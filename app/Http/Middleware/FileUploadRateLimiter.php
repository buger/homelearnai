<?php

namespace App\Http\Middleware;

use App\Services\ThreatDetectionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class FileUploadRateLimiter
{
    /**
     * Rate limiting configurations for different user roles
     */
    public const RATE_LIMITS = [
        'admin' => [
            'uploads_per_minute' => 60,
            'uploads_per_hour' => 300,
            'bandwidth_per_hour' => 1000 * 1024 * 1024, // 1GB
            'max_file_size' => 500 * 1024 * 1024, // 500MB
        ],
        'parent' => [
            'uploads_per_minute' => 20,
            'uploads_per_hour' => 100,
            'bandwidth_per_hour' => 500 * 1024 * 1024, // 500MB
            'max_file_size' => 200 * 1024 * 1024, // 200MB
        ],
        'child' => [
            'uploads_per_minute' => 5,
            'uploads_per_hour' => 30,
            'bandwidth_per_hour' => 100 * 1024 * 1024, // 100MB
            'max_file_size' => 50 * 1024 * 1024, // 50MB
        ],
        'guest' => [
            'uploads_per_minute' => 2,
            'uploads_per_hour' => 10,
            'bandwidth_per_hour' => 20 * 1024 * 1024, // 20MB
            'max_file_size' => 10 * 1024 * 1024, // 10MB
        ],
    ];

    /**
     * Suspicious activity thresholds
     */
    public const SUSPICIOUS_THRESHOLDS = [
        'rapid_uploads' => 10, // uploads per minute
        'large_file_threshold' => 100 * 1024 * 1024, // 100MB
        'failed_attempts_threshold' => 5, // failed uploads per hour
        'bandwidth_spike_threshold' => 2.0, // 2x normal bandwidth usage
    ];

    protected ThreatDetectionService $threatDetection;

    public function __construct(ThreatDetectionService $threatDetection)
    {
        $this->threatDetection = $threatDetection;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $userRole = $user?->role ?? 'guest';
        $userKey = $user ? "user:{$user->id}" : 'ip:'.$request->ip();
        $ipAddress = $request->ip();

        try {
            // Step 1: Check if IP is blocked
            if ($this->threatDetection->isIpBlocked($ipAddress)) {
                return $this->blockRequest('IP address is blocked for suspicious activity', 403);
            }

            // Step 2: Check rate limits
            $rateLimitResult = $this->checkRateLimits($request, $userKey, $userRole);
            if (! $rateLimitResult['allowed']) {
                return $this->blockRequest($rateLimitResult['reason'], 429);
            }

            // Step 3: Check file size limits
            if ($request->hasFile('file') || $request->hasFile('image') || $request->hasFile('chunk')) {
                $fileSizeResult = $this->checkFileSizeLimits($request, $userRole);
                if (! $fileSizeResult['allowed']) {
                    return $this->blockRequest($fileSizeResult['reason'], 413);
                }
            }

            // Step 4: Check bandwidth usage
            $bandwidthResult = $this->checkBandwidthUsage($request, $userKey, $userRole);
            if (! $bandwidthResult['allowed']) {
                return $this->blockRequest($bandwidthResult['reason'], 429);
            }

            // Step 5: Detect suspicious activity
            $suspiciousActivity = $this->detectSuspiciousActivity($request, $userKey, $userRole);
            if ($suspiciousActivity['detected']) {
                return $this->handleSuspiciousActivity($request, $suspiciousActivity);
            }

            // Step 6: Log successful rate limit check
            $this->logRateLimitCheck($request, $userKey, $userRole, true);

            // Process the request
            $response = $next($request);

            // Step 7: Update usage counters after successful upload
            $this->updateUsageCounters($request, $userKey, $userRole);

            return $response;

        } catch (\Exception $e) {
            Log::error('File upload rate limiter error', [
                'user_id' => $user?->id,
                'ip' => $ipAddress,
                'error' => $e->getMessage(),
            ]);

            // In case of error, allow the request but log the issue
            return $next($request);
        }
    }

    /**
     * Check various rate limits
     */
    private function checkRateLimits(Request $request, string $userKey, string $userRole): array
    {
        $limits = self::RATE_LIMITS[$userRole];

        // Check per-minute limit
        $minuteKey = $userKey.':uploads:'.now()->format('Y-m-d:H:i');
        $minuteUploads = Cache::get($minuteKey, 0);

        if ($minuteUploads >= $limits['uploads_per_minute']) {
            return [
                'allowed' => false,
                'reason' => "Upload rate limit exceeded: {$limits['uploads_per_minute']} uploads per minute for {$userRole} users",
            ];
        }

        // Check per-hour limit
        $hourKey = $userKey.':uploads:'.now()->format('Y-m-d:H');
        $hourUploads = Cache::get($hourKey, 0);

        if ($hourUploads >= $limits['uploads_per_hour']) {
            return [
                'allowed' => false,
                'reason' => "Upload rate limit exceeded: {$limits['uploads_per_hour']} uploads per hour for {$userRole} users",
            ];
        }

        // Check Laravel's built-in rate limiter for additional protection
        $rateLimiterKey = $userKey.':file_uploads';
        if (RateLimiter::tooManyAttempts($rateLimiterKey, $limits['uploads_per_minute'])) {
            return [
                'allowed' => false,
                'reason' => 'Too many upload attempts. Please try again later.',
            ];
        }

        // Check for suspicious activity penalty (IP-based)
        $ipAddress = $request->ip();
        $suspiciousIpKey = 'suspicious_activity:'.$ipAddress;
        if (RateLimiter::tooManyAttempts($suspiciousIpKey, 1)) {
            return [
                'allowed' => false,
                'reason' => 'Access temporarily restricted due to suspicious activity. Please try again later.',
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Check file size limits
     */
    private function checkFileSizeLimits(Request $request, string $userRole): array
    {
        $limits = self::RATE_LIMITS[$userRole];
        $files = $this->getUploadedFiles($request);

        foreach ($files as $file) {
            if ($file->getSize() > $limits['max_file_size']) {
                $maxSizeMB = round($limits['max_file_size'] / 1024 / 1024, 2);

                return [
                    'allowed' => false,
                    'reason' => "File size exceeds limit: {$maxSizeMB}MB for {$userRole} users",
                ];
            }
        }

        return ['allowed' => true];
    }

    /**
     * Check bandwidth usage
     */
    private function checkBandwidthUsage(Request $request, string $userKey, string $userRole): array
    {
        $limits = self::RATE_LIMITS[$userRole];
        $hourKey = $userKey.':bandwidth:'.now()->format('Y-m-d:H');
        $currentBandwidth = Cache::get($hourKey, 0);

        $files = $this->getUploadedFiles($request);
        $requestBandwidth = array_sum(array_map(fn ($file) => $file->getSize(), $files));

        if ($currentBandwidth + $requestBandwidth > $limits['bandwidth_per_hour']) {
            $limitMB = round($limits['bandwidth_per_hour'] / 1024 / 1024, 2);

            return [
                'allowed' => false,
                'reason' => "Bandwidth limit exceeded: {$limitMB}MB per hour for {$userRole} users",
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Detect suspicious upload activity
     */
    private function detectSuspiciousActivity(Request $request, string $userKey, string $userRole): array
    {
        $result = [
            'detected' => false,
            'indicators' => [],
            'risk_score' => 0,
        ];

        try {
            // Check for rapid successive uploads
            $rapidUploadsKey = $userKey.':rapid_uploads:'.now()->format('Y-m-d:H:i');
            $rapidUploads = Cache::get($rapidUploadsKey, 0);

            if ($rapidUploads >= self::SUSPICIOUS_THRESHOLDS['rapid_uploads']) {
                $result['detected'] = true;
                $result['indicators'][] = 'Rapid successive uploads detected';
                $result['risk_score'] += 5;
            }

            // Check for unusually large files
            $files = $this->getUploadedFiles($request);
            foreach ($files as $file) {
                if ($file->getSize() > self::SUSPICIOUS_THRESHOLDS['large_file_threshold']) {
                    $result['indicators'][] = 'Unusually large file upload detected';
                    $result['risk_score'] += 3;
                }
            }

            // Check for failed upload attempts
            $failedAttemptsKey = $userKey.':failed_uploads:'.now()->format('Y-m-d:H');
            $failedAttempts = Cache::get($failedAttemptsKey, 0);

            if ($failedAttempts >= self::SUSPICIOUS_THRESHOLDS['failed_attempts_threshold']) {
                $result['detected'] = true;
                $result['indicators'][] = 'High number of failed upload attempts';
                $result['risk_score'] += 4;
            }

            // Check for bandwidth usage spikes
            $this->checkBandwidthSpikes($userKey, $userRole, $result);

            // Check upload patterns
            $this->checkUploadPatterns($userKey, $result);

            if ($result['risk_score'] >= 5) {
                $result['detected'] = true;
            }

        } catch (\Exception $e) {
            Log::warning('Suspicious activity detection failed', [
                'user_key' => $userKey,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Handle suspicious activity
     */
    private function handleSuspiciousActivity(Request $request, array $suspiciousActivity): Response
    {
        $ipAddress = $request->ip();
        $user = $request->user();

        // Log suspicious activity
        Log::warning('Suspicious file upload activity detected', [
            'ip' => $ipAddress,
            'user_id' => $user?->id,
            'indicators' => $suspiciousActivity['indicators'],
            'risk_score' => $suspiciousActivity['risk_score'],
            'user_agent' => $request->userAgent(),
        ]);

        // Take automated action based on risk score
        if ($suspiciousActivity['risk_score'] >= 10) {
            // High risk - block IP temporarily
            $this->threatDetection->blockIp(
                $ipAddress,
                'Suspicious file upload activity detected',
                3600 // 1 hour
            );

            return $this->blockRequest('Suspicious activity detected. Access temporarily restricted.', 403);
        } elseif ($suspiciousActivity['risk_score'] >= 7) {
            // Medium risk - require additional verification
            return $this->blockRequest('Additional verification required. Please try again later.', 429);
        } else {
            // Low risk - apply rate limiting penalty without blocking server process
            $ipAddress = $request->ip();
            $suspiciousIpKey = 'suspicious_activity:'.$ipAddress;

            // Penalize this IP for 60 seconds by reducing their rate limit
            RateLimiter::hit($suspiciousIpKey, 60); // 60 second penalty

            // Log the suspicious activity penalty
            Log::info('Rate limiting penalty applied for suspicious activity', [
                'ip' => $ipAddress,
                'user_id' => $request->user()?->id,
                'risk_score' => $suspiciousActivity['risk_score'],
                'indicators' => $suspiciousActivity['indicators'],
                'penalty_duration' => 60,
            ]);

            return response()->json([
                'warning' => 'Upload activity is being monitored for security.',
            ], 202);
        }
    }

    /**
     * Block request with appropriate response
     */
    private function blockRequest(string $reason, int $statusCode): Response
    {
        $response = [
            'error' => 'Upload blocked',
            'message' => $reason,
            'status_code' => $statusCode,
        ];

        // Different response formats based on request type
        if (request()->expectsJson() || request()->header('HX-Request')) {
            return response()->json($response, $statusCode);
        }

        return response()->view('errors.rate-limited', $response, $statusCode);
    }

    /**
     * Update usage counters after successful upload
     */
    private function updateUsageCounters(Request $request, string $userKey, string $userRole): void
    {
        try {
            $files = $this->getUploadedFiles($request);
            $totalSize = array_sum(array_map(fn ($file) => $file->getSize(), $files));

            // Update upload counters
            $minuteKey = $userKey.':uploads:'.now()->format('Y-m-d:i');
            $hourKey = $userKey.':uploads:'.now()->format('Y-m-d:H');

            Cache::increment($minuteKey);
            Cache::increment($hourKey);

            // Set expiry for counters
            Cache::put($minuteKey, Cache::get($minuteKey, 1), now()->addMinutes(1));
            Cache::put($hourKey, Cache::get($hourKey, 1), now()->addHour());

            // Update bandwidth counters
            $bandwidthHourKey = $userKey.':bandwidth:'.now()->format('Y-m-d:H');
            Cache::increment($bandwidthHourKey, $totalSize);
            Cache::put($bandwidthHourKey, Cache::get($bandwidthHourKey, $totalSize), now()->addHour());

            // Update rapid upload counter
            $rapidKey = $userKey.':rapid_uploads:'.now()->format('Y-m-d:H:i');
            Cache::increment($rapidKey);
            Cache::put($rapidKey, Cache::get($rapidKey, 1), now()->addMinutes(1));

            // Hit Laravel rate limiter
            $rateLimiterKey = $userKey.':file_uploads';
            RateLimiter::hit($rateLimiterKey, 60); // 1 minute decay

        } catch (\Exception $e) {
            Log::warning('Failed to update usage counters', [
                'user_key' => $userKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get uploaded files from request
     */
    private function getUploadedFiles(Request $request): array
    {
        $files = [];

        // Check common file upload field names
        $fileFields = ['file', 'image', 'chunk', 'upload'];

        foreach ($fileFields as $field) {
            if ($request->hasFile($field)) {
                $uploadedFiles = $request->file($field);
                if (is_array($uploadedFiles)) {
                    $files = array_merge($files, $uploadedFiles);
                } else {
                    $files[] = $uploadedFiles;
                }
            }
        }

        return array_filter($files, fn ($file) => $file && $file->isValid());
    }

    /**
     * Check for bandwidth usage spikes
     */
    private function checkBandwidthSpikes(string $userKey, string $userRole, array &$result): void
    {
        $limits = self::RATE_LIMITS[$userRole];
        $currentHour = now()->format('Y-m-d:H');
        $previousHour = now()->subHour()->format('Y-m-d:H');

        $currentBandwidth = Cache::get($userKey.':bandwidth:'.$currentHour, 0);
        $previousBandwidth = Cache::get($userKey.':bandwidth:'.$previousHour, 0);

        if ($previousBandwidth > 0) {
            $spike = $currentBandwidth / $previousBandwidth;
            if ($spike > self::SUSPICIOUS_THRESHOLDS['bandwidth_spike_threshold']) {
                $result['indicators'][] = 'Bandwidth usage spike detected';
                $result['risk_score'] += 2;
            }
        }
    }

    /**
     * Check upload patterns for anomalies
     */
    private function checkUploadPatterns(string $userKey, array &$result): void
    {
        // Check if uploads are happening at unusual times
        $hour = now()->hour;
        if ($hour < 6 || $hour > 23) { // Late night/early morning uploads
            $result['indicators'][] = 'Uploads at unusual hours detected';
            $result['risk_score'] += 1;
        }

        // Check upload frequency consistency
        $uploadCounts = [];
        for ($i = 0; $i < 5; $i++) {
            $hourKey = $userKey.':uploads:'.now()->subHours($i)->format('Y-m-d:H');
            $uploadCounts[] = Cache::get($hourKey, 0);
        }

        $averageUploads = array_sum($uploadCounts) / count($uploadCounts);
        $currentUploads = $uploadCounts[0];

        if ($averageUploads > 0 && $currentUploads > $averageUploads * 3) {
            $result['indicators'][] = 'Upload frequency anomaly detected';
            $result['risk_score'] += 2;
        }
    }

    /**
     * Log rate limit check results
     */
    private function logRateLimitCheck(Request $request, string $userKey, string $userRole, bool $allowed): void
    {
        Log::info('File upload rate limit check', [
            'user_key' => $userKey,
            'user_role' => $userRole,
            'ip' => $request->ip(),
            'allowed' => $allowed,
            'user_agent' => $request->userAgent(),
            'file_count' => count($this->getUploadedFiles($request)),
        ]);
    }
}
