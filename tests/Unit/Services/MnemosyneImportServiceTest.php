<?php

namespace Tests\Unit\Services;

use App\Services\MnemosyneImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MnemosyneImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private MnemosyneImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MnemosyneImportService;
    }

    public function test_parses_simple_xml_format(): void
    {
        $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
<mnemosyne>
    <card>
        <question>What is the capital of France?</question>
        <answer>Paris</answer>
        <category>Geography</category>
    </card>
    <card>
        <question>What is 2 + 2?</question>
        <answer>4</answer>
        <category>Math</category>
    </card>
</mnemosyne>';

        $result = $this->service->parseMnemosyneContent($xmlContent, 'test.xml');

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['total_cards']);
        $this->assertCount(2, $result['cards']);

        $firstCard = $result['cards'][0];
        $this->assertEquals('What is the capital of France?', $firstCard['question']);
        $this->assertEquals('Paris', $firstCard['answer']);
        $this->assertEquals('basic', $firstCard['card_type']);
        $this->assertContains('Geography', $firstCard['tags']);
        $this->assertEquals('mnemosyne', $firstCard['import_source']);
    }

    public function test_parses_alternative_xml_format(): void
    {
        $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
<cards>
    <item>
        <q>What programming language is Laravel built with?</q>
        <a>PHP</a>
    </item>
    <item>
        <front>What does HTML stand for?</front>
        <back>HyperText Markup Language</back>
    </item>
</cards>';

        $result = $this->service->parseMnemosyneContent($xmlContent, 'test.xml');

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['total_cards']);

        $firstCard = $result['cards'][0];
        $this->assertEquals('What programming language is Laravel built with?', $firstCard['question']);
        $this->assertEquals('PHP', $firstCard['answer']);

        $secondCard = $result['cards'][1];
        $this->assertEquals('What does HTML stand for?', $secondCard['question']);
        $this->assertEquals('HyperText Markup Language', $secondCard['answer']);
    }

    public function test_parses_text_format(): void
    {
        $textContent = "What is the capital of France?\tParis
What is 2 + 2?\t4
What is the largest planet?\tJupiter";

        $result = $this->service->parseMnemosyneContent($textContent, 'test.txt');

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['total_cards']);
        $this->assertCount(3, $result['cards']);

        $firstCard = $result['cards'][0];
        $this->assertEquals('What is the capital of France?', $firstCard['question']);
        $this->assertEquals('Paris', $firstCard['answer']);
        $this->assertEquals('basic', $firstCard['card_type']);
    }

    public function test_handles_malformed_xml(): void
    {
        $malformedXml = '<?xml version="1.0" encoding="UTF-8"?>
<mnemosyne>
    <card>
        <question>What is broken XML?
        <answer>This should still work</answer>
    </card>
</mnemosyne>';

        $result = $this->service->parseMnemosyneContent($malformedXml, 'malformed.xml');

        // Should gracefully handle and try regex parsing
        $this->assertTrue(isset($result['success']));
    }

    public function test_validates_mnemosyne_file(): void
    {
        // Create a temporary XML file
        $tempDir = storage_path('app/temp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $tempPath = $tempDir.'/test_mnemosyne_'.uniqid().'.xml';
        $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
<mnemosyne>
    <card>
        <question>Test question</question>
        <answer>Test answer</answer>
    </card>
</mnemosyne>';
        file_put_contents($tempPath, $xmlContent);

        $uploadedFile = new UploadedFile(
            $tempPath,
            'test.xml',
            'application/xml',
            null,
            true
        );

        $validation = $this->service->validateMnemosyneFile($uploadedFile);

        $this->assertTrue($validation['valid']);
        $this->assertEquals('xml', $validation['extension']);

        unlink($tempPath);
    }

    public function test_rejects_invalid_file_extension(): void
    {
        $tempDir = storage_path('app/temp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $tempPath = $tempDir.'/test_invalid_'.uniqid().'.txt';
        file_put_contents($tempPath, 'Some content');

        $uploadedFile = new UploadedFile(
            $tempPath,
            'test.txt',
            'text/plain',
            null,
            true
        );

        $validation = $this->service->validateMnemosyneFile($uploadedFile);

        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('extension', $validation['error']);

        unlink($tempPath);
    }

    public function test_extracts_categories(): void
    {
        $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
<mnemosyne>
    <card>
        <question>Geography question</question>
        <answer>Answer</answer>
        <category>Geography</category>
    </card>
    <card>
        <question>Math question</question>
        <answer>Answer</answer>
        <category>Mathematics</category>
    </card>
    <card>
        <question>Another geography question</question>
        <answer>Answer</answer>
        <category>Geography</category>
    </card>
</mnemosyne>';

        $result = $this->service->parseMnemosyneContent($xmlContent, 'test.xml');

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['categories']); // Should have unique categories
        $this->assertContains('Geography', $result['categories']);
        $this->assertContains('Mathematics', $result['categories']);
    }

    public function test_handles_empty_file(): void
    {
        $result = $this->service->parseMnemosyneContent('', 'empty.xml');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No valid flashcards found', $result['error']);
    }

    public function test_converts_difficulty_levels(): void
    {
        $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
<mnemosyne>
    <card>
        <question>Easy question</question>
        <answer>Answer</answer>
        <difficulty>1</difficulty>
    </card>
    <card>
        <question>Medium question</question>
        <answer>Answer</answer>
        <difficulty>3</difficulty>
    </card>
    <card>
        <question>Hard question</question>
        <answer>Answer</answer>
        <difficulty>5</difficulty>
    </card>
</mnemosyne>';

        $result = $this->service->parseMnemosyneContent($xmlContent, 'test.xml');

        $this->assertTrue($result['success']);
        $this->assertEquals('easy', $result['cards'][0]['difficulty_level']);
        $this->assertEquals('medium', $result['cards'][1]['difficulty_level']);
        $this->assertEquals('hard', $result['cards'][2]['difficulty_level']);
    }
}
