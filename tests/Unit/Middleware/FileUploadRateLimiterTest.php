<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\FileUploadRateLimiter;
use App\Services\ThreatDetectionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class FileUploadRateLimiterTest extends TestCase
{
    public function test_no_sleep_calls_in_middleware_code(): void
    {
        // Read the middleware file and verify no sleep() calls exist
        $middlewareFile = file_get_contents(app_path('Http/Middleware/FileUploadRateLimiter.php'));

        $this->assertStringNotContainsString('sleep(', $middlewareFile,
            'FileUploadRateLimiter should not contain any sleep() calls that block server processes');

        $this->assertStringNotContainsString('usleep(', $middlewareFile,
            'FileUploadRateLimiter should not contain any usleep() calls that block server processes');

        $this->assertStringNotContainsString('time_nanosleep(', $middlewareFile,
            'FileUploadRateLimiter should not contain any time_nanosleep() calls that block server processes');
    }

    public function test_rate_limiter_facade_is_used(): void
    {
        // Verify that the middleware uses Laravel's RateLimiter for proper rate limiting
        $middlewareFile = file_get_contents(app_path('Http/Middleware/FileUploadRateLimiter.php'));

        $this->assertStringContainsString('RateLimiter::hit', $middlewareFile,
            'FileUploadRateLimiter should use RateLimiter::hit for applying penalties');

        $this->assertStringContainsString('RateLimiter::tooManyAttempts', $middlewareFile,
            'FileUploadRateLimiter should use RateLimiter::tooManyAttempts for checking penalties');
    }

    public function test_suspicious_activity_penalty_logic_exists(): void
    {
        // Verify the suspicious activity penalty logic is properly implemented
        $middlewareFile = file_get_contents(app_path('Http/Middleware/FileUploadRateLimiter.php'));

        $this->assertStringContainsString('suspicious_activity:', $middlewareFile,
            'FileUploadRateLimiter should have suspicious activity penalty keys');

        $this->assertStringContainsString('Rate limiting penalty applied', $middlewareFile,
            'FileUploadRateLimiter should log when penalties are applied');

        $this->assertStringContainsString('Access temporarily restricted due to suspicious activity', $middlewareFile,
            'FileUploadRateLimiter should have proper error message for rate limited requests');
    }

    public function test_middleware_execution_time_is_reasonable(): void
    {
        // Test that the middleware executes quickly without blocking calls
        $threatDetection = Mockery::mock(ThreatDetectionService::class);
        $threatDetection->shouldReceive('isIpBlocked')->andReturn(false);

        $middleware = new FileUploadRateLimiter($threatDetection);

        $request = Request::create('/test', 'POST');
        $request->setUserResolver(function () {
            return null; // Guest user
        });

        $startTime = microtime(true);

        try {
            // This might fail due to missing file uploads, but we're testing execution time
            $response = $middleware->handle($request, function ($request) {
                return new Response('OK', 200);
            });
        } catch (\Exception $e) {
            // Expected - middleware might fail due to missing dependencies or file uploads
            // But the important thing is the execution time
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete in well under 1 second (no blocking sleep calls)
        $this->assertLessThan(0.5, $executionTime,
            'Middleware should execute quickly without any blocking sleep() calls');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
