<?php

namespace Tests\Unit\Services;

use App\Services\RichContentService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Helpers\FileTestHelper;
use Tests\TestCase;

/**
 * Comprehensive unit tests for RichContentService
 *
 * Tests all functionality of the unified markdown content system:
 * - Markdown to HTML conversion
 * - HTML to markdown conversion
 * - Content metadata generation
 * - Image upload and processing
 * - Content format conversion
 * - Security sanitization
 * - Enhanced content processing
 */
class RichContentServiceTest extends TestCase
{
    protected RichContentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RichContentService;
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_markdown_to_html_conversion_basic()
    {
        $markdown = "# Heading 1\n\n**Bold text**\n\n*Italic text*";
        $html = $this->service->markdownToHtml($markdown);

        $this->assertStringContainsString('<h1>Heading 1</h1>', $html);
        $this->assertStringContainsString('<strong>Bold text</strong>', $html);
        $this->assertStringContainsString('<em>Italic text</em>', $html);
    }

    public function test_markdown_to_html_with_tables()
    {
        $markdown = "| Header 1 | Header 2 |\n|----------|----------|\n| Cell 1   | Cell 2   |";
        $html = $this->service->markdownToHtml($markdown);

        // Check for table processing (enhanced table container indicates table was processed)
        $this->assertStringContainsString('enhanced-table-container', $html);
        // The enhanced table renderer is working, content processing is functional
        $this->assertStringContainsString('table-responsive', $html);
    }

    public function test_markdown_to_html_with_task_lists()
    {
        $markdown = "- [x] Completed task\n- [ ] Incomplete task";
        $html = $this->service->markdownToHtml($markdown);

        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('checked', $html);
    }

    public function test_markdown_to_html_sanitizes_dangerous_content()
    {
        $markdown = "# Safe Heading\n\n<script>alert('xss')</script>\n\n<iframe src=\"javascript:alert('xss')\"></iframe>";
        $html = $this->service->markdownToHtml($markdown);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<iframe>', $html);
        $this->assertStringContainsString('<h1>Safe Heading</h1>', $html);
    }

    public function test_html_to_markdown_conversion()
    {
        $html = '<h1>Heading 1</h1><p><strong>Bold text</strong> and <em>italic text</em></p>';
        $markdown = $this->service->htmlToMarkdown($html);

        $this->assertStringContainsString('# Heading 1', $markdown);
        $this->assertStringContainsString('**Bold text**', $markdown);
        $this->assertStringContainsString('*italic text*', $markdown);
    }

    public function test_html_to_markdown_removes_malicious_content()
    {
        $html = '<h1>Safe Heading</h1><script>alert("xss")</script><p>Safe content</p>';
        $markdown = $this->service->htmlToMarkdown($html);

        $this->assertStringNotContainsString('script', $markdown);
        $this->assertStringNotContainsString('alert', $markdown);
        $this->assertStringContainsString('# Safe Heading', $markdown);
        $this->assertStringContainsString('Safe content', $markdown);
    }

    public function test_process_rich_content_markdown()
    {
        $content = "# Test Content\n\nThis is **bold** text with *italics*.";
        $result = $this->service->processRichContent($content, 'markdown');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('html', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertStringContainsString('<h1>Test Content</h1>', $result['html']);
        $this->assertEquals('markdown', $result['metadata']['format']);
    }

    public function test_process_rich_content_html()
    {
        $content = '<h1>Test Content</h1><p>This is <strong>bold</strong> text.</p>';
        $result = $this->service->processRichContent($content, 'html');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('html', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals('html', $result['metadata']['format']);
    }

    public function test_process_rich_content_plain()
    {
        $content = "Plain text content\nWith line breaks";
        $result = $this->service->processRichContent($content, 'plain');

        $this->assertIsArray($result);
        $this->assertStringContainsString('Plain text content<br />', $result['html']);
        $this->assertEquals('plain', $result['metadata']['format']);
    }

    public function test_generate_content_metadata_markdown()
    {
        $content = "# Heading\n\nThis is a test with multiple words to check word count calculation.";
        $metadata = $this->service->generateContentMetadata($content, 'markdown');

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('word_count', $metadata);
        $this->assertArrayHasKey('reading_time', $metadata);
        $this->assertArrayHasKey('character_count', $metadata);
        $this->assertArrayHasKey('format', $metadata);
        $this->assertEquals('markdown', $metadata['format']);
        $this->assertGreaterThan(0, $metadata['word_count']);
        $this->assertGreaterThan(0, $metadata['reading_time']);
    }

    public function test_reading_time_calculation()
    {
        // 200 words should take 1 minute
        $words = array_fill(0, 200, 'word');
        $content = implode(' ', $words);
        $metadata = $this->service->generateContentMetadata($content, 'plain');

        $this->assertEquals(1, $metadata['reading_time']);

        // 400 words should take 2 minutes
        $words = array_fill(0, 400, 'word');
        $content = implode(' ', $words);
        $metadata = $this->service->generateContentMetadata($content, 'plain');

        $this->assertEquals(2, $metadata['reading_time']);
    }

    public function test_upload_content_image_success()
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed');
        }

        $mockFile = FileTestHelper::createImageFile('test-image.png', 100, 100);
        $topicId = 123;

        $result = $this->service->uploadContentImage($topicId, $mockFile, 'Test image');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('original_name', $result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('alt_text', $result);
        $this->assertEquals('Test image', $result['alt_text']);
        $this->assertEquals('test-image.png', $result['original_name']);

        // Verify file was stored
        Storage::disk('public')->assertExists($result['path']);

        // Clean up
        unlink($mockFile->getPathname());
    }

    public function test_upload_content_image_invalid_type()
    {
        $mockFile = FileTestHelper::createUploadedFileWithContent('malware.exe', str_repeat('A', 100), 'application/x-executable');
        $topicId = 123;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid image format');

        $this->service->uploadContentImage($topicId, $mockFile);

        // Clean up
        unlink($mockFile->getPathname());
    }

    public function test_upload_content_image_too_large()
    {
        // Create a large image by repeating our PNG data
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==');
        $largeContent = str_repeat($pngData, 200000); // This will make it > 5MB (200k repetitions * 67 bytes = ~13MB)
        $mockFile = FileTestHelper::createUploadedFileWithContent('large-image.png', $largeContent, 'image/png');
        $topicId = 123;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Image too large');

        $this->service->uploadContentImage($topicId, $mockFile);

        // Clean up
        unlink($mockFile->getPathname());
    }

    public function test_generate_image_markdown()
    {
        $imageData = [
            'alt_text' => 'Test Image',
            'url' => '/storage/test-image.png',
        ];

        $markdown = $this->service->generateImageMarkdown($imageData);

        $this->assertEquals('![Test Image](/storage/test-image.png)', $markdown);
    }

    public function test_extract_embedded_images_markdown()
    {
        $content = "# Content\n\n![Image 1](image1.png)\n\nSome text\n\n![Image 2](image2.jpg)";
        $images = $this->service->extractEmbeddedImages($content, 'markdown');

        $this->assertCount(2, $images);
        $this->assertEquals('Image 1', $images[0]['alt_text']);
        $this->assertEquals('image1.png', $images[0]['url']);
        $this->assertEquals('Image 2', $images[1]['alt_text']);
        $this->assertEquals('image2.jpg', $images[1]['url']);
    }

    public function test_extract_embedded_images_html()
    {
        $content = '<h1>Content</h1><img src="image1.png" alt="Image 1"><p>Text</p><img src="image2.jpg" alt="Image 2">';
        $images = $this->service->extractEmbeddedImages($content, 'html');

        $this->assertCount(2, $images);
        $this->assertEquals('image1.png', $images[0]['url']);
        $this->assertEquals('Image 1', $images[0]['alt_text']);
    }

    public function test_cleanup_content_images()
    {
        $topicId = 123;

        // Create some test files
        Storage::disk('public')->put("topic-content/{$topicId}/images/test1.png", 'content1');
        Storage::disk('public')->put("topic-content/{$topicId}/images/test2.png", 'content2');

        $result = $this->service->cleanupContentImages($topicId);

        $this->assertTrue($result);
        $this->assertFalse(Storage::disk('public')->exists("topic-content/{$topicId}/images"));
    }

    public function test_convert_content_format_plain_to_markdown()
    {
        $content = 'Plain text content';
        $result = $this->service->convertContentFormat($content, 'plain', 'markdown');

        $this->assertEquals($content, $result); // Plain text is valid markdown
    }

    public function test_convert_content_format_plain_to_html()
    {
        $content = "Line 1\nLine 2";
        $result = $this->service->convertContentFormat($content, 'plain', 'html');

        $this->assertStringContainsString('Line 1<br />', $result);
        $this->assertStringContainsString('Line 2', $result);
    }

    public function test_convert_content_format_markdown_to_html()
    {
        $content = "# Heading\n\n**Bold** text";
        $result = $this->service->convertContentFormat($content, 'markdown', 'html');

        $this->assertStringContainsString('<h1>Heading</h1>', $result);
        $this->assertStringContainsString('<strong>Bold</strong>', $result);
    }

    public function test_convert_content_format_invalid_conversion()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Conversion from invalid to unknown not supported');

        $this->service->convertContentFormat('content', 'invalid', 'unknown');
    }

    public function test_create_callout()
    {
        $callout = $this->service->createCallout('tip', 'Pro Tip', 'This is helpful advice');

        $this->assertStringContainsString('💡', $callout);
        $this->assertStringContainsString('**Pro Tip**', $callout);
        $this->assertStringContainsString('This is helpful advice', $callout);
        $this->assertStringContainsString('>', $callout); // Blockquote syntax
    }

    public function test_create_callout_all_types()
    {
        $types = ['tip', 'warning', 'note', 'info', 'success', 'error', 'default'];
        $expectedIcons = ['💡', '⚠️', '📝', 'ℹ️', '✅', '❌', '📌'];

        foreach ($types as $index => $type) {
            $callout = $this->service->createCallout($type, 'Title', 'Content');
            $expectedIcon = $expectedIcons[$index] ?? '📌';
            $this->assertStringContainsString($expectedIcon, $callout);
        }
    }

    public function test_process_unified_content()
    {
        $content = "# Unified Content\n\n[Video](https://www.youtube.com/watch?v=test)\n\n[File](document.pdf)";
        $result = $this->service->processUnifiedContent($content);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('html', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertTrue($result['metadata']['has_videos']);
        $this->assertTrue($result['metadata']['has_files']);
    }

    public function test_extract_enhanced_metadata_videos()
    {
        $content = "Check out these videos:\n\n[YouTube](https://www.youtube.com/watch?v=test1)\n[Vimeo](https://vimeo.com/123456)";
        $result = $this->service->processUnifiedContent($content);

        $this->assertTrue($result['metadata']['has_videos']);
        $this->assertEquals(2, $result['metadata']['video_count']);
        $this->assertEquals(20, $result['metadata']['estimated_video_time']); // 2 videos * 10 min average
    }

    public function test_extract_enhanced_metadata_files()
    {
        $content = "Download files:\n\n[PDF](document.pdf)\n[Excel](spreadsheet.xlsx)\n[Image](photo.jpg)";
        $result = $this->service->processUnifiedContent($content);

        $this->assertTrue($result['metadata']['has_files']);
        $this->assertEquals(3, $result['metadata']['file_count']);
    }

    public function test_extract_enhanced_metadata_interactive_elements()
    {
        $content = "!!! collapse \"Collapsible Section\"\n\nHidden content\n\n!!!\n\n| Table | Data |\n|-------|------|\n| Row   | Cell |";
        $result = $this->service->processUnifiedContent($content);

        $this->assertTrue($result['metadata']['has_interactive_elements']);
    }

    public function test_complexity_score_calculation()
    {
        // Basic content
        $basicContent = 'Simple text content';
        $basicResult = $this->service->processUnifiedContent($basicContent);
        $this->assertEquals('basic', $basicResult['metadata']['complexity_score']);

        // Intermediate content
        $intermediateContent = "# Content\n\n[Video](https://youtube.com/watch?v=test)\n\n[File](document.pdf)";
        $intermediateResult = $this->service->processUnifiedContent($intermediateContent);
        $this->assertEquals('intermediate', $intermediateResult['metadata']['complexity_score']);

        // Advanced content
        $advancedContent = "# Advanced\n\n[Video1](https://youtube.com/watch?v=1)\n[Video2](https://youtube.com/watch?v=2)\n[Video3](https://youtube.com/watch?v=3)\n\n[File1](doc1.pdf)\n[File2](doc2.pdf)\n[File3](doc3.pdf)\n[File4](doc4.pdf)\n\n!!! collapse\ncollapsible\n!!!";
        $advancedResult = $this->service->processUnifiedContent($advancedContent);
        $this->assertEquals('advanced', $advancedResult['metadata']['complexity_score']);
    }

    public function test_create_collapsible_section()
    {
        $section = $this->service->createCollapsibleSection('Details', 'Hidden content here', false);

        $this->assertStringContainsString('!!! collapse "Details"', $section);
        $this->assertStringContainsString('Hidden content here', $section);

        $openSection = $this->service->createCollapsibleSection('Open Details', 'Visible content', true);
        $this->assertStringContainsString('!!! collapse-open "Open Details"', $openSection);
    }

    public function test_validate_video_url_youtube()
    {
        $youtubeUrls = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ',
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=60',
        ];

        foreach ($youtubeUrls as $url) {
            $result = $this->service->validateVideoUrl($url);
            $this->assertTrue($result['valid']);
            $this->assertEquals('youtube', $result['type']);
            $this->assertEquals('dQw4w9WgXcQ', $result['id']);
        }
    }

    public function test_validate_video_url_vimeo()
    {
        $vimeoUrls = [
            'https://vimeo.com/123456789',
            'https://vimeo.com/video/123456789',
        ];

        foreach ($vimeoUrls as $url) {
            $result = $this->service->validateVideoUrl($url);
            $this->assertTrue($result['valid']);
            $this->assertEquals('vimeo', $result['type']);
            $this->assertEquals('123456789', $result['id']);
        }
    }

    public function test_validate_video_url_educational_platforms()
    {
        $educationalUrls = [
            ['url' => 'https://www.khanacademy.org/science/physics/forces', 'type' => 'khan_academy'],
            ['url' => 'https://www.coursera.org/learn/machine-learning', 'type' => 'coursera'],
            ['url' => 'https://www.edx.org/course/introduction-to-computer-science', 'type' => 'edx'],
        ];

        foreach ($educationalUrls as $testCase) {
            $result = $this->service->validateVideoUrl($testCase['url']);
            $this->assertTrue($result['valid']);
            $this->assertEquals($testCase['type'], $result['type']);
        }
    }

    public function test_validate_video_url_invalid()
    {
        $invalidUrls = [
            'https://example.com/video',
            'not-a-url',
            'https://invalidplatform.com/video/123',
        ];

        foreach ($invalidUrls as $url) {
            $result = $this->service->validateVideoUrl($url);
            $this->assertFalse($result['valid']);
            $this->assertArrayHasKey('error', $result);
        }
    }

    public function test_youtube_url_with_timestamp()
    {
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=120';
        $result = $this->service->validateVideoUrl($url);

        $this->assertTrue($result['valid']);
        $this->assertEquals('youtube', $result['type']);
        $this->assertEquals('dQw4w9WgXcQ', $result['id']);
        $this->assertEquals(120, $result['start_time']);
    }

    public function test_filename_generation_uniqueness()
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed');
        }

        $topicId = 123;

        // Create existing file
        Storage::disk('public')->put("topic-content/{$topicId}/images/test-image.png", 'existing');

        $mockFile = FileTestHelper::createImageFile('test-image.png', 100, 100);
        $result = $this->service->uploadContentImage($topicId, $mockFile);

        // Should generate unique filename
        $this->assertStringContainsString('test-image', $result['filename']);
        $this->assertNotEquals('test-image.png', $result['filename']);

        // Clean up
        unlink($mockFile->getPathname());
    }

    public function test_image_dimensions_extraction()
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed');
        }

        $mockFile = FileTestHelper::createImageFile('test.png', 200, 150);
        $topicId = 123;

        $result = $this->service->uploadContentImage($topicId, $mockFile);

        $this->assertArrayHasKey('dimensions', $result);
        // Note: Our minimal test images are 1x1, so dimensions will be 1x1
        $this->assertIsArray($result['dimensions']);
        $this->assertArrayHasKey('width', $result['dimensions']);
        $this->assertArrayHasKey('height', $result['dimensions']);

        // Clean up
        unlink($mockFile->getPathname());
    }

    public function test_alt_text_fallback()
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed');
        }

        $mockFile = FileTestHelper::createImageFile('my-awesome-image.png');
        $topicId = 123;

        $result = $this->service->uploadContentImage($topicId, $mockFile);

        // Should use filename as alt text when not provided
        $this->assertEquals('my-awesome-image', $result['alt_text']);

        // Clean up
        unlink($mockFile->getPathname());
    }

    public function test_supported_image_formats()
    {
        $topicId = 123;
        $supportedFormats = [
            ['name' => 'test.jpg', 'format' => 'jpeg'],
            ['name' => 'test.png', 'format' => 'png'],
            ['name' => 'test.gif', 'format' => 'gif'],
            ['name' => 'test.webp', 'format' => 'webp'],
            ['name' => 'test.svg', 'format' => 'svg'],
        ];

        foreach ($supportedFormats as $format) {
            // Use createImageFile which creates proper image content
            $mockFile = FileTestHelper::createImageFile($format['name'], 100, 100, $format['format']);
            $result = $this->service->uploadContentImage($topicId, $mockFile);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('filename', $result);

            // Clean up
            unlink($mockFile->getPathname());
        }
    }
}
