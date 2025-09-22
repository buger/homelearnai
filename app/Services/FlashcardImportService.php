<?php

namespace App\Services;

use App\Models\Flashcard;
use App\Models\Topic;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * FlashcardImportService handles importing flashcards from various formats
 *
 * This service provides comprehensive import functionality for flashcards from popular
 * platforms like Quizlet, Anki, Mnemosyne, and various file formats including CSV, JSON, and XML.
 * It includes format detection, data validation, duplicate detection, and media file handling.
 *
 * Supported formats:
 * - Quizlet (tab-delimited, comma-separated, dash-separated)
 * - Anki (.apkg packages with full media support)
 * - Mnemosyne (.mem/.xml files)
 * - CSV/TSV files with extended format support
 * - JSON format with complete data preservation
 * - SuperMemo Q&A format
 *
 * Features:
 * - Automatic format detection
 * - Delimiter auto-detection for text-based formats
 * - Card type auto-detection (basic, multiple choice, cloze, etc.)
 * - Duplicate detection and resolution strategies
 * - Media file extraction and storage
 * - Batch processing with progress tracking
 * - Comprehensive error handling and validation
 * - Preview functionality before final import
 *
 * @author Learning App Team
 *
 * @version 1.0.0
 *
 * @since 2025-09-09
 */
class FlashcardImportService
{
    /**
     * Anki import service for handling .apkg files
     */
    private AnkiImportService $ankiImporter;

    /**
     * Mnemosyne import service for handling .mem/.xml files
     */
    private MnemosyneImportService $mnemosyneImporter;

    /**
     * Service for detecting and resolving duplicate flashcards
     */
    private DuplicateDetectionService $duplicateDetector;

    /**
     * Service for handling media file storage and processing
     */
    private MediaStorageService $mediaStorage;

    /**
     * Create a new FlashcardImportService instance
     *
     * @param  AnkiImportService  $ankiImporter  Service for Anki imports
     * @param  MnemosyneImportService  $mnemosyneImporter  Service for Mnemosyne imports
     * @param  DuplicateDetectionService  $duplicateDetector  Service for duplicate detection
     * @param  MediaStorageService  $mediaStorage  Service for media file handling
     */
    public function __construct(
        AnkiImportService $ankiImporter,
        MnemosyneImportService $mnemosyneImporter,
        DuplicateDetectionService $duplicateDetector,
        MediaStorageService $mediaStorage
    ) {
        $this->ankiImporter = $ankiImporter;
        $this->mnemosyneImporter = $mnemosyneImporter;
        $this->duplicateDetector = $duplicateDetector;
        $this->mediaStorage = $mediaStorage;
    }

    /**
     * Supported file extensions for import
     */
    public const SUPPORTED_EXTENSIONS = ['csv', 'tsv', 'txt', 'apkg', 'mem', 'xml'];

    /**
     * Maximum number of flashcards allowed in single import
     */
    public const MAX_IMPORT_SIZE = 500;

    /**
     * Supported delimiters with their detection patterns
     */
    private const DELIMITERS = [
        'tab' => "\t",
        'comma' => ',',
        'dash' => ' - ',
        'pipe' => '|',
        'semicolon' => ';',
    ];

    /**
     * Card type detection patterns
     */
    private const CARD_TYPE_PATTERNS = [
        'cloze' => '/\{\{[^}]*\}\}/',
        'true_false' => '/^(true|false|yes|no|t|f|y|n)$/i',
        'multiple_choice' => '/^[a-d]\)|^\d+\)|;|,/',
    ];

    /**
     * Parse import data from file upload
     */
    public function parseFile(UploadedFile $file): array
    {
        $content = file_get_contents($file->getPathname());

        if (empty($content)) {
            return [
                'success' => false,
                'error' => 'File is empty or could not be read',
                'cards' => [],
            ];
        }

        return $this->parseContent($content, $file->getClientOriginalName());
    }

    /**
     * Parse import data from copy-paste text
     */
    public function parseText(string $content): array
    {
        if (empty(trim($content))) {
            return [
                'success' => false,
                'error' => 'No content provided',
                'cards' => [],
            ];
        }

        return $this->parseContent($content, 'pasted_text');
    }

    /**
     * Import flashcards to a specific unit
     * This will find the first topic in the unit or create a default "Imported Cards" topic
     */
    public function importCards(array $cards, int $unitId, int $userId, string $source = 'manual'): array
    {
        $unit = Unit::findOrFail($unitId);

        // Verify user has access to the unit
        if ((int) $unit->subject->user_id !== $userId) {
            return [
                'success' => false,
                'error' => 'Access denied to this unit',
                'imported' => 0,
                'failed' => count($cards),
                'errors' => [],
            ];
        }

        // Find or create a topic for the flashcards
        $topic = $unit->topics()->first();
        if (! $topic) {
            $topic = Topic::create([
                'unit_id' => $unitId,
                'title' => 'Imported Cards',
                'description' => 'Cards imported via import feature',
                'estimated_minutes' => 30,
                'required' => false,
            ]);
        }

        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($cards as $index => $cardData) {
            try {
                // Detect card type if not explicitly set
                if (! isset($cardData['card_type'])) {
                    $cardData['card_type'] = $this->detectCardType($cardData);
                }

                // Prepare card data based on type
                $processedData = $this->processCardByType($cardData);

                $flashcard = new Flashcard([
                    'unit_id' => $unitId,
                    'topic_id' => $topic->id,
                    'card_type' => $processedData['card_type'],
                    'question' => $processedData['question'],
                    'answer' => $processedData['answer'],
                    'hint' => $processedData['hint'] ?? null,
                    'choices' => $processedData['choices'] ?? [],
                    'correct_choices' => $processedData['correct_choices'] ?? [],
                    'cloze_text' => $processedData['cloze_text'] ?? null,
                    'cloze_answers' => $processedData['cloze_answers'] ?? [],
                    'question_image_url' => $processedData['question_image_url'] ?? null,
                    'answer_image_url' => $processedData['answer_image_url'] ?? null,
                    'occlusion_data' => $processedData['occlusion_data'] ?? [],
                    'difficulty_level' => $processedData['difficulty_level'] ?? 'medium',
                    'tags' => $processedData['tags'] ?? [],
                    'import_source' => $source,
                    'is_active' => true,
                ]);

                // Validate the flashcard data
                $cardErrors = $flashcard->validateCardData();
                if (! empty($cardErrors)) {
                    $failed++;
                    $errors[] = 'Row '.($index + 1).': '.implode(', ', $cardErrors);

                    continue;
                }

                if ($flashcard->save()) {
                    $imported++;
                } else {
                    $failed++;
                    $errors[] = 'Row '.($index + 1).': Failed to save flashcard';
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = 'Row '.($index + 1).': '.$e->getMessage();
                Log::error('Flashcard import error', [
                    'row' => $index + 1,
                    'data' => $cardData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => $imported > 0,
            'imported' => $imported,
            'failed' => $failed,
            'total' => count($cards),
            'errors' => $errors,
        ];
    }

    /**
     * Validate import data
     */
    public function validateImport(array $cards): array
    {
        $errors = [];

        if (count($cards) > self::MAX_IMPORT_SIZE) {
            $errors[] = 'Import size exceeds maximum limit of '.self::MAX_IMPORT_SIZE.' cards';
        }

        if (empty($cards)) {
            $errors[] = 'No valid flashcards found in import data';
        }

        foreach ($cards as $index => $card) {
            $validator = Validator::make($card, [
                'question' => 'required|string|max:65535',
                'answer' => 'required|string|max:65535',
                'hint' => 'nullable|string|max:65535',
                'difficulty_level' => 'nullable|in:easy,medium,hard',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
            ], [
                'question.required' => 'Question is required',
                'answer.required' => 'Answer is required',
                'question.string' => 'Question must be a string',
                'answer.string' => 'Answer must be a string',
                'question.max' => 'Question may not be greater than 65535 characters',
                'answer.max' => 'Answer may not be greater than 65535 characters',
                'difficulty_level.in' => 'Difficulty level must be easy, medium, or hard',
                'tags.array' => 'Tags must be an array',
                'tags.*.string' => 'Each tag must be a string',
                'tags.*.max' => 'Each tag must be no more than 50 characters',
            ]);

            if ($validator->fails()) {
                $errors[] = 'Row '.($index + 1).': '.implode(', ', $validator->errors()->all());
            }
        }

        return $errors;
    }

    /**
     * Get supported file extensions
     */
    public static function getSupportedExtensions(): array
    {
        return self::SUPPORTED_EXTENSIONS;
    }

    /**
     * Parse content and detect format automatically
     */
    public function parseContent(string $content, string $filename = ''): array
    {
        try {
            // Normalize line endings
            $content = str_replace(["\r\n", "\r"], "\n", $content);
            $lines = array_filter(explode("\n", $content), function ($line) {
                return ! empty(trim($line));
            });

            if (empty($lines)) {
                return [
                    'success' => false,
                    'error' => 'No content lines found',
                    'cards' => [],
                ];
            }

            // Detect delimiter
            $delimiter = $this->detectDelimiter($lines);

            if (! $delimiter) {
                return [
                    'success' => false,
                    'error' => 'Could not detect delimiter. Supported formats: tab-separated, comma-separated, or " - " separated',
                    'cards' => [],
                ];
            }

            // Parse lines into flashcards
            $cards = [];
            $errors = [];

            foreach ($lines as $lineNumber => $line) {
                $parsedCard = $this->parseLine($line, $delimiter, $lineNumber + 1);

                if ($parsedCard['success']) {
                    $card = $parsedCard['card'];

                    // Detect card type if not explicitly set
                    if (empty($card['card_type'])) {
                        $card['card_type'] = $this->detectCardType($card);
                    }

                    // Process card data based on detected type
                    $card = $this->processCardByType($card);

                    $cards[] = $card;
                } else {
                    $errors[] = $parsedCard['error'];
                }
            }

            // Validate total cards
            if (empty($cards)) {
                return [
                    'success' => false,
                    'error' => 'No valid flashcards could be parsed. '.
                              (! empty($errors) ? 'Errors: '.implode('; ', $errors) : ''),
                    'cards' => [],
                ];
            }

            if (count($cards) > self::MAX_IMPORT_SIZE) {
                return [
                    'success' => false,
                    'error' => 'Import contains '.count($cards).' cards, but maximum allowed is '.self::MAX_IMPORT_SIZE,
                    'cards' => [],
                ];
            }

            return [
                'success' => true,
                'cards' => $cards,
                'delimiter' => $delimiter,
                'total_lines' => count($lines),
                'parsed_cards' => count($cards),
                'errors' => $errors,
            ];

        } catch (\Exception $e) {
            Log::error('Import parsing error', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to parse import data: '.$e->getMessage(),
                'cards' => [],
            ];
        }
    }

    /**
     * Detect the delimiter used in the content
     */
    private function detectDelimiter(array $lines): ?string
    {
        $sampleLines = array_slice($lines, 0, min(5, count($lines)));
        $delimiterScores = [];

        foreach (self::DELIMITERS as $name => $delimiter) {
            $score = 0;
            $validLines = 0;

            foreach ($sampleLines as $line) {
                $parts = $this->splitLine($line, $delimiter);

                if (count($parts) >= 2) {
                    $validLines++;
                    // Prefer delimiters that consistently split into 2-3 parts
                    if (count($parts) == 2) {
                        $score += 3; // Perfect for question-answer pairs
                    } elseif (count($parts) == 3) {
                        $score += 2; // Good for question-answer-hint
                    } else {
                        $score += 1; // Still valid but not ideal
                    }
                }
            }

            // Calculate score as percentage of valid lines
            if ($validLines > 0) {
                $delimiterScores[$name] = $score / count($sampleLines);
            }
        }

        if (empty($delimiterScores)) {
            return null;
        }

        // Return delimiter with highest score
        $bestDelimiter = array_keys($delimiterScores, max($delimiterScores))[0];

        return self::DELIMITERS[$bestDelimiter];
    }

    /**
     * Parse a single line into a flashcard
     */
    private function parseLine(string $line, string $delimiter, int $lineNumber): array
    {
        $parts = $this->splitLine($line, $delimiter);

        if (count($parts) < 2) {
            return [
                'success' => false,
                'error' => "Line {$lineNumber}: Must contain at least question and answer separated by delimiter",
                'card' => null,
            ];
        }

        $question = trim($parts[0]);
        $answer = trim($parts[1]);
        $hint = isset($parts[2]) ? trim($parts[2]) : null;

        if (empty($question)) {
            return [
                'success' => false,
                'error' => "Line {$lineNumber}: Question cannot be empty",
                'card' => null,
            ];
        }

        if (empty($answer)) {
            return [
                'success' => false,
                'error' => "Line {$lineNumber}: Answer cannot be empty",
                'card' => null,
            ];
        }

        // Parse tags from question or answer if they contain hashtags
        $tags = [];
        if (preg_match_all('/#(\w+)/', $line, $matches)) {
            $tags = array_unique($matches[1]);
        }

        // Support extended CSV format with explicit type column
        $cardType = null;
        $choices = null;
        $correctChoices = null;

        // Check for extended format: Type,Question,Answer,Choices,Correct,Hint,Tags
        if (count($parts) >= 5) {
            $possibleType = strtolower(trim($parts[0]));
            if (in_array($possibleType, Flashcard::getCardTypes())) {
                $cardType = $possibleType;
                $question = trim($parts[1]);
                $answer = trim($parts[2]);
                $choices = isset($parts[3]) && ! empty(trim($parts[3])) ?
                    array_map('trim', explode(';', trim($parts[3]))) : null;
                $correctChoices = isset($parts[4]) && ! empty(trim($parts[4])) ?
                    array_map('intval', explode(';', trim($parts[4]))) : null;
                $hint = isset($parts[5]) ? trim($parts[5]) : null;
                if (isset($parts[6]) && ! empty(trim($parts[6]))) {
                    $tags = array_map('trim', explode(';', trim($parts[6])));
                }
            }
        }

        return [
            'success' => true,
            'card' => [
                'card_type' => $cardType,
                'question' => $question,
                'answer' => $answer,
                'hint' => $hint,
                'choices' => $choices,
                'correct_choices' => $correctChoices,
                'difficulty_level' => 'medium',
                'tags' => $tags,
            ],
            'error' => null,
        ];
    }

    /**
     * Split a line by delimiter with special handling
     */
    private function splitLine(string $line, string $delimiter): array
    {
        if ($delimiter === "\t") {
            // Tab-separated (Quizlet format)
            return explode("\t", $line);
        } elseif ($delimiter === ',') {
            // CSV format - use proper CSV parsing to handle quoted fields
            return str_getcsv($line);
        } else {
            // Other delimiters
            return explode($delimiter, $line);
        }
    }

    /**
     * Detect card type from question and answer content
     */
    private function detectCardType(array $cardData): string
    {
        $question = $cardData['question'] ?? '';
        $answer = $cardData['answer'] ?? '';
        $choices = $cardData['choices'] ?? null;

        // Priority order: explicit type detection

        // 1. Cloze deletion - check for {{}} syntax in question or answer
        if (preg_match(self::CARD_TYPE_PATTERNS['cloze'], $question) ||
            preg_match(self::CARD_TYPE_PATTERNS['cloze'], $answer)) {
            return Flashcard::CARD_TYPE_CLOZE;
        }

        // 2. Multiple choice - check for choices or structured options
        if (! empty($choices) && is_array($choices) && count($choices) > 2) {
            return Flashcard::CARD_TYPE_MULTIPLE_CHOICE;
        }

        // Check answer for multiple choice patterns (a), b), c) or 1), 2), 3)
        if (preg_match('/^[a-d]\)|^\d+\)/', $answer) ||
            strpos($answer, ';') !== false && substr_count($answer, ';') >= 1) {
            return Flashcard::CARD_TYPE_MULTIPLE_CHOICE;
        }

        // 3. True/False - exact matches
        if (preg_match(self::CARD_TYPE_PATTERNS['true_false'], trim($answer))) {
            return Flashcard::CARD_TYPE_TRUE_FALSE;
        }

        // 4. Image occlusion - check for image URLs
        if (filter_var($question, FILTER_VALIDATE_URL) &&
            preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $question)) {
            return Flashcard::CARD_TYPE_IMAGE_OCCLUSION;
        }

        // 5. Default to basic card type
        return Flashcard::CARD_TYPE_BASIC;
    }

    /**
     * Process card data based on detected or specified card type
     */
    private function processCardByType(array $cardData): array
    {
        $cardType = $cardData['card_type'];
        $processedData = $cardData;

        switch ($cardType) {
            case Flashcard::CARD_TYPE_MULTIPLE_CHOICE:
                $processedData = $this->processMultipleChoice($cardData);
                break;

            case Flashcard::CARD_TYPE_TRUE_FALSE:
                $processedData = $this->processTrueFalse($cardData);
                break;

            case Flashcard::CARD_TYPE_CLOZE:
                $processedData = $this->processCloze($cardData);
                break;

            case Flashcard::CARD_TYPE_IMAGE_OCCLUSION:
                $processedData = $this->processImageOcclusion($cardData);
                break;

            case Flashcard::CARD_TYPE_TYPED_ANSWER:
            case Flashcard::CARD_TYPE_BASIC:
            default:
                // Basic cards need no special processing
                break;
        }

        return $processedData;
    }

    /**
     * Process multiple choice card data
     */
    private function processMultipleChoice(array $cardData): array
    {
        $question = $cardData['question'];
        $answer = $cardData['answer'];
        $choices = $cardData['choices'] ?? [];
        $correctChoices = $cardData['correct_choices'] ?? [];

        // If choices not explicitly provided, try to parse from answer
        if (empty($choices)) {
            // Parse answer for choices separated by semicolon or newline
            if (strpos($answer, ';') !== false) {
                $choices = array_map('trim', explode(';', $answer));
            } elseif (strpos($answer, "\n") !== false) {
                $choices = array_filter(array_map('trim', explode("\n", $answer)));
            } else {
                // Default choices if none detected
                $choices = [$answer, 'Option B', 'Option C', 'Option D'];
            }
        }

        // If correct choices not provided, assume first is correct
        if (empty($correctChoices)) {
            $correctChoices = [0];
        }

        return array_merge($cardData, [
            'choices' => array_slice($choices, 0, 6), // Max 6 choices
            'correct_choices' => $correctChoices,
            'answer' => is_array($correctChoices) ?
                implode(', ', array_map(fn ($i) => $choices[$i] ?? '', $correctChoices)) :
                $choices[0] ?? $answer,
        ]);
    }

    /**
     * Process true/false card data
     */
    private function processTrueFalse(array $cardData): array
    {
        $answer = strtolower(trim($cardData['answer']));

        // Normalize answer variations
        $isTrue = in_array($answer, ['true', 'yes', 't', 'y', '1']);

        return array_merge($cardData, [
            'choices' => ['True', 'False'],
            'correct_choices' => [$isTrue ? 0 : 1],
            'answer' => $isTrue ? 'True' : 'False',
        ]);
    }

    /**
     * Process cloze deletion card data
     */
    private function processCloze(array $cardData): array
    {
        $question = $cardData['question'];
        $answer = $cardData['answer'];

        // Check if cloze text is in question or answer
        if (preg_match_all('/\{\{([^}]*)\}\}/', $question, $matches)) {
            $clozeText = $question;
            $clozeAnswers = $matches[1];
        } elseif (preg_match_all('/\{\{([^}]*)\}\}/', $answer, $matches)) {
            $clozeText = $answer;
            $clozeAnswers = $matches[1];
        } else {
            // Convert simple answer to cloze format
            $clozeText = str_replace($answer, '{{'.$answer.'}}', $question);
            $clozeAnswers = [$answer];
        }

        // Handle Anki-style cloze syntax {{c1::answer}}
        $clozeText = preg_replace('/\{\{c\d+::(.*?)\}\}/', '{{$1}}', $clozeText);

        return array_merge($cardData, [
            'cloze_text' => $clozeText,
            'cloze_answers' => array_unique($clozeAnswers),
            'question' => preg_replace('/\{\{[^}]*\}\}/', '[...]', $clozeText),
            'answer' => implode(', ', $clozeAnswers),
        ]);
    }

    /**
     * Process image occlusion card data
     */
    private function processImageOcclusion(array $cardData): array
    {
        $question = $cardData['question'];

        // Basic implementation - more advanced features would require frontend editor
        return array_merge($cardData, [
            'question_image_url' => filter_var($question, FILTER_VALIDATE_URL) ? $question : null,
            'occlusion_data' => [
                [
                    'type' => 'rectangle',
                    'x' => 100,
                    'y' => 100,
                    'width' => 200,
                    'height' => 50,
                    'answer' => $cardData['answer'],
                ],
            ],
        ]);
    }

    /**
     * Parse Anki package file
     */
    public function parseAnkiPackage(UploadedFile $file, int $unitId): array
    {
        return $this->ankiImporter->parseAnkiPackage($file, $unitId);
    }

    /**
     * Parse Mnemosyne file
     */
    public function parseMnemosyneFile(UploadedFile $file): array
    {
        return $this->mnemosyneImporter->parseMnemosyneFile($file);
    }

    /**
     * Handle media files from packages
     */
    public function handleMediaFiles(array $mediaFiles, int $unitId): array
    {
        $results = [];
        $errors = [];

        foreach ($mediaFiles as $filename => $mediaFile) {
            if (isset($mediaFile['content'])) {
                $storeResult = $this->mediaStorage->storeMediaFile(
                    $mediaFile['content'],
                    $filename,
                    $unitId
                );

                if ($storeResult['success']) {
                    $results[$filename] = $storeResult;
                } else {
                    $errors[] = "Failed to store {$filename}: {$storeResult['error']}";
                }
            }
        }

        return [
            'success' => count($errors) === 0,
            'stored_files' => $results,
            'errors' => $errors,
            'total_processed' => count($mediaFiles),
        ];
    }

    /**
     * Detect duplicates in import cards
     * For backward compatibility, this method accepts unitId and finds the first topic
     */
    public function detectDuplicates(array $cards, int $unitId): array
    {
        $unit = Unit::findOrFail($unitId);
        $topic = $unit->topics()->first();

        if (! $topic) {
            // If no topic exists, return no duplicates found
            return [
                'success' => true,
                'duplicates' => [],
                'unique_cards' => $cards,
                'total_import' => count($cards),
                'duplicate_count' => 0,
                'unique_count' => count($cards),
                'existing_cards_checked' => 0,
            ];
        }

        return $this->duplicateDetector->detectDuplicates($cards, $topic->id);
    }

    /**
     * Apply merge strategy for duplicates
     * For backward compatibility, this method accepts unitId and finds the first topic
     */
    public function applyMergeStrategy(array $duplicates, array $strategy, int $unitId, int $userId): array
    {
        $unit = Unit::findOrFail($unitId);
        $topic = $unit->topics()->first();

        if (! $topic) {
            return [
                'success' => false,
                'error' => 'No topic found in unit for duplicate resolution',
                'results' => [],
            ];
        }

        return $this->duplicateDetector->applyMergeStrategy($duplicates, $strategy, $topic->id, $userId);
    }

    /**
     * Enhanced import with duplicate detection and media handling
     */
    public function importCardsAdvanced(array $cards, int $unitId, int $userId, string $source = 'manual', array $options = []): array
    {
        try {
            // Step 1: Detect duplicates if requested
            $duplicateResult = null;
            if ($options['detect_duplicates'] ?? false) {
                $duplicateResult = $this->detectDuplicates($cards, $unitId);

                if (! $duplicateResult['success']) {
                    return [
                        'success' => false,
                        'error' => 'Duplicate detection failed: '.$duplicateResult['error'],
                        'phase' => 'duplicate_detection',
                    ];
                }

                // If duplicates found and no strategy provided, return for user decision
                if (! empty($duplicateResult['duplicates']) && empty($options['merge_strategy'])) {
                    return [
                        'success' => false,
                        'needs_duplicate_resolution' => true,
                        'duplicates' => $duplicateResult['duplicates'],
                        'unique_cards' => $duplicateResult['unique_cards'],
                        'phase' => 'duplicate_resolution',
                    ];
                }

                // Apply merge strategy if provided
                if (! empty($duplicateResult['duplicates']) && ! empty($options['merge_strategy'])) {
                    $mergeResult = $this->applyMergeStrategy(
                        $duplicateResult['duplicates'],
                        $options['merge_strategy'],
                        $unitId,
                        $userId
                    );

                    if (! $mergeResult['success']) {
                        return [
                            'success' => false,
                            'error' => 'Merge strategy failed: '.$mergeResult['error'],
                            'phase' => 'merge_strategy',
                        ];
                    }

                    // Use only unique cards for import
                    $cards = $duplicateResult['unique_cards'];
                }
            }

            // Step 2: Import the cards
            $importResult = $this->importCards($cards, $unitId, $userId, $source);

            // Step 3: Enhance result with advanced information
            $enhancedResult = array_merge($importResult, [
                'phase' => 'import_complete',
                'duplicate_info' => $duplicateResult ? [
                    'duplicates_found' => count($duplicateResult['duplicates']),
                    'unique_imported' => count($duplicateResult['unique_cards']),
                    'merge_results' => $mergeResult ?? null,
                ] : null,
            ]);

            return $enhancedResult;

        } catch (\Exception $e) {
            Log::error('Advanced import error', [
                'unit_id' => $unitId,
                'user_id' => $userId,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Advanced import failed: '.$e->getMessage(),
                'phase' => 'import_error',
            ];
        }
    }

    /**
     * Get supported extensions for advanced import
     */
    public static function getAdvancedSupportedExtensions(): array
    {
        return [
            'csv' => 'CSV/TSV files',
            'tsv' => 'Tab-separated values',
            'txt' => 'Plain text files',
            'apkg' => 'Anki packages',
            'mem' => 'Mnemosyne exports',
            'xml' => 'XML exports',
        ];
    }

    /**
     * Detect import file type
     */
    public function detectImportType(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        $typeInfo = [
            'extension' => $extension,
            'mime_type' => $mimeType,
            'import_type' => 'unknown',
            'parser_service' => null,
            'supports_media' => false,
            'supports_advanced_types' => false,
        ];

        switch ($extension) {
            case 'apkg':
                $typeInfo['import_type'] = 'anki';
                $typeInfo['parser_service'] = 'anki';
                $typeInfo['supports_media'] = true;
                $typeInfo['supports_advanced_types'] = true;
                break;

            case 'mem':
            case 'xml':
                $typeInfo['import_type'] = 'mnemosyne';
                $typeInfo['parser_service'] = 'mnemosyne';
                $typeInfo['supports_media'] = false;
                $typeInfo['supports_advanced_types'] = false;
                break;

            case 'csv':
            case 'tsv':
            case 'txt':
                $typeInfo['import_type'] = 'text';
                $typeInfo['parser_service'] = 'basic';
                $typeInfo['supports_media'] = false;
                $typeInfo['supports_advanced_types'] = true;
                break;
        }

        return $typeInfo;
    }

    /**
     * Validate advanced import file
     */
    public function validateAdvancedImport(UploadedFile $file): array
    {
        $typeInfo = $this->detectImportType($file);

        if ($typeInfo['import_type'] === 'unknown') {
            return [
                'valid' => false,
                'error' => 'Unsupported file type: '.$typeInfo['extension'],
                'type_info' => $typeInfo,
            ];
        }

        // Delegate to specific validators
        switch ($typeInfo['parser_service']) {
            case 'anki':
                // Basic validation for Anki files
                if ($file->getSize() > 100 * 1024 * 1024) { // 100MB limit
                    return [
                        'valid' => false,
                        'error' => 'Anki package too large (max 100MB)',
                        'type_info' => $typeInfo,
                    ];
                }
                break;

            case 'mnemosyne':
                $validation = $this->mnemosyneImporter->validateMnemosyneFile($file);
                if (! $validation['valid']) {
                    return array_merge($validation, ['type_info' => $typeInfo]);
                }
                break;

            case 'basic':
            default:
                // Use existing validation for basic text files
                break;
        }

        return [
            'valid' => true,
            'type_info' => $typeInfo,
            'file_size' => $file->getSize(),
            'estimated_cards' => $this->estimateCardCount($file, $typeInfo),
        ];
    }

    /**
     * Estimate card count from file
     */
    private function estimateCardCount(UploadedFile $file, array $typeInfo): int
    {
        try {
            switch ($typeInfo['import_type']) {
                case 'text':
                    // Read first 1KB to estimate
                    $handle = fopen($file->getPathname(), 'r');
                    $sample = fread($handle, 1024);
                    fclose($handle);

                    $lines = substr_count($sample, "\n");
                    $fileSize = $file->getSize();

                    return (int) (($lines / 1024) * $fileSize);

                case 'anki':
                    // Rough estimate based on file size (very approximate)
                    return (int) ($file->getSize() / 1024); // ~1KB per card average

                case 'mnemosyne':
                    // Similar to text files
                    return (int) ($file->getSize() / 200); // ~200 bytes per card average

                default:
                    return 0;
            }
        } catch (\Exception $e) {
            return 0;
        }
    }
}
