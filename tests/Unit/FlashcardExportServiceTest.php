<?php

namespace Tests\Unit;

use App\Models\Flashcard;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use App\Services\FlashcardExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class FlashcardExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private FlashcardExportService $exportService;

    private User $user;

    private Subject $subject;

    private Unit $unit;

    private Topic $topic;

    private Collection $flashcards;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exportService = new FlashcardExportService;

        // Create test data
        $this->user = User::factory()->create();
        $this->subject = Subject::factory()->create(['user_id' => $this->user->id]);
        $this->unit = Unit::factory()->create(['subject_id' => $this->subject->id]);
        $this->topic = Topic::factory()->create(['unit_id' => $this->unit->id]);

        // Create sample flashcards of different types
        $this->flashcards = collect([
            // Basic card
            Flashcard::factory()->forTopic($this->topic)->create([
                'card_type' => Flashcard::CARD_TYPE_BASIC,
                'question' => 'What is the capital of France?',
                'answer' => 'Paris',
                'hint' => 'It starts with P',
                'tags' => ['geography', 'europe'],
                'difficulty_level' => 'medium',
            ]),

            // Multiple choice card
            Flashcard::factory()->forTopic($this->topic)->create([
                'card_type' => Flashcard::CARD_TYPE_MULTIPLE_CHOICE,
                'question' => 'What is 2 + 2?',
                'answer' => '4',
                'choices' => ['3', '4', '5', '6'],
                'correct_choices' => [1],
                'difficulty_level' => 'easy',
            ]),

            // True/False card
            Flashcard::factory()->forTopic($this->topic)->create([
                'card_type' => Flashcard::CARD_TYPE_TRUE_FALSE,
                'question' => 'The Earth is round.',
                'answer' => 'True',
                'choices' => ['True', 'False'],
                'correct_choices' => [0],
                'difficulty_level' => 'easy',
            ]),

            // Cloze deletion card
            Flashcard::factory()->forTopic($this->topic)->create([
                'card_type' => Flashcard::CARD_TYPE_CLOZE,
                'question' => 'The capital of France is [...]',
                'answer' => 'Paris',
                'cloze_text' => 'The capital of France is {{Paris}}',
                'cloze_answers' => ['Paris'],
                'difficulty_level' => 'medium',
            ]),

            // Typed answer card
            Flashcard::factory()->forTopic($this->topic)->create([
                'card_type' => Flashcard::CARD_TYPE_TYPED_ANSWER,
                'question' => 'Name the first president of the United States.',
                'answer' => 'George Washington',
                'difficulty_level' => 'medium',
            ]),
        ]);
    }

    #[Test]
    public function it_returns_available_export_formats()
    {
        $formats = FlashcardExportService::getExportFormats();

        $this->assertIsArray($formats);
        $this->assertArrayHasKey('anki', $formats);
        $this->assertArrayHasKey('quizlet', $formats);
        $this->assertArrayHasKey('csv', $formats);
        $this->assertArrayHasKey('json', $formats);
        $this->assertArrayHasKey('mnemosyne', $formats);
        $this->assertArrayHasKey('supermemo', $formats);

        $this->assertCount(6, $formats);
    }

    #[Test]
    public function it_fails_export_with_empty_flashcards()
    {
        $result = $this->exportService->exportFlashcards(collect(), 'json');

        $this->assertFalse($result['success']);
        $this->assertEquals('No flashcards provided for export', $result['error']);
    }

    #[Test]
    public function it_fails_export_with_invalid_format()
    {
        $result = $this->exportService->exportFlashcards($this->flashcards, 'invalid_format');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid export format specified', $result['error']);
    }

    #[Test]
    public function it_fails_export_when_exceeding_max_size()
    {
        // Create a large collection that exceeds the limit
        $largeCollection = collect();
        for ($i = 0; $i < FlashcardExportService::MAX_EXPORT_SIZE + 1; $i++) {
            $largeCollection->push($this->flashcards->first());
        }

        $result = $this->exportService->exportFlashcards($largeCollection, 'json');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Export size exceeds maximum limit', $result['error']);
    }

    #[Test]
    public function it_exports_flashcards_as_json()
    {
        $result = $this->exportService->exportFlashcards($this->flashcards, 'json');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertEquals('application/json', $result['mime_type']);

        $data = json_decode($result['content'], true);
        $this->assertArrayHasKey('exported_at', $data);
        $this->assertArrayHasKey('format_version', $data);
        $this->assertArrayHasKey('total_cards', $data);
        $this->assertArrayHasKey('flashcards', $data);
        $this->assertEquals(5, $data['total_cards']);
        $this->assertCount(5, $data['flashcards']);

        // Check that all card types are present
        $cardTypes = array_column($data['flashcards'], 'card_type');
        $this->assertContains('basic', $cardTypes);
        $this->assertContains('multiple_choice', $cardTypes);
        $this->assertContains('true_false', $cardTypes);
        $this->assertContains('cloze', $cardTypes);
        $this->assertContains('typed_answer', $cardTypes);
    }

    #[Test]
    public function it_exports_flashcards_as_json_without_metadata()
    {
        $result = $this->exportService->exportFlashcards(
            $this->flashcards,
            'json',
            ['include_metadata' => false]
        );

        $this->assertTrue($result['success']);

        $data = json_decode($result['content'], true);
        $firstCard = $data['flashcards'][0];

        $this->assertArrayNotHasKey('created_at', $firstCard);
        $this->assertArrayNotHasKey('updated_at', $firstCard);
    }

    #[Test]
    public function it_exports_flashcards_as_csv()
    {
        $result = $this->exportService->exportFlashcards($this->flashcards, 'csv');

        $this->assertTrue($result['success']);
        $this->assertEquals('text/csv', $result['mime_type']);

        $lines = explode("\n", trim($result['content']));
        $this->assertCount(6, $lines); // 5 cards + header

        // Check header
        $header = str_getcsv($lines[0]);
        $this->assertContains('ID', $header);
        $this->assertContains('Card Type', $header);
        $this->assertContains('Question', $header);
        $this->assertContains('Answer', $header);

        // Check first data row
        $firstRow = str_getcsv($lines[1]);
        $this->assertEquals('basic', $firstRow[1]); // Card Type
        $this->assertEquals('What is the capital of France?', $firstRow[2]); // Question
        $this->assertEquals('Paris', $firstRow[3]); // Answer
    }

    #[Test]
    public function it_exports_flashcards_as_quizlet_tsv()
    {
        $result = $this->exportService->exportFlashcards($this->flashcards, 'quizlet');

        $this->assertTrue($result['success']);
        $this->assertEquals('text/tab-separated-values', $result['mime_type']);

        $lines = explode("\n", $result['content']);
        $this->assertCount(5, $lines);

        // Check first line (basic card)
        $parts = explode("\t", $lines[0]);
        $this->assertCount(2, $parts);
        $this->assertEquals('What is the capital of France?', $parts[0]);
        $this->assertEquals('Paris', $parts[1]);

        // Check multiple choice card format
        $mcLine = null;
        foreach ($lines as $line) {
            if (strpos($line, '2 + 2') !== false) {
                $mcLine = $line;
                break;
            }
        }
        $this->assertNotNull($mcLine);
        $parts = explode("\t", $mcLine);
        $this->assertStringContainsString('Options:', $parts[0]);
    }

    #[Test]
    public function it_exports_flashcards_as_mnemosyne_xml()
    {
        $result = $this->exportService->exportFlashcards($this->flashcards, 'mnemosyne');

        $this->assertTrue($result['success']);
        $this->assertEquals('application/xml', $result['mime_type']);

        $xml = $result['content'];
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<mnemosyne', $xml);
        $this->assertStringContainsString('<card>', $xml);
        $this->assertStringContainsString('<Q>', $xml);
        $this->assertStringContainsString('<A>', $xml);
        $this->assertStringContainsString('What is the capital of France?', $xml);
        $this->assertStringContainsString('Paris', $xml);
    }

    #[Test]
    public function it_exports_flashcards_as_supermemo_txt()
    {
        $result = $this->exportService->exportFlashcards($this->flashcards, 'supermemo');

        $this->assertTrue($result['success']);
        $this->assertEquals('text/plain', $result['mime_type']);

        $content = $result['content'];
        $this->assertStringContainsString('Q: What is the capital of France?', $content);
        $this->assertStringContainsString('A: Paris', $content);

        // Check that cards are separated by empty lines
        $lines = explode("\n", $content);
        $qLines = array_filter($lines, fn ($line) => str_starts_with($line, 'Q:'));
        $this->assertCount(5, $qLines);
    }

    #[Test]
    public function it_exports_flashcards_as_anki_package()
    {
        $result = $this->exportService->exportFlashcards(
            $this->flashcards,
            'anki',
            ['deck_name' => 'Test Deck']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('application/zip', $result['mime_type']);
        $this->assertStringEndsWith('.apkg', $result['filename']);

        // Write content to temporary file to verify ZIP structure
        $tempDir = storage_path('app/temp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $tempFile = $tempDir.'/anki_test_'.uniqid().'.zip';
        file_put_contents($tempFile, $result['content']);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tempFile) === true);

        // Check for required files
        $this->assertNotFalse($zip->locateName('collection.anki2'));
        $this->assertNotFalse($zip->locateName('media'));

        $zip->close();
        unlink($tempFile);
    }

    #[Test]
    public function it_validates_export_options_correctly()
    {
        // Valid options
        $errors = $this->exportService->validateExportOptions(['deck_name' => 'Valid Deck'], 'anki');
        $this->assertEmpty($errors);

        // Invalid format
        $errors = $this->exportService->validateExportOptions([], 'invalid');
        $this->assertNotEmpty($errors);
        $this->assertContains('Invalid export format specified', $errors);

        // Empty deck name for Anki
        $errors = $this->exportService->validateExportOptions(['deck_name' => ''], 'anki');
        $this->assertNotEmpty($errors);
        $this->assertContains('Deck name cannot be empty', $errors);

        // Deck name too long
        $errors = $this->exportService->validateExportOptions(['deck_name' => str_repeat('x', 101)], 'anki');
        $this->assertNotEmpty($errors);
        $this->assertContains('Deck name cannot exceed 100 characters', $errors);

        // Invalid metadata option
        $errors = $this->exportService->validateExportOptions(['include_metadata' => 'invalid'], 'json');
        $this->assertNotEmpty($errors);
        $this->assertContains('Include metadata option must be boolean', $errors);
    }

    #[Test]
    public function it_gets_correct_question_text_for_different_card_types()
    {
        $basicCard = $this->flashcards->where('card_type', 'basic')->first();
        $mcCard = $this->flashcards->where('card_type', 'multiple_choice')->first();
        $tfCard = $this->flashcards->where('card_type', 'true_false')->first();
        $clozeCard = $this->flashcards->where('card_type', 'cloze')->first();

        // Basic card
        $questionText = $this->exportService->getQuestionText($basicCard);
        $this->assertEquals('What is the capital of France?', $questionText);

        // Multiple choice card
        $questionText = $this->exportService->getQuestionText($mcCard);
        $this->assertStringContainsString('What is 2 + 2?', $questionText);
        $this->assertStringContainsString('Options:', $questionText);
        $this->assertStringContainsString('A) 3', $questionText);
        $this->assertStringContainsString('B) 4', $questionText);

        // True/False card
        $questionText = $this->exportService->getQuestionText($tfCard);
        $this->assertEquals('The Earth is round.'."\n\n".'(True or False)', $questionText);

        // Cloze card
        $questionText = $this->exportService->getQuestionText($clozeCard);
        $this->assertEquals('The capital of France is [...]', $questionText);
    }

    #[Test]
    public function it_gets_correct_answer_text_for_different_card_types()
    {
        $basicCard = $this->flashcards->where('card_type', 'basic')->first();
        $mcCard = $this->flashcards->where('card_type', 'multiple_choice')->first();
        $clozeCard = $this->flashcards->where('card_type', 'cloze')->first();

        // Basic card
        $answerText = $this->exportService->getAnswerText($basicCard);
        $this->assertEquals('Paris', $answerText);

        // Multiple choice card
        $answerText = $this->exportService->getAnswerText($mcCard);
        $this->assertEquals('B) 4', $answerText);

        // Cloze card
        $answerText = $this->exportService->getAnswerText($clozeCard);
        $this->assertEquals('Paris', $answerText);
    }

    #[Test]
    public function it_generates_valid_filenames()
    {
        $result = $this->exportService->exportFlashcards($this->flashcards, 'json');

        $this->assertTrue($result['success']);
        $this->assertStringEndsWith('.json', $result['filename']);
        $this->assertStringContainsString(date('Y-m-d'), $result['filename']);

        // Check that special characters are removed
        $this->assertDoesNotMatchRegularExpression('/[^A-Za-z0-9\-_.]/', $result['filename']);
    }

    #[Test]
    public function it_handles_cards_with_special_characters()
    {
        $specialCard = Flashcard::factory()->forTopic($this->topic)->create([
            'question' => 'What does "café" mean in English?',
            'answer' => 'Coffee ☕',
            'tags' => ['français', 'café'],
        ]);

        $flashcards = Flashcard::where('id', $specialCard->id)->get();
        $result = $this->exportService->exportFlashcards($flashcards, 'json');

        $this->assertTrue($result['success']);

        $data = json_decode($result['content'], true);
        $card = $data['flashcards'][0];

        $this->assertEquals('What does "café" mean in English?', $card['question']);
        $this->assertEquals('Coffee ☕', $card['answer']);
        $this->assertContains('français', $card['tags']);
        $this->assertContains('café', $card['tags']);
    }

    #[Test]
    public function it_returns_export_progress_status()
    {
        $progress = $this->exportService->getExportProgress('test-export-123');

        $this->assertIsArray($progress);
        $this->assertEquals('test-export-123', $progress['export_id']);
        $this->assertEquals('completed', $progress['status']);
        $this->assertEquals(100, $progress['progress']);
    }
}
