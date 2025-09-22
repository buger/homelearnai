<?php

namespace App\Services;

use App\Models\Flashcard;
use Illuminate\Support\Facades\Log;

class DuplicateDetectionService
{
    /**
     * Similarity threshold for text comparison (0.0 to 1.0)
     */
    private const SIMILARITY_THRESHOLD = 0.8;

    /**
     * Minimum question length to consider for similarity
     */
    private const MIN_QUESTION_LENGTH = 5;

    /**
     * Maximum number of existing flashcards to compare against
     */
    private const MAX_COMPARISON_LIMIT = 1000;

    /**
     * Detect duplicates in import data against existing flashcards
     *
     * @param  array  $importCards  Array of cards to import
     * @param  int  $topicId  Topic ID to check against
     */
    public function detectDuplicates(array $importCards, int $topicId): array
    {
        try {
            $duplicates = [];
            $uniqueCards = [];

            // Get existing flashcards for this topic
            $existingCards = $this->getExistingCards($topicId);

            foreach ($importCards as $index => $importCard) {
                $duplicateInfo = $this->findDuplicateCard($importCard, $existingCards, $uniqueCards);

                if ($duplicateInfo) {
                    $duplicates[] = [
                        'import_index' => $index,
                        'import_card' => $importCard,
                        'duplicate_type' => $duplicateInfo['type'], // 'existing' or 'within_import'
                        'existing_card' => $duplicateInfo['existing_card'] ?? null,
                        'similarity_score' => $duplicateInfo['similarity_score'],
                        'match_reason' => $duplicateInfo['match_reason'],
                        'suggested_action' => $this->suggestAction($duplicateInfo),
                    ];
                } else {
                    $uniqueCards[] = $importCard;
                }
            }

            return [
                'success' => true,
                'duplicates' => $duplicates,
                'unique_cards' => $uniqueCards,
                'total_import' => count($importCards),
                'duplicate_count' => count($duplicates),
                'unique_count' => count($uniqueCards),
                'existing_cards_checked' => count($existingCards),
            ];

        } catch (\Exception $e) {
            Log::error('Duplicate detection error', [
                'topic_id' => $topicId,
                'import_count' => count($importCards),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to detect duplicates: '.$e->getMessage(),
                'duplicates' => [],
                'unique_cards' => $importCards,
            ];
        }
    }

    /**
     * Apply merge strategy to resolve duplicates
     *
     * @param  array  $duplicates  Array of duplicate information
     * @param  array  $strategy  Strategy configuration
     * @param  int  $topicId  Topic ID
     * @param  int  $userId  User ID
     */
    public function applyMergeStrategy(array $duplicates, array $strategy, int $topicId, int $userId): array
    {
        $results = [
            'skipped' => 0,
            'updated' => 0,
            'kept_both' => 0,
            'replaced' => 0,
            'errors' => [],
        ];

        try {
            foreach ($duplicates as $duplicate) {
                $action = $strategy['global_action'] ??
                         ($strategy['actions'][$duplicate['import_index']] ?? 'skip');

                $result = $this->applyDuplicateAction($duplicate, $action, $topicId, $userId);

                if ($result['success']) {
                    $results[$result['action']]++;
                } else {
                    $results['errors'][] = $result['error'];
                }
            }

            return [
                'success' => count($results['errors']) === 0,
                'results' => $results,
                'total_processed' => count($duplicates),
            ];

        } catch (\Exception $e) {
            Log::error('Merge strategy application error', [
                'topic_id' => $topicId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to apply merge strategy: '.$e->getMessage(),
                'results' => $results,
            ];
        }
    }

    /**
     * Find duplicate card among existing and within import
     *
     * @param  array  $uniqueCards  Cards already processed in this import
     */
    private function findDuplicateCard(array $importCard, array $existingCards, array $uniqueCards): ?array
    {
        $question = trim($importCard['question'] ?? '');
        $answer = trim($importCard['answer'] ?? '');

        if (strlen($question) < self::MIN_QUESTION_LENGTH) {
            return null;
        }

        // First check for exact matches
        $exactMatch = $this->findExactMatch($question, $answer, $existingCards);
        if ($exactMatch) {
            return [
                'type' => 'existing',
                'existing_card' => $exactMatch,
                'similarity_score' => 1.0,
                'match_reason' => 'exact_match',
            ];
        }

        // Check for exact match within current import batch
        $importMatch = $this->findExactMatchInImport($question, $answer, $uniqueCards);
        if ($importMatch) {
            return [
                'type' => 'within_import',
                'existing_card' => $importMatch,
                'similarity_score' => 1.0,
                'match_reason' => 'exact_match_in_import',
            ];
        }

        // Check for similar matches
        $similarMatch = $this->findSimilarMatch($question, $answer, $existingCards);
        if ($similarMatch) {
            return [
                'type' => 'existing',
                'existing_card' => $similarMatch['card'],
                'similarity_score' => $similarMatch['score'],
                'match_reason' => 'similar_content',
            ];
        }

        // Check for similar match within import
        $importSimilarMatch = $this->findSimilarMatchInImport($question, $answer, $uniqueCards);
        if ($importSimilarMatch) {
            return [
                'type' => 'within_import',
                'existing_card' => $importSimilarMatch['card'],
                'similarity_score' => $importSimilarMatch['score'],
                'match_reason' => 'similar_content_in_import',
            ];
        }

        return null;
    }

    /**
     * Find exact match in existing cards
     */
    private function findExactMatch(string $question, string $answer, array $existingCards): ?array
    {
        foreach ($existingCards as $existing) {
            if ($this->normalizeText($existing['question']) === $this->normalizeText($question) &&
                $this->normalizeText($existing['answer']) === $this->normalizeText($answer)) {
                return $existing;
            }
        }

        return null;
    }

    /**
     * Find exact match within import batch
     */
    private function findExactMatchInImport(string $question, string $answer, array $uniqueCards): ?array
    {
        foreach ($uniqueCards as $unique) {
            if ($this->normalizeText($unique['question']) === $this->normalizeText($question) &&
                $this->normalizeText($unique['answer']) === $this->normalizeText($answer)) {
                return $unique;
            }
        }

        return null;
    }

    /**
     * Find similar match in existing cards
     */
    private function findSimilarMatch(string $question, string $answer, array $existingCards): ?array
    {
        $bestMatch = null;
        $bestScore = 0;

        foreach ($existingCards as $existing) {
            $score = $this->calculateSimilarity($question, $answer, $existing['question'], $existing['answer']);

            if ($score >= self::SIMILARITY_THRESHOLD && $score > $bestScore) {
                $bestScore = $score;
                $bestMatch = ['card' => $existing, 'score' => $score];
            }
        }

        return $bestMatch;
    }

    /**
     * Find similar match within import batch
     */
    private function findSimilarMatchInImport(string $question, string $answer, array $uniqueCards): ?array
    {
        $bestMatch = null;
        $bestScore = 0;

        foreach ($uniqueCards as $unique) {
            $score = $this->calculateSimilarity($question, $answer, $unique['question'], $unique['answer']);

            if ($score >= self::SIMILARITY_THRESHOLD && $score > $bestScore) {
                $bestScore = $score;
                $bestMatch = ['card' => $unique, 'score' => $score];
            }
        }

        return $bestMatch;
    }

    /**
     * Calculate similarity between two flashcards
     *
     * @param  string  $q1  Question 1
     * @param  string  $a1  Answer 1
     * @param  string  $q2  Question 2
     * @param  string  $a2  Answer 2
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculateSimilarity(string $q1, string $a1, string $q2, string $a2): float
    {
        // Normalize texts
        $nq1 = $this->normalizeText($q1);
        $na1 = $this->normalizeText($a1);
        $nq2 = $this->normalizeText($q2);
        $na2 = $this->normalizeText($a2);

        // Calculate similarity for questions and answers separately
        $questionSimilarity = $this->textSimilarity($nq1, $nq2);
        $answerSimilarity = $this->textSimilarity($na1, $na2);

        // Weight question similarity higher (70%) than answer similarity (30%)
        return ($questionSimilarity * 0.7) + ($answerSimilarity * 0.3);
    }

    /**
     * Calculate text similarity using multiple methods
     */
    private function textSimilarity(string $text1, string $text2): float
    {
        if ($text1 === $text2) {
            return 1.0;
        }

        if (empty($text1) || empty($text2)) {
            return 0.0;
        }

        // Use Levenshtein distance for similar texts
        $maxLen = max(strlen($text1), strlen($text2));
        if ($maxLen > 255) {
            // For long texts, use word-based similarity
            return $this->wordSimilarity($text1, $text2);
        }

        $distance = levenshtein($text1, $text2);

        return 1.0 - ($distance / $maxLen);
    }

    /**
     * Calculate word-based similarity for longer texts
     */
    private function wordSimilarity(string $text1, string $text2): float
    {
        $words1 = array_filter(explode(' ', $text1));
        $words2 = array_filter(explode(' ', $text2));

        if (empty($words1) || empty($words2)) {
            return 0.0;
        }

        $intersection = count(array_intersect($words1, $words2));
        $union = count(array_unique(array_merge($words1, $words2)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Normalize text for comparison
     */
    private function normalizeText(string $text): string
    {
        // Convert to lowercase
        $text = strtolower($text);

        // Remove HTML tags
        $text = strip_tags($text);

        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Remove punctuation for better comparison
        $text = preg_replace('/[^\w\s]/', '', $text);

        return trim($text);
    }

    /**
     * Get existing flashcards for comparison
     */
    private function getExistingCards(int $topicId): array
    {
        return Flashcard::where('topic_id', $topicId)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->limit(self::MAX_COMPARISON_LIMIT)
            ->get(['id', 'question', 'answer', 'card_type', 'tags', 'created_at'])
            ->toArray();
    }

    /**
     * Suggest action for duplicate resolution
     */
    private function suggestAction(array $duplicateInfo): string
    {
        $score = $duplicateInfo['similarity_score'];
        $type = $duplicateInfo['type'];

        // Exact matches should usually be skipped
        if ($score >= 0.95) {
            return 'skip';
        }

        // High similarity - suggest review
        if ($score >= 0.9) {
            return 'review';
        }

        // Medium similarity - could update or keep both
        if ($score >= 0.8) {
            return $type === 'existing' ? 'update' : 'keep_both';
        }

        // Lower similarity - keep both
        return 'keep_both';
    }

    /**
     * Apply specific duplicate resolution action
     */
    private function applyDuplicateAction(array $duplicate, string $action, int $topicId, int $userId): array
    {
        try {
            switch ($action) {
                case 'skip':
                    return ['success' => true, 'action' => 'skipped'];

                case 'update':
                    return $this->updateExistingCard($duplicate, $topicId);

                case 'replace':
                    return $this->replaceExistingCard($duplicate, $topicId);

                case 'keep_both':
                    return $this->keepBothCards($duplicate, $topicId);

                default:
                    return [
                        'success' => false,
                        'error' => "Unknown action: {$action}",
                    ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Failed to apply action '{$action}': ".$e->getMessage(),
            ];
        }
    }

    /**
     * Update existing card with import data
     */
    private function updateExistingCard(array $duplicate, int $topicId): array
    {
        if ($duplicate['duplicate_type'] !== 'existing') {
            return ['success' => true, 'action' => 'skipped']; // Can't update within-import duplicates
        }

        $existingCard = Flashcard::find($duplicate['existing_card']['id']);
        if (! $existingCard) {
            return ['success' => false, 'error' => 'Existing card not found'];
        }

        $importCard = $duplicate['import_card'];

        // Update with new data, keeping some existing metadata
        $existingCard->update([
            'question' => $importCard['question'],
            'answer' => $importCard['answer'],
            'hint' => $importCard['hint'] ?? $existingCard->hint,
            'difficulty_level' => $importCard['difficulty_level'] ?? $existingCard->difficulty_level,
            'tags' => array_unique(array_merge($existingCard->tags ?? [], $importCard['tags'] ?? [])),
            'import_source' => $importCard['import_source'] ?? $existingCard->import_source,
        ]);

        return ['success' => true, 'action' => 'updated'];
    }

    /**
     * Replace existing card with import data
     */
    private function replaceExistingCard(array $duplicate, int $topicId): array
    {
        if ($duplicate['duplicate_type'] !== 'existing') {
            return ['success' => true, 'action' => 'skipped'];
        }

        $existingCard = Flashcard::find($duplicate['existing_card']['id']);
        if (! $existingCard) {
            return ['success' => false, 'error' => 'Existing card not found'];
        }

        $importCard = $duplicate['import_card'];

        // Replace completely with import data
        $existingCard->update(array_merge($importCard, [
            'topic_id' => $topicId,
            'is_active' => true,
        ]));

        return ['success' => true, 'action' => 'replaced'];
    }

    /**
     * Keep both existing and import cards
     */
    private function keepBothCards(array $duplicate, int $topicId): array
    {
        $importCard = $duplicate['import_card'];

        // Create new flashcard from import data
        $flashcard = Flashcard::create(array_merge($importCard, [
            'topic_id' => $topicId,
            'is_active' => true,
        ]));

        return ['success' => true, 'action' => 'kept_both'];
    }

    /**
     * Get duplicate detection statistics
     */
    public function getDetectionStatistics(int $topicId): array
    {
        $existingCount = Flashcard::where('topic_id', $topicId)
            ->where('is_active', true)
            ->count();

        return [
            'existing_cards' => $existingCount,
            'comparison_limit' => self::MAX_COMPARISON_LIMIT,
            'similarity_threshold' => self::SIMILARITY_THRESHOLD,
            'will_check_against' => min($existingCount, self::MAX_COMPARISON_LIMIT),
        ];
    }
}
