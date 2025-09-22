<?php

namespace App\Services;

use App\Models\Flashcard;
use DOMDocument;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use League\Csv\Writer;
use SQLite3;
use ZipArchive;

class FlashcardExportService
{
    /**
     * Available export formats
     */
    public const EXPORT_FORMATS = [
        'anki' => 'Anki Package (.apkg)',
        'quizlet' => 'Quizlet TSV (.tsv)',
        'csv' => 'Extended CSV (.csv)',
        'json' => 'JSON Export (.json)',
        'mnemosyne' => 'Mnemosyne XML (.xml)',
        'supermemo' => 'SuperMemo Q&A (.txt)',
    ];

    /**
     * Maximum number of flashcards allowed in single export
     */
    public const MAX_EXPORT_SIZE = 5000;

    /**
     * Export flashcards in specified format
     */
    public function exportFlashcards(Collection|EloquentCollection $flashcards, string $format, array $options = []): array
    {
        try {
            if ($flashcards->isEmpty()) {
                return [
                    'success' => false,
                    'error' => 'No flashcards provided for export',
                    'content' => null,
                    'filename' => null,
                ];
            }

            if ($flashcards->count() > self::MAX_EXPORT_SIZE) {
                return [
                    'success' => false,
                    'error' => 'Export size exceeds maximum limit of '.self::MAX_EXPORT_SIZE.' cards',
                    'content' => null,
                    'filename' => null,
                ];
            }

            if (! array_key_exists($format, self::EXPORT_FORMATS)) {
                return [
                    'success' => false,
                    'error' => 'Invalid export format specified',
                    'content' => null,
                    'filename' => null,
                ];
            }

            switch ($format) {
                case 'anki':
                    return $this->exportAnki($flashcards, $options);
                case 'quizlet':
                    return $this->exportQuizlet($flashcards, $options);
                case 'csv':
                    return $this->exportExtendedCsv($flashcards, $options);
                case 'json':
                    return $this->exportJson($flashcards, $options);
                case 'mnemosyne':
                    return $this->exportMnemosyne($flashcards, $options);
                case 'supermemo':
                    return $this->exportSuperMemo($flashcards, $options);
                default:
                    return [
                        'success' => false,
                        'error' => 'Export format not implemented',
                        'content' => null,
                        'filename' => null,
                    ];
            }
        } catch (\Exception $e) {
            Log::error('Flashcard export error', [
                'format' => $format,
                'count' => $flashcards->count(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Export failed: '.$e->getMessage(),
                'content' => null,
                'filename' => null,
            ];
        }
    }

    /**
     * Get available export formats
     */
    public static function getExportFormats(): array
    {
        return self::EXPORT_FORMATS;
    }

    /**
     * Export flashcards as Anki Package (.apkg)
     *
     * @param  Collection  $flashcards
     */
    private function exportAnki(Collection|EloquentCollection $flashcards, array $options = []): array
    {
        $deckName = $options['deck_name'] ?? 'Exported Flashcards';
        $timestamp = time();
        $tempDir = storage_path('app/temp/anki_'.$timestamp);

        try {
            // Create temporary directory
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Create Anki database
            $dbPath = $tempDir.'/collection.anki2';
            $this->createAnkiDatabase($dbPath, $deckName, $flashcards);

            // Create media directory (empty for now)
            $mediaDir = $tempDir.'/media';
            if (! is_dir($mediaDir)) {
                mkdir($mediaDir, 0755, true);
            }

            // Create media metadata file
            file_put_contents($mediaDir.'/_media', '{}');

            // Create ZIP file
            $zipPath = $tempDir.'/export.apkg';
            $zip = new ZipArchive;

            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                throw new \Exception('Cannot create Anki package file');
            }

            $zip->addFile($dbPath, 'collection.anki2');
            $zip->addFile($mediaDir.'/_media', 'media');
            $zip->close();

            $content = file_get_contents($zipPath);
            $filename = $this->generateFilename($deckName, 'apkg');

            // Clean up temporary files
            $this->cleanupTempDirectory($tempDir);

            return [
                'success' => true,
                'content' => $content,
                'filename' => $filename,
                'mime_type' => 'application/zip',
            ];

        } catch (\Exception $e) {
            // Clean up on error
            if (is_dir($tempDir)) {
                $this->cleanupTempDirectory($tempDir);
            }
            throw $e;
        }
    }

    /**
     * Export flashcards as Quizlet TSV (.tsv)
     *
     * @param  Collection  $flashcards
     */
    private function exportQuizlet(Collection|EloquentCollection $flashcards, array $options = []): array
    {
        $output = '';

        foreach ($flashcards as $flashcard) {
            $question = $this->getQuestionText($flashcard);
            $answer = $this->getAnswerText($flashcard);

            // Escape tabs and newlines
            $question = str_replace(["\t", "\n", "\r"], [' ', ' ', ''], $question);
            $answer = str_replace(["\t", "\n", "\r"], [' ', ' ', ''], $answer);

            $output .= $question."\t".$answer."\n";
        }

        return [
            'success' => true,
            'content' => trim($output),
            'filename' => $this->generateFilename('quizlet-export', 'tsv'),
            'mime_type' => 'text/tab-separated-values',
        ];
    }

    /**
     * Export flashcards as Extended CSV (.csv)
     *
     * @param  Collection  $flashcards
     */
    private function exportExtendedCsv(Collection|EloquentCollection $flashcards, array $options = []): array
    {
        $csv = Writer::createFromString();

        // Add header row
        $csv->insertOne([
            'ID',
            'Card Type',
            'Question',
            'Answer',
            'Hint',
            'Choices',
            'Correct Choices',
            'Cloze Text',
            'Cloze Answers',
            'Question Image URL',
            'Answer Image URL',
            'Occlusion Data',
            'Difficulty Level',
            'Tags',
            'Created At',
            'Updated At',
        ]);

        foreach ($flashcards as $flashcard) {
            $csv->insertOne([
                $flashcard->id,
                $flashcard->card_type,
                $flashcard->question,
                $flashcard->answer,
                $flashcard->hint,
                is_array($flashcard->choices) ? implode(';', $flashcard->choices) : '',
                is_array($flashcard->correct_choices) ? implode(';', $flashcard->correct_choices) : '',
                $flashcard->cloze_text,
                is_array($flashcard->cloze_answers) ? implode(';', $flashcard->cloze_answers) : '',
                $flashcard->question_image_url,
                $flashcard->answer_image_url,
                is_array($flashcard->occlusion_data) ? json_encode($flashcard->occlusion_data) : '',
                $flashcard->difficulty_level,
                is_array($flashcard->tags) ? implode(';', $flashcard->tags) : '',
                $flashcard->created_at?->toIso8601String(),
                $flashcard->updated_at?->toIso8601String(),
            ]);
        }

        return [
            'success' => true,
            'content' => $csv->toString(),
            'filename' => $this->generateFilename('extended-export', 'csv'),
            'mime_type' => 'text/csv',
        ];
    }

    /**
     * Export flashcards as JSON (.json)
     *
     * @param  Collection  $flashcards
     */
    private function exportJson(Collection|EloquentCollection $flashcards, array $options = []): array
    {
        $includeMetadata = $options['include_metadata'] ?? true;

        $data = [
            'exported_at' => now()->toIso8601String(),
            'format_version' => '1.0',
            'total_cards' => $flashcards->count(),
            'flashcards' => [],
        ];

        if ($includeMetadata && $flashcards->isNotEmpty()) {
            $firstCard = $flashcards->first();

            // Ensure the topic and unit are loaded
            $firstCard->load('topic.unit.subject');

            $unit = $firstCard->unit;  // Access as property
            $data['unit'] = [
                'id' => $unit->id ?? null,
                'name' => $unit->name ?? null,
                'subject_id' => $unit->subject_id ?? null,
                'subject_name' => $unit->subject->name ?? null,
            ];
        }

        foreach ($flashcards as $flashcard) {
            $cardData = [
                'id' => $flashcard->id,
                'card_type' => $flashcard->card_type,
                'question' => $flashcard->question,
                'answer' => $flashcard->answer,
                'difficulty_level' => $flashcard->difficulty_level,
                'tags' => $flashcard->tags ?? [],
            ];

            // Include card-type specific data
            if ($flashcard->hint) {
                $cardData['hint'] = $flashcard->hint;
            }

            if ($flashcard->requiresMultipleChoiceData()) {
                $cardData['choices'] = $flashcard->choices ?? [];
                $cardData['correct_choices'] = $flashcard->correct_choices ?? [];
            }

            if ($flashcard->requiresClozeData()) {
                $cardData['cloze_text'] = $flashcard->cloze_text;
                $cardData['cloze_answers'] = $flashcard->cloze_answers ?? [];
            }

            if ($flashcard->requiresImageData()) {
                $cardData['question_image_url'] = $flashcard->question_image_url;
                $cardData['answer_image_url'] = $flashcard->answer_image_url;
                $cardData['occlusion_data'] = $flashcard->occlusion_data ?? [];
            }

            if ($includeMetadata) {
                $cardData['created_at'] = $flashcard->created_at?->toIso8601String();
                $cardData['updated_at'] = $flashcard->updated_at?->toIso8601String();
            }

            $data['flashcards'][] = $cardData;
        }

        return [
            'success' => true,
            'content' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'filename' => $this->generateFilename('backup-export', 'json'),
            'mime_type' => 'application/json',
        ];
    }

    /**
     * Export flashcards as Mnemosyne XML (.xml)
     *
     * @param  Collection  $flashcards
     */
    private function exportMnemosyne(Collection|EloquentCollection $flashcards, array $options = []): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Root element
        $root = $dom->createElement('mnemosyne');
        $root->setAttribute('core_version', '1');
        $root->setAttribute('database_version', '1');
        $dom->appendChild($root);

        // Add cards
        foreach ($flashcards as $flashcard) {
            $card = $dom->createElement('card');

            // Card ID
            $id = $dom->createElement('id', $flashcard->id);
            $card->appendChild($id);

            // Question
            $question = $this->getQuestionText($flashcard);
            $questionElem = $dom->createElement('Q');
            $questionElem->appendChild($dom->createCDATASection($question));
            $card->appendChild($questionElem);

            // Answer
            $answer = $this->getAnswerText($flashcard);
            $answerElem = $dom->createElement('A');
            $answerElem->appendChild($dom->createCDATASection($answer));
            $card->appendChild($answerElem);

            // Tags
            if (! empty($flashcard->tags)) {
                $tags = $dom->createElement('tags', implode(', ', $flashcard->tags));
                $card->appendChild($tags);
            }

            // Grade (default to 0 for new cards)
            $grade = $dom->createElement('grade', '0');
            $card->appendChild($grade);

            // Easiness factor
            $easiness = $dom->createElement('easiness', '2.5');
            $card->appendChild($easiness);

            // Acquisition date
            $acqDate = $dom->createElement('acq_reps', '0');
            $card->appendChild($acqDate);

            $root->appendChild($card);
        }

        return [
            'success' => true,
            'content' => $dom->saveXML(),
            'filename' => $this->generateFilename('mnemosyne-export', 'xml'),
            'mime_type' => 'application/xml',
        ];
    }

    /**
     * Export flashcards as SuperMemo Q&A (.txt)
     *
     * @param  Collection  $flashcards
     */
    private function exportSuperMemo(Collection|EloquentCollection $flashcards, array $options = []): array
    {
        $output = '';
        $cardNumber = 1;

        foreach ($flashcards as $flashcard) {
            $question = $this->getQuestionText($flashcard);
            $answer = $this->getAnswerText($flashcard);

            $output .= "Q: {$question}\n";
            $output .= "A: {$answer}\n";

            if ($cardNumber < $flashcards->count()) {
                $output .= "\n";
            }

            $cardNumber++;
        }

        return [
            'success' => true,
            'content' => $output,
            'filename' => $this->generateFilename('supermemo-export', 'txt'),
            'mime_type' => 'text/plain',
        ];
    }

    /**
     * Create Anki database with flashcards
     *
     * @param  Collection  $flashcards
     *
     * @throws \Exception
     */
    private function createAnkiDatabase(string $dbPath, string $deckName, Collection|EloquentCollection $flashcards): void
    {
        $db = new SQLite3($dbPath);

        // Create Anki database schema (simplified)
        $db->exec('
            CREATE TABLE col (
                id INTEGER PRIMARY KEY,
                crt INTEGER NOT NULL,
                mod INTEGER NOT NULL,
                scm INTEGER NOT NULL,
                ver INTEGER NOT NULL,
                dty INTEGER NOT NULL,
                usn INTEGER NOT NULL,
                ls INTEGER NOT NULL,
                conf TEXT NOT NULL,
                models TEXT NOT NULL,
                decks TEXT NOT NULL,
                dconf TEXT NOT NULL,
                tags TEXT NOT NULL
            )
        ');

        $db->exec('
            CREATE TABLE notes (
                id INTEGER PRIMARY KEY,
                guid TEXT NOT NULL,
                mid INTEGER NOT NULL,
                mod INTEGER NOT NULL,
                usn INTEGER NOT NULL,
                tags TEXT NOT NULL,
                flds TEXT NOT NULL,
                sfld TEXT NOT NULL,
                csum INTEGER NOT NULL,
                flags INTEGER NOT NULL,
                data TEXT NOT NULL
            )
        ');

        $db->exec('
            CREATE TABLE cards (
                id INTEGER PRIMARY KEY,
                nid INTEGER NOT NULL,
                did INTEGER NOT NULL,
                ord INTEGER NOT NULL,
                mod INTEGER NOT NULL,
                usn INTEGER NOT NULL,
                type INTEGER NOT NULL,
                queue INTEGER NOT NULL,
                due INTEGER NOT NULL,
                ivl INTEGER NOT NULL,
                factor INTEGER NOT NULL,
                reps INTEGER NOT NULL,
                lapses INTEGER NOT NULL,
                left INTEGER NOT NULL,
                odue INTEGER NOT NULL,
                odid INTEGER NOT NULL,
                flags INTEGER NOT NULL,
                data TEXT NOT NULL
            )
        ');

        $timestamp = time();
        $deckId = 1;
        $modelId = 1;

        // Basic configuration
        $config = json_encode([
            'nextPos' => 1,
            'estTimes' => true,
            'activeDecks' => [$deckId],
            'sortType' => 'noteFld',
            'timeLim' => 0,
            'sortBackwards' => false,
            'addToCur' => true,
            'curDeck' => $deckId,
            'newBury' => true,
            'newSpread' => 0,
            'dueCounts' => true,
            'curModel' => $modelId,
            'collapseTime' => 1200,
        ]);

        // Model (note type) configuration
        $models = json_encode([
            $modelId => [
                'id' => $modelId,
                'name' => 'Basic',
                'type' => 0,
                'mod' => $timestamp,
                'usn' => 0,
                'sortf' => 0,
                'did' => $deckId,
                'tmpls' => [
                    [
                        'name' => 'Card 1',
                        'ord' => 0,
                        'qfmt' => '{{Front}}',
                        'afmt' => '{{FrontSide}}<hr id="answer">{{Back}}',
                        'did' => null,
                        'bqfmt' => '',
                        'bafmt' => '',
                    ],
                ],
                'flds' => [
                    ['name' => 'Front', 'ord' => 0, 'sticky' => false, 'rtl' => false, 'font' => 'Arial', 'size' => 20],
                    ['name' => 'Back', 'ord' => 1, 'sticky' => false, 'rtl' => false, 'font' => 'Arial', 'size' => 20],
                ],
                'css' => '.card { font-family: arial; font-size: 20px; text-align: center; color: black; background-color: white; }',
            ],
        ]);

        // Deck configuration
        $decks = json_encode([
            $deckId => [
                'id' => $deckId,
                'name' => $deckName,
                'extendRev' => 50,
                'usn' => 0,
                'collapsed' => false,
                'newToday' => [0, 0],
                'revToday' => [0, 0],
                'lrnToday' => [0, 0],
                'timeToday' => [0, 0],
                'mod' => $timestamp,
                'desc' => '',
                'dyn' => 0,
            ],
        ]);

        // Insert collection data
        $stmt = $db->prepare('
            INSERT INTO col (id, crt, mod, scm, ver, dty, usn, ls, conf, models, decks, dconf, tags)
            VALUES (1, :crt, :mod, :scm, 11, 0, 0, :ls, :conf, :models, :decks, :dconf, :tags)
        ');
        $stmt->bindValue(':crt', $timestamp, SQLITE3_INTEGER);
        $stmt->bindValue(':mod', $timestamp, SQLITE3_INTEGER);
        $stmt->bindValue(':scm', $timestamp, SQLITE3_INTEGER);
        $stmt->bindValue(':ls', $timestamp, SQLITE3_INTEGER);
        $stmt->bindValue(':conf', $config, SQLITE3_TEXT);
        $stmt->bindValue(':models', $models, SQLITE3_TEXT);
        $stmt->bindValue(':decks', $decks, SQLITE3_TEXT);
        $stmt->bindValue(':dconf', '{}', SQLITE3_TEXT);
        $stmt->bindValue(':tags', '{}', SQLITE3_TEXT);
        $stmt->execute();

        // Insert flashcards
        $noteStmt = $db->prepare('
            INSERT INTO notes (id, guid, mid, mod, usn, tags, flds, sfld, csum, flags, data)
            VALUES (:id, :guid, :mid, :mod, 0, :tags, :flds, :sfld, 0, 0, "")
        ');

        $cardStmt = $db->prepare('
            INSERT INTO cards (id, nid, did, ord, mod, usn, type, queue, due, ivl, factor, reps, lapses, left, odue, odid, flags, data)
            VALUES (:id, :nid, :did, 0, :mod, 0, 0, 0, :due, 0, 2500, 0, 0, 0, 0, 0, 0, "")
        ');

        foreach ($flashcards as $index => $flashcard) {
            $noteId = $index + 1;
            $cardId = $index + 1;

            $question = $this->getQuestionText($flashcard);
            $answer = $this->getAnswerText($flashcard);

            $fields = $question."\x1f".$answer;
            $sortField = $question;
            $tags = ! empty($flashcard->tags) ? ' '.implode(' ', $flashcard->tags).' ' : '';

            $guid = substr(md5($question.$answer.$timestamp), 0, 11);

            // Insert note
            $noteStmt->bindValue(':id', $noteId, SQLITE3_INTEGER);
            $noteStmt->bindValue(':guid', $guid, SQLITE3_TEXT);
            $noteStmt->bindValue(':mid', $modelId, SQLITE3_INTEGER);
            $noteStmt->bindValue(':mod', $timestamp, SQLITE3_INTEGER);
            $noteStmt->bindValue(':tags', $tags, SQLITE3_TEXT);
            $noteStmt->bindValue(':flds', $fields, SQLITE3_TEXT);
            $noteStmt->bindValue(':sfld', $sortField, SQLITE3_TEXT);
            $noteStmt->execute();

            // Insert card
            $cardStmt->bindValue(':id', $cardId, SQLITE3_INTEGER);
            $cardStmt->bindValue(':nid', $noteId, SQLITE3_INTEGER);
            $cardStmt->bindValue(':did', $deckId, SQLITE3_INTEGER);
            $cardStmt->bindValue(':mod', $timestamp, SQLITE3_INTEGER);
            $cardStmt->bindValue(':due', $noteId, SQLITE3_INTEGER);
            $cardStmt->execute();
        }

        $db->close();
    }

    /**
     * Get question text for any card type
     */
    public function getQuestionText(Flashcard $flashcard): string
    {
        switch ($flashcard->card_type) {
            case Flashcard::CARD_TYPE_MULTIPLE_CHOICE:
                $question = $flashcard->question;
                if (! empty($flashcard->choices)) {
                    $question .= "\n\nOptions:\n";
                    foreach ($flashcard->choices as $index => $choice) {
                        $letter = chr(65 + $index); // A, B, C, D...
                        $question .= "{$letter}) {$choice}\n";
                    }
                }

                return $question;

            case Flashcard::CARD_TYPE_TRUE_FALSE:
                return $flashcard->question."\n\n(True or False)";

            case Flashcard::CARD_TYPE_CLOZE:
                return $flashcard->cloze_text ?
                    preg_replace('/\{\{([^}]*)\}\}/', '[...]', $flashcard->cloze_text) :
                    $flashcard->question;

            default:
                return $flashcard->question;
        }
    }

    /**
     * Get answer text for any card type
     */
    public function getAnswerText(Flashcard $flashcard): string
    {
        switch ($flashcard->card_type) {
            case Flashcard::CARD_TYPE_MULTIPLE_CHOICE:
                if (! empty($flashcard->correct_choices) && ! empty($flashcard->choices)) {
                    $correctAnswers = [];
                    foreach ($flashcard->correct_choices as $index) {
                        if (isset($flashcard->choices[$index])) {
                            $letter = chr(65 + $index);
                            $correctAnswers[] = "{$letter}) {$flashcard->choices[$index]}";
                        }
                    }

                    return implode(', ', $correctAnswers);
                }

                return $flashcard->answer;

            case Flashcard::CARD_TYPE_CLOZE:
                return ! empty($flashcard->cloze_answers) ?
                    implode(', ', $flashcard->cloze_answers) :
                    $flashcard->answer;

            default:
                return $flashcard->answer;
        }
    }

    /**
     * Generate filename for export
     */
    private function generateFilename(string $basename, string $extension): string
    {
        $basename = preg_replace('/[^A-Za-z0-9\-_]/', '-', $basename);
        $basename = preg_replace('/-+/', '-', $basename);
        $basename = trim($basename, '-');

        if (empty($basename)) {
            $basename = 'flashcards-export';
        }

        return $basename.'-'.date('Y-m-d').'.'.$extension;
    }

    /**
     * Clean up temporary directory
     */
    private function cleanupTempDirectory(string $dir): void
    {
        try {
            if (is_dir($dir)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($files as $fileinfo) {
                    if ($fileinfo->isDir()) {
                        rmdir($fileinfo->getRealPath());
                    } else {
                        unlink($fileinfo->getRealPath());
                    }
                }
                rmdir($dir);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to cleanup temporary directory: '.$e->getMessage());
        }
    }

    /**
     * Validate export options
     */
    public function validateExportOptions(array $options, string $format): array
    {
        $errors = [];

        if (! array_key_exists($format, self::EXPORT_FORMATS)) {
            $errors[] = 'Invalid export format specified';
        }

        // Format-specific validation
        switch ($format) {
            case 'anki':
                if (! isset($options['deck_name']) || empty(trim($options['deck_name']))) {
                    $errors[] = 'Deck name cannot be empty';
                } elseif (strlen($options['deck_name']) > 100) {
                    $errors[] = 'Deck name cannot exceed 100 characters';
                }
                break;

            case 'json':
                if (isset($options['include_metadata'])) {
                    if (! is_bool($options['include_metadata'])) {
                        $errors[] = 'Include metadata option must be boolean';
                    }
                }
                break;
        }

        return $errors;
    }

    /**
     * Get export progress for large exports
     */
    public function getExportProgress(string $exportId): array
    {
        // This would be implemented with job queues for large exports
        // For now, return simple progress tracking
        return [
            'export_id' => $exportId,
            'status' => 'completed',
            'progress' => 100,
            'message' => 'Export completed successfully',
        ];
    }

    /**
     * Generate preview data for the export
     */
    public function generatePreviewData(Collection|EloquentCollection $flashcards, string $format): array
    {
        $sampleSize = min($flashcards->count(), 3);
        $samples = $flashcards->take($sampleSize);
        $previewData = [
            'total_count' => $flashcards->count(),
            'sample_count' => $sampleSize,
            'samples' => [],
        ];

        switch ($format) {
            case 'json':
                $previewData['description'] = 'JSON format includes all flashcard data with metadata and relationships.';
                $previewData['samples'] = [
                    [
                        'structure' => 'Array of flashcard objects',
                        'features' => 'Full metadata, topics, creation dates, statistics',
                    ],
                ];
                break;

            case 'anki':
                $previewData['description'] = 'Anki package (.apkg) format for importing into Anki spaced repetition software.';
                foreach ($samples as $flashcard) {
                    $previewData['samples'][] = [
                        'question' => substr($flashcard->question, 0, 100).(strlen($flashcard->question) > 100 ? '...' : ''),
                        'answer' => substr($flashcard->answer, 0, 100).(strlen($flashcard->answer) > 100 ? '...' : ''),
                        'type' => $flashcard->card_type ?? 'basic',
                    ];
                }
                break;

            case 'csv':
                $previewData['description'] = 'Extended CSV format with all flashcard fields and metadata.';
                $previewData['samples'] = [
                    'CSV Headers: Question, Answer, Type, Difficulty, Topic, Tags, Hint, Created At',
                ];
                break;

            case 'quizlet':
                $previewData['description'] = 'Tab-separated values format compatible with Quizlet import.';
                foreach ($samples as $flashcard) {
                    $previewData['samples'][] = [
                        'example' => substr($flashcard->question, 0, 50)."\t".substr($flashcard->answer, 0, 50),
                    ];
                }
                break;

            case 'mnemosyne':
                $previewData['description'] = 'XML format for Mnemosyne spaced repetition software.';
                $previewData['samples'] = [
                    [
                        'xml_format' => '&lt;item&gt;&lt;Q&gt;Question&lt;/Q&gt;&lt;A&gt;Answer&lt;/A&gt;&lt;/item&gt;',
                    ],
                ];
                break;

            case 'supermemo':
                $previewData['description'] = 'Plain text Q&A format for SuperMemo software.';
                foreach ($samples as $flashcard) {
                    $previewData['samples'][] = [
                        'example' => 'Q: '.substr($flashcard->question, 0, 50)."\nA: ".substr($flashcard->answer, 0, 50),
                    ];
                }
                break;

            default:
                $previewData['description'] = 'Export format preview';
                break;
        }

        return $previewData;
    }
}
