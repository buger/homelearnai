<?php

namespace Tests\Unit;

use App\Services\FlashcardImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\FileTestHelper;
use Tests\TestCase;

class FlashcardImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private FlashcardImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FlashcardImportService::class);
        Storage::fake('local');
    }

    #[Test]
    public function test_parses_quizlet_tab_format()
    {
        $content = "What is the capital of France?\tParis\nWhat is 2+2?\t4\tBasic math";

        $result = $this->service->parseText($content);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['cards']);
        $this->assertEquals('What is the capital of France?', $result['cards'][0]['question']);
        $this->assertEquals('Paris', $result['cards'][0]['answer']);
        $this->assertEquals('What is 2+2?', $result['cards'][1]['question']);
        $this->assertEquals('4', $result['cards'][1]['answer']);
        $this->assertEquals('Basic math', $result['cards'][1]['hint']);
    }

    #[Test]
    public function test_parses_csv_format()
    {
        $content = "What is the capital of France?,Paris\n\"What is 2+2?\",4,\"Basic math\"";

        $result = $this->service->parseText($content);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['cards']);
        $this->assertEquals('What is the capital of France?', $result['cards'][0]['question']);
        $this->assertEquals('Paris', $result['cards'][0]['answer']);
        $this->assertEquals('What is 2+2?', $result['cards'][1]['question']);
        $this->assertEquals('4', $result['cards'][1]['answer']);
        $this->assertEquals('Basic math', $result['cards'][1]['hint']);
    }

    #[Test]
    public function test_parses_dash_format()
    {
        $content = "What is the capital of France? - Paris\nWhat is 2+2? - 4 - Basic math";

        $result = $this->service->parseText($content);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['cards']);
        $this->assertEquals('What is the capital of France?', $result['cards'][0]['question']);
        $this->assertEquals('Paris', $result['cards'][0]['answer']);
        $this->assertEquals('What is 2+2?', $result['cards'][1]['question']);
        $this->assertEquals('4', $result['cards'][1]['answer']);
        $this->assertEquals('Basic math', $result['cards'][1]['hint']);
    }

    #[Test]
    public function test_auto_detects_delimiter()
    {
        // Tab-separated should be detected
        $tabContent = "Question\tAnswer";
        $tabResult = $this->service->parseText($tabContent);
        $this->assertTrue($tabResult['success']);

        // Comma-separated should be detected
        $csvContent = 'Question,Answer';
        $csvResult = $this->service->parseText($csvContent);
        $this->assertTrue($csvResult['success']);

        // Dash-separated should be detected
        $dashContent = 'Question - Answer';
        $dashResult = $this->service->parseText($dashContent);
        $this->assertTrue($dashResult['success']);
    }

    #[Test]
    public function test_validates_required_fields()
    {
        $cards = [
            ['question' => '', 'answer' => 'Answer'],
            ['question' => 'Question', 'answer' => ''],
            ['question' => 'Valid Question', 'answer' => 'Valid Answer'],
        ];

        $errors = $this->service->validateImport($cards);

        $this->assertCount(2, $errors);
        $this->assertStringContainsString('Row 1:', $errors[0]);
        $this->assertStringContainsString('Row 2:', $errors[1]);
    }

    #[Test]
    public function test_handles_empty_content()
    {
        $result = $this->service->parseText('');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No content provided', $result['error']);
    }

    #[Test]
    public function test_handles_unsupported_delimiter()
    {
        $content = 'Question|Answer'; // Pipe is not primary supported delimiter

        $result = $this->service->parseText($content);

        // Should still work as pipe is in the delimiter list
        $this->assertTrue($result['success']);
    }

    #[Test]
    public function test_enforces_max_import_size()
    {
        // Create content with more than max allowed cards
        $lines = [];
        for ($i = 1; $i <= FlashcardImportService::MAX_IMPORT_SIZE + 1; $i++) {
            $lines[] = "Question {$i}\tAnswer {$i}";
        }
        $content = implode("\n", $lines);

        $result = $this->service->parseText($content);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('maximum allowed', $result['error']);
    }

    #[Test]
    public function test_parses_file_upload()
    {
        $content = "What is the capital of France?\tParis\nWhat is 2+2?\t4";
        $file = FileTestHelper::createUploadedFileWithContent('flashcards.csv', $content, 'text/csv');

        $result = $this->service->parseFile($file);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['cards']);

        // Clean up
        unlink($file->getPathname());
    }

    #[Test]
    public function test_extracts_tags_from_hashtags()
    {
        $content = "What is the capital of France? #geography\tParis #cities";

        $result = $this->service->parseText($content);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['cards']);
        $this->assertContains('geography', $result['cards'][0]['tags']);
        $this->assertContains('cities', $result['cards'][0]['tags']);
    }

    #[Test]
    public function test_normalizes_line_endings()
    {
        $contentWithCrLf = "Question 1\tAnswer 1\r\nQuestion 2\tAnswer 2";
        $contentWithCr = "Question 1\tAnswer 1\rQuestion 2\tAnswer 2";

        $resultCrLf = $this->service->parseText($contentWithCrLf);
        $resultCr = $this->service->parseText($contentWithCr);

        $this->assertTrue($resultCrLf['success']);
        $this->assertTrue($resultCr['success']);
        $this->assertCount(2, $resultCrLf['cards']);
        $this->assertCount(2, $resultCr['cards']);
    }

    #[Test]
    public function test_skips_empty_lines()
    {
        $content = "Question 1\tAnswer 1\n\n\nQuestion 2\tAnswer 2\n\n";

        $result = $this->service->parseText($content);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['cards']);
    }

    #[Test]
    public function test_handles_long_content()
    {
        $longQuestion = str_repeat('This is a very long question. ', 100);
        $longAnswer = str_repeat('This is a very long answer. ', 100);
        $content = trim($longQuestion)."\t".trim($longAnswer);

        $result = $this->service->parseText($content);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['cards']);
        $this->assertEquals(trim($longQuestion), $result['cards'][0]['question']);
        $this->assertEquals(trim($longAnswer), $result['cards'][0]['answer']);
    }

    #[Test]
    public function test_validates_card_data_length()
    {
        $cards = [
            [
                'question' => str_repeat('x', 70000), // Too long
                'answer' => 'Answer',
            ],
        ];

        $errors = $this->service->validateImport($cards);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('may not be greater than 65535', $errors[0]);
    }
}
