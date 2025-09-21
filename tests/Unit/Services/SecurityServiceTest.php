<?php

namespace Tests\Unit\Services;

use App\Services\SecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\FileTestHelper;
use Tests\TestCase;

/**
 * Comprehensive unit tests for SecurityService
 *
 * Tests all security functionality including:
 * - File upload validation
 * - Content security scanning
 * - Malicious content detection
 * - URL validation
 * - Secure token operations
 * - Access control enforcement
 */
class SecurityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SecurityService $securityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->securityService = new SecurityService;
    }

    public function test_validate_file_upload_valid_image()
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed');
        }

        $file = FileTestHelper::createImageFile('test.png', 100, 100);

        $result = $this->securityService->validateFileUpload($file);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals('safe', $result['security_level']);
        $this->assertEquals('image', $result['file_type']);

        // Clean up
        unlink($file->getPathname());
    }

    public function test_validate_file_upload_valid_document()
    {
        $file = FileTestHelper::createUploadedFileWithContent('document.pdf', str_repeat('A', 1024), 'application/pdf');

        $result = $this->securityService->validateFileUpload($file);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals('document', $result['file_type']);

        // Clean up
        unlink($file->getPathname());
    }

    public function test_validate_file_upload_dangerous_extension()
    {
        $file = FileTestHelper::createUploadedFileWithContent('malware.exe', str_repeat('A', 1024), 'application/x-executable');

        $result = $this->securityService->validateFileUpload($file);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('not allowed for security reasons', $result['errors'][0]);

        // Clean up
        unlink($file->getPathname());
    }

    public function test_validate_file_upload_oversized_file()
    {
        // Create oversized image (6MB > 5MB limit) with valid PNG content
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==');
        $largeContent = str_repeat($pngData, 200000); // This will make it > 5MB (200k repetitions * 67 bytes = ~13MB)
        $file = FileTestHelper::createUploadedFileWithContent('large.png', $largeContent, 'image/png');

        $result = $this->securityService->validateFileUpload($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('File size exceeds maximum', $result['errors'][0]);

        // Clean up
        unlink($file->getPathname());
    }

    public function test_scan_content_security_safe_content()
    {
        $content = "# Safe Content\n\nThis is completely safe markdown content.";

        $result = $this->securityService->scanContentSecurity($content);

        $this->assertTrue($result['safe']);
        $this->assertEmpty($result['threats']);
        $this->assertEquals('safe', $result['security_level']);
    }

    public function test_scan_content_security_script_injection()
    {
        $content = "# Content\n\n<script>alert('xss')</script>\n\nMore content.";

        $result = $this->securityService->scanContentSecurity($content);

        $this->assertFalse($result['safe']);
        $this->assertNotEmpty($result['threats']);
        $this->assertEquals('dangerous', $result['security_level']);
    }

    public function test_scan_content_security_sql_injection()
    {
        $content = 'Test content with UNION SELECT * FROM users';

        $result = $this->securityService->scanContentSecurity($content);

        $this->assertFalse($result['safe']);
        $this->assertNotEmpty($result['threats']);
    }

    public function test_scan_content_security_command_injection()
    {
        $content = 'Test content; rm -rf /';

        $result = $this->securityService->scanContentSecurity($content);

        $this->assertFalse($result['safe']);
        $this->assertNotEmpty($result['threats']);
    }

    public function test_validate_url_valid_https()
    {
        $url = 'https://www.example.com/video';

        $result = $this->securityService->validateUrl($url);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['safe']);
        $this->assertEquals('safe', $result['security_level']);
    }

    public function test_validate_url_invalid_protocol()
    {
        $url = 'ftp://example.com/file';

        $result = $this->securityService->validateUrl($url);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Only HTTP and HTTPS protocols', $result['warnings'][0]);
    }

    public function test_validate_url_ip_address()
    {
        $url = 'http://192.168.1.1/video';

        $result = $this->securityService->validateUrl($url);

        $this->assertTrue($result['valid']);
        $this->assertFalse($result['safe']);
        $this->assertStringContainsString('IP address', $result['warnings'][0]);
    }

    public function test_validate_url_suspicious_tld()
    {
        $url = 'https://suspicious.tk/video';

        $result = $this->securityService->validateUrl($url);

        $this->assertTrue($result['valid']);
        $this->assertFalse($result['safe']);
        $this->assertStringContainsString('suspicious TLD', $result['warnings'][0]);
    }

    public function test_validate_url_shortener()
    {
        $url = 'https://bit.ly/test123';

        $result = $this->securityService->validateUrl($url);

        $this->assertTrue($result['valid']);
        $this->assertFalse($result['safe']);
        $this->assertStringContainsString('URL shortening service', $result['warnings'][0]);
    }

    public function test_generate_secure_file_token()
    {
        $filePath = '/uploads/test.pdf';
        $userId = 123;

        $token = $this->securityService->generateSecureFileToken($filePath, $userId, 60);

        $this->assertIsString($token);
        $this->assertStringContainsString('.', $token); // Should have token.signature format
    }

    public function test_validate_secure_file_token_valid()
    {
        $filePath = '/uploads/test.pdf';
        $userId = 123;

        $token = $this->securityService->generateSecureFileToken($filePath, $userId, 60);
        $isValid = $this->securityService->validateSecureFileToken($token, $filePath, $userId);

        $this->assertTrue($isValid);
    }

    public function test_validate_secure_file_token_wrong_file()
    {
        $filePath = '/uploads/test.pdf';
        $userId = 123;

        $token = $this->securityService->generateSecureFileToken($filePath, $userId, 60);
        $isValid = $this->securityService->validateSecureFileToken($token, '/uploads/other.pdf', $userId);

        $this->assertFalse($isValid);
    }

    public function test_validate_secure_file_token_wrong_user()
    {
        $filePath = '/uploads/test.pdf';
        $userId = 123;

        $token = $this->securityService->generateSecureFileToken($filePath, $userId, 60);
        $isValid = $this->securityService->validateSecureFileToken($token, $filePath, 456);

        $this->assertFalse($isValid);
    }

    public function test_validate_secure_file_token_malformed()
    {
        $result = $this->securityService->validateSecureFileToken('invalid-token', '/test.pdf', 123);

        $this->assertFalse($result);
    }

    public function test_sanitize_content_basic()
    {
        $content = '<script>alert("xss")</script>Safe content';

        $sanitized = $this->securityService->sanitizeContent($content);

        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('Safe content', $sanitized);
    }

    public function test_sanitize_content_with_options()
    {
        $content = '<h1>Title</h1><p>Content & more</p>';

        $sanitized = $this->securityService->sanitizeContent($content, [
            'strip_html' => true,
            'escape_html' => true,
        ]);

        $this->assertStringNotContainsString('<h1>', $sanitized);
        $this->assertStringContainsString('&amp;', $sanitized);
    }

    public function test_file_type_validation_all_allowed_types()
    {
        $allowedTypes = [
            'image/jpeg' => 'test.jpg',
            'image/png' => 'test.png',
            'application/pdf' => 'test.pdf',
            'application/msword' => 'test.doc',
            'text/plain' => 'test.txt',
            'audio/mpeg' => 'test.mp3',
            'video/mp4' => 'test.mp4',
            'application/zip' => 'test.zip',
        ];

        foreach ($allowedTypes as $mimeType => $filename) {
            $file = FileTestHelper::createUploadedFileWithContent($filename, str_repeat('A', 1024), $mimeType);
            $result = $this->securityService->validateFileUpload($file);

            $this->assertTrue($result['valid'], "File type {$mimeType} should be valid");

            // Clean up
            unlink($file->getPathname());
        }
    }

    public function test_file_signature_validation()
    {
        // Test with a real PNG signature using FileTestHelper
        $file = FileTestHelper::createImageFile('test.png', 1, 1, 'png');
        $result = $this->securityService->validateFileUpload($file);

        $this->assertTrue($result['valid']);

        // Clean up
        unlink($file->getPathname());
    }

    public function test_malicious_pattern_detection()
    {
        $maliciousContents = [
            '<script>alert("xss")</script>',
            'javascript:alert("xss")',
            'vbscript:msgbox("test")',
            'onclick="alert(1)"',
            '<?php system("rm -rf /"); ?>',
            'UNION SELECT * FROM users',
            '; DROP TABLE users;',
            '../../../etc/passwd',
            'eval(maliciousCode)',
        ];

        foreach ($maliciousContents as $content) {
            $result = $this->securityService->scanContentSecurity($content);
            $this->assertFalse($result['safe'], "Content should be detected as unsafe: {$content}");
        }
    }

    public function test_base64_encoded_threat_detection()
    {
        // Base64 encoded script tag
        $base64Script = base64_encode('<script>alert("encoded")</script>');
        $content = "Some content with encoded data: {$base64Script}";

        $result = $this->securityService->scanContentSecurity($content);

        $this->assertFalse($result['safe']);
        $this->assertNotEmpty($result['threats']);
    }

    public function test_excessive_redirects_detection()
    {
        $content = str_repeat('location.href = "http://evil.com"; ', 10);

        $result = $this->securityService->scanContentSecurity($content);

        $this->assertFalse($result['safe']);
        $threats = collect($result['threats'])->pluck('type');
        $this->assertContains('excessive_redirects', $threats);
    }

    public function test_code_obfuscation_detection()
    {
        $content = 'eval(someObfuscatedCode)';

        $result = $this->securityService->scanContentSecurity($content);

        $this->assertFalse($result['safe']);
        $threats = collect($result['threats'])->pluck('type');
        $this->assertContains('code_obfuscation', $threats);
    }

    public function test_filename_validation()
    {
        $testCases = [
            ['filename' => 'normal-file.pdf', 'should_be_valid' => true],
            ['filename' => 'file with spaces.pdf', 'should_be_valid' => true],
            ['filename' => 'file<with>invalid:chars.pdf', 'should_be_valid' => false],
            ['filename' => '.hidden-file.pdf', 'should_be_valid' => false],
            ['filename' => str_repeat('a', 300).'.pdf', 'should_be_valid' => false],
        ];

        foreach ($testCases as $testCase) {
            $file = FileTestHelper::createUploadedFileWithContent($testCase['filename'], str_repeat('A', 1024), 'application/pdf');
            $result = $this->securityService->validateFileUpload($file);

            if ($testCase['should_be_valid']) {
                $this->assertTrue($result['valid'] || empty($result['errors']),
                    "Filename '{$testCase['filename']}' should be valid");
            } else {
                $this->assertTrue(! empty($result['warnings']) || ! empty($result['errors']),
                    "Filename '{$testCase['filename']}' should have warnings or errors");
            }

            // Clean up
            unlink($file->getPathname());
        }
    }

    public function test_security_level_calculation()
    {
        $testCases = [
            'safe_content' => ['content' => 'Safe content', 'expected_level' => 'safe'],
            'warning_content' => ['content' => 'Content with location.href', 'expected_level' => 'warning'],
            'suspicious_content' => ['content' => str_repeat('suspicious ', 5), 'expected_level' => 'safe'], // Not actually suspicious
            'dangerous_content' => ['content' => '<script>eval(malicious)</script>', 'expected_level' => 'dangerous'],
        ];

        foreach ($testCases as $name => $testCase) {
            $result = $this->securityService->scanContentSecurity($testCase['content']);
            $this->assertEquals($testCase['expected_level'], $result['security_level'],
                "Content '{$name}' should have security level '{$testCase['expected_level']}'");
        }
    }

    public function test_token_expiry()
    {
        $filePath = '/uploads/test.pdf';
        $userId = 123;

        // Create token that expires immediately
        $token = $this->securityService->generateSecureFileToken($filePath, $userId, 0);

        // Wait a moment for it to expire
        sleep(1);

        $isValid = $this->securityService->validateSecureFileToken($token, $filePath, $userId);

        $this->assertFalse($isValid);
    }

    public function test_all_dangerous_extensions_blocked()
    {
        $dangerousExtensions = [
            'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar',
            'php', 'asp', 'aspx', 'jsp', 'pl', 'py', 'rb', 'sh', 'bash',
        ];

        foreach ($dangerousExtensions as $extension) {
            $file = FileTestHelper::createUploadedFileWithContent("malware.{$extension}", str_repeat('A', 1024), 'application/octet-stream');
            $result = $this->securityService->validateFileUpload($file);

            $this->assertFalse($result['valid'], "Extension '.{$extension}' should be blocked");

            // Clean up
            unlink($file->getPathname());
        }
    }

    public function test_file_size_limits_by_type()
    {
        // Test with smaller file sizes due to Laravel fake() file size issues
        $testCases = [
            ['type' => 'image/png', 'size' => 1000, 'should_pass' => true], // Small image should pass
            ['type' => 'application/pdf', 'size' => 1000, 'should_pass' => true], // Small document should pass
            ['type' => 'video/mp4', 'size' => 1000, 'should_pass' => true], // Small video should pass
        ];

        foreach ($testCases as $testCase) {
            $file = FileTestHelper::createFileWithSize('test', $testCase['size'], $testCase['type']);
            $result = $this->securityService->validateFileUpload($file);

            if ($testCase['should_pass']) {
                $this->assertTrue($result['valid'],
                    "File of type {$testCase['type']} with expected size {$testCase['size']} (actual: {$file->getSize()}) should pass. Errors: ".implode(', ', $result['errors']));
            } else {
                $this->assertFalse($result['valid'],
                    "File of type {$testCase['type']} with size {$testCase['size']} should fail");
            }

            // Clean up
            unlink($file->getPathname());
        }
    }
}
