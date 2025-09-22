<?php

namespace Tests\Unit\Services;

use App\Models\Flashcard;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use App\Services\FlashcardImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\FileTestHelper;
use Tests\TestCase;

class FlashcardImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private FlashcardImportService $importService;

    private User $user;

    private Subject $subject;

    private Unit $unit;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the import service with dependencies
        $this->importService = $this->app->make(FlashcardImportService::class);

        // Create test user and unit
        $this->user = User::factory()->create();
        $this->subject = Subject::factory()->for($this->user)->create();
        $this->unit = Unit::factory()->for($this->subject)->create();
        $this->topic = Topic::create([
            'unit_id' => $this->unit->id,
            'title' => 'Test Topic',
            'description' => 'Test topic for import testing',
            'estimated_minutes' => 30,
            'required' => true,
        ]);
    }

    #[Test]
    public function it_detects_basic_card_type()
    {
        $content = "What is 2+2?\t4\tSimple math";
        $result = $this->importService->parseText($content);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['cards']);
        $this->assertEquals('basic', $result['cards'][0]['card_type']);
    }

    #[Test]
    public function it_detects_cloze_card_type_from_question()
    {
        $content = "The {{capital}} of France is Paris.\tCapital city\t";
        $result = $this->importService->parseText($content);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['cards']);
        $this->assertEquals('cloze', $result['cards'][0]['card_type']);
    }

    #[Test]
    public function it_detects_cloze_card_type_with_anki_syntax()
    {
        $content = "The {{c1::mitochondria}} is the {{c2::powerhouse}} of the cell.\tOrganelles\t";
        $result = $this->importService->parseText($content);

        $this->assertTrue($result['success']);
        $this->assertEquals('cloze', $result['cards'][0]['card_type']);
    }

    #[Test]
    public function it_detects_true_false_card_type()
    {
        $content = "The Earth is round.\ttrue\tGeography";
        $result = $this->importService->parseText($content);

        $this->assertTrue($result['success']);
        $this->assertEquals('true_false', $result['cards'][0]['card_type']);
    }

    #[Test]
    public function it_detects_multiple_choice_from_semicolon_separated_answers()
    {
        $content = "Pick the even numbers.\t2;4;6;8\tNumbers\t";
        $result = $this->importService->parseText($content);

        $this->assertTrue($result['success']);
        $this->assertEquals('multiple_choice', $result['cards'][0]['card_type']);
    }

    #[Test]
    public function it_detects_image_occlusion_from_image_url()
    {
        $content = "https://example.com/anatomy.jpg\tHeart location\tAnatomy";
        $result = $this->importService->parseText($content);

        $this->assertTrue($result['success']);
        $this->assertEquals('image_occlusion', $result['cards'][0]['card_type']);
    }

    #[Test]
    public function it_processes_extended_csv_format()
    {
        $content = 'multiple_choice,Pick even numbers,Correct answer,2;3;4;5,0;2,Numbers are fun,math;basic';
        $result = $this->importService->parseText($content);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['cards']);

        $card = $result['cards'][0];
        $this->assertEquals('multiple_choice', $card['card_type']);
        $this->assertEquals(['2', '3', '4', '5'], $card['choices']);
        $this->assertEquals([0, 2], $card['correct_choices']);
        $this->assertEquals(['math', 'basic'], $card['tags']);
    }

    #[Test]
    public function it_processes_multiple_choice_cards_correctly()
    {
        $content = "Pick colors.\tred;blue;green;yellow\t\t";
        $result = $this->importService->parseText($content);

        $this->assertTrue($result['success']);
        $card = $result['cards'][0];

        $this->assertEquals('multiple_choice', $card['card_type']);
        $this->assertEquals(['red', 'blue', 'green', 'yellow'], $card['choices']);
        $this->assertEquals([0], $card['correct_choices']); // First choice marked as correct by default
    }

    #[Test]
    public function it_processes_true_false_cards_correctly()
    {
        $testCases = [
            ['answer' => 'true', 'expected' => 0],
            ['answer' => 'false', 'expected' => 1],
            ['answer' => 'yes', 'expected' => 0],
            ['answer' => 'no', 'expected' => 1],
            ['answer' => 'T', 'expected' => 0],
            ['answer' => 'F', 'expected' => 1],
        ];

        foreach ($testCases as $testCase) {
            $content = "Statement.\t{$testCase['answer']}\t";
            $result = $this->importService->parseText($content);

            $this->assertTrue($result['success']);
            $card = $result['cards'][0];

            $this->assertEquals('true_false', $card['card_type']);
            $this->assertEquals(['True', 'False'], $card['choices']);
            $this->assertEquals([$testCase['expected']], $card['correct_choices']);
        }
    }

    #[Test]
    public function it_processes_cloze_cards_correctly()
    {
        $content = "The {{capital}} of {{country}} is {{Paris}}.\tGeography fact\t";
        $result = $this->importService->parseText($content);

        $this->assertTrue($result['success']);
        $card = $result['cards'][0];

        $this->assertEquals('cloze', $card['card_type']);
        $this->assertEquals('The {{capital}} of {{country}} is {{Paris}}.', $card['cloze_text']);
        $this->assertEquals(['capital', 'country', 'Paris'], $card['cloze_answers']);
        $this->assertStringContainsString('[...]', $card['question']);
    }

    #[Test]
    public function it_imports_cards_successfully()
    {
        $cards = [
            [
                'card_type' => 'basic',
                'question' => 'What is 2+2?',
                'answer' => '4',
                'hint' => 'Simple math',
                'tags' => ['math'],
                'difficulty_level' => 'easy',
            ],
            [
                'card_type' => 'multiple_choice',
                'question' => 'Pick even numbers',
                'answer' => '2, 4',
                'choices' => ['2', '3', '4', '5'],
                'correct_choices' => [0, 2],
                'tags' => ['math'],
                'difficulty_level' => 'medium',
            ],
        ];

        $result = $this->importService->importCards($cards, $this->unit->id, $this->user->id, 'test_import');

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['imported']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(2, Flashcard::all());

        // Verify first card
        $basicCard = Flashcard::where('card_type', 'basic')->first();
        $this->assertEquals('What is 2+2?', $basicCard->question);
        $this->assertEquals('4', $basicCard->answer);
        $this->assertEquals('test_import', $basicCard->import_source);
        $this->assertEquals($this->topic->id, $basicCard->topic_id);

        // Verify second card
        $mcCard = Flashcard::where('card_type', 'multiple_choice')->first();
        $this->assertEquals(['2', '3', '4', '5'], $mcCard->choices);
        $this->assertEquals([0, 2], $mcCard->correct_choices);
    }

    #[Test]
    public function it_validates_import_data()
    {
        $invalidCards = [
            ['question' => '', 'answer' => 'No question'],
            ['question' => 'No answer', 'answer' => ''],
        ];

        $errors = $this->importService->validateImport($invalidCards);

        $this->assertCount(2, $errors);
        $this->assertStringContainsString('Question', $errors[0]);
        $this->assertStringContainsString('Answer', $errors[1]);
    }

    #[Test]
    public function it_handles_mixed_card_types_import()
    {
        $content = "What is 2+2?\t4\tMath\n".
                   "The Earth is round.\ttrue\tGeography\n".
                   "The {{capital}} of France is Paris.\tcapital\tGeography";

        $result = $this->importService->parseText($content);

        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['cards']);

        $cardTypes = array_column($result['cards'], 'card_type');
        $this->assertEquals(['basic', 'true_false', 'cloze'], $cardTypes);
    }

    #[Test]
    public function it_detects_csv_delimiter_correctly()
    {
        $csvContent = "What is 2+2?,4,Math\nWhat is 3+3?,6,More math";
        $result = $this->importService->parseText($csvContent);

        $this->assertTrue($result['success']);
        $this->assertEquals(',', $result['delimiter']);
        $this->assertCount(2, $result['cards']);
    }

    #[Test]
    public function it_detects_tab_delimiter_correctly()
    {
        $tsvContent = "What is 2+2?\t4\tMath\nWhat is 3+3?\t6\tMore math";
        $result = $this->importService->parseText($tsvContent);

        $this->assertTrue($result['success']);
        $this->assertEquals("\t", $result['delimiter']);
        $this->assertCount(2, $result['cards']);
    }

    #[Test]
    public function it_extracts_tags_from_hashtags()
    {
        $content = "What is 2+2? #math #basic\t4\tSimple";
        $result = $this->importService->parseText($content);

        $this->assertTrue($result['success']);
        $card = $result['cards'][0];
        $this->assertContains('math', $card['tags']);
        $this->assertContains('basic', $card['tags']);
    }

    #[Test]
    public function it_rejects_oversized_imports()
    {
        $largeContent = str_repeat("Question?\tAnswer\n", FlashcardImportService::MAX_IMPORT_SIZE + 1);
        $result = $this->importService->parseText($largeContent);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('maximum allowed', $result['error']);
    }

    #[Test]
    public function it_handles_empty_content()
    {
        $result = $this->importService->parseText('');

        $this->assertFalse($result['success']);
        $this->assertEquals('No content provided', $result['error']);
    }

    #[Test]
    public function it_handles_malformed_lines()
    {
        $content = "Valid question\tValid answer\nInvalid line without separator\nAnother question\tAnother answer";
        $result = $this->importService->parseText($content);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['cards']); // Only valid lines processed
        $this->assertCount(1, $result['errors']); // One error for malformed line
    }

    #[Test]
    public function it_processes_file_upload()
    {
        $content = "What is 2+2?\t4\nWhat is 3+3?\t6";
        $file = FileTestHelper::createUploadedFileWithContent('test.tsv', $content, 'text/tab-separated-values');

        $result = $this->importService->parseFile($file);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['cards']);
        $this->assertEquals("\t", $result['delimiter']);

        // Clean up
        unlink($file->getPathname());
    }

    #[Test]
    public function it_handles_empty_file()
    {
        $file = FileTestHelper::createUploadedFileWithContent('empty.csv', '', 'text/csv');

        $result = $this->importService->parseFile($file);

        $this->assertFalse($result['success']);
        $this->assertEquals('File is empty or could not be read', $result['error']);

        // Clean up
        unlink($file->getPathname());
    }
}
