<?php

namespace App\Services;

use App\Models\Child;
use App\Models\Session;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    // Cache TTL in seconds
    private const SHORT_CACHE = 300;    // 5 minutes

    private const MEDIUM_CACHE = 1800;  // 30 minutes

    private const LONG_CACHE = 3600;    // 1 hour

    /**
     * Cache user's children data
     */
    public function cacheUserChildren(string $userId, \Illuminate\Database\Eloquent\Collection $children): void
    {
        $cacheKey = "user_children_{$userId}";
        Cache::put($cacheKey, $children, self::MEDIUM_CACHE);
    }

    /**
     * Get cached user children
     */
    public function getUserChildren(string $userId): ?\Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "user_children_{$userId}";

        return Cache::get($cacheKey);
    }

    /**
     * Cache child's sessions
     */
    public function cacheChildSessions(int $childId, Collection $sessions): void
    {
        $cacheKey = "child_sessions_{$childId}";
        Cache::put($cacheKey, $sessions, self::SHORT_CACHE);

        // Also cache by status for faster access
        $sessionsByStatus = $sessions->groupBy('status');
        foreach ($sessionsByStatus as $status => $statusSessions) {
            $statusCacheKey = "child_sessions_{$childId}_{$status}";
            Cache::put($statusCacheKey, $statusSessions, self::SHORT_CACHE);
        }
    }

    /**
     * Get cached child sessions
     */
    public function getChildSessions(int $childId, ?string $status = null): ?Collection
    {
        $cacheKey = $status
            ? "child_sessions_{$childId}_{$status}"
            : "child_sessions_{$childId}";

        return Cache::get($cacheKey);
    }

    /**
     * Cache user's subjects with units and topics
     */
    public function cacheUserSubjects(string $userId, Collection $subjects): void
    {
        $cacheKey = "user_subjects_{$userId}";
        Cache::put($cacheKey, $subjects, self::LONG_CACHE);
    }

    /**
     * Get cached user subjects
     */
    public function getUserSubjects(string $userId): ?Collection
    {
        $cacheKey = "user_subjects_{$userId}";

        return Cache::get($cacheKey);
    }

    /**
     * Cache child's time blocks
     */
    public function cacheChildTimeBlocks(int $childId, Collection $timeBlocks): void
    {
        $cacheKey = "child_timeblocks_{$childId}";
        Cache::put($cacheKey, $timeBlocks, self::MEDIUM_CACHE);

        // Cache by day for faster access
        $timeBlocksByDay = $timeBlocks->groupBy('day_of_week');
        foreach ($timeBlocksByDay as $day => $dayBlocks) {
            $dayCacheKey = "child_timeblocks_{$childId}_day_{$day}";
            Cache::put($dayCacheKey, $dayBlocks, self::MEDIUM_CACHE);
        }
    }

    /**
     * Get cached child time blocks
     */
    public function getChildTimeBlocks(int $childId, ?int $dayOfWeek = null): ?Collection
    {
        $cacheKey = $dayOfWeek
            ? "child_timeblocks_{$childId}_day_{$dayOfWeek}"
            : "child_timeblocks_{$childId}";

        return Cache::get($cacheKey);
    }

    /**
     * Cache child's capacity analysis
     */
    public function cacheChildCapacityAnalysis(int $childId, array $analysis): void
    {
        $cacheKey = "child_capacity_{$childId}";
        Cache::put($cacheKey, $analysis, self::SHORT_CACHE);
    }

    /**
     * Get cached capacity analysis
     */
    public function getChildCapacityAnalysis(int $childId): ?array
    {
        $cacheKey = "child_capacity_{$childId}";

        return Cache::get($cacheKey);
    }

    /**
     * Cache quality heuristics analysis
     */
    public function cacheQualityAnalysis(int $childId, array $analysis): void
    {
        $cacheKey = "child_quality_{$childId}";
        Cache::put($cacheKey, $analysis, self::MEDIUM_CACHE);
    }

    /**
     * Get cached quality analysis
     */
    public function getQualityAnalysis(int $childId): ?array
    {
        $cacheKey = "child_quality_{$childId}";

        return Cache::get($cacheKey);
    }

    /**
     * Cache review statistics for child
     */
    public function cacheChildReviewStats(int $childId, array $stats): void
    {
        $cacheKey = "child_review_stats_{$childId}";
        Cache::put($cacheKey, $stats, self::LONG_CACHE);
    }

    /**
     * Get cached review statistics
     */
    public function getChildReviewStats(int $childId): ?array
    {
        $cacheKey = "child_review_stats_{$childId}";

        return Cache::get($cacheKey);
    }

    /**
     * Cache child's dashboard data
     */
    public function cacheChildDashboard(int $childId, array $dashboardData): void
    {
        $cacheKey = "child_dashboard_{$childId}";
        Cache::put($cacheKey, $dashboardData, self::SHORT_CACHE);
    }

    /**
     * Get cached dashboard data
     */
    public function getChildDashboard(int $childId): ?array
    {
        $cacheKey = "child_dashboard_{$childId}";

        return Cache::get($cacheKey);
    }

    /**
     * Cache planning board data
     */
    public function cachePlanningBoard(int $childId, array $planningData): void
    {
        $cacheKey = "planning_board_{$childId}";
        Cache::put($cacheKey, $planningData, self::SHORT_CACHE);
    }

    /**
     * Get cached planning board data
     */
    public function getPlanningBoard(int $childId): ?array
    {
        $cacheKey = "planning_board_{$childId}";

        return Cache::get($cacheKey);
    }

    /**
     * Clear all caches for a user
     */
    public function clearUserCache(string $userId): void
    {
        $patterns = [
            "user_children_{$userId}",
            "user_subjects_{$userId}",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Clear all caches for a child
     */
    public function clearChildCache(int $childId): void
    {
        $patterns = [
            "child_sessions_{$childId}",
            "child_sessions_{$childId}_*",
            "child_timeblocks_{$childId}",
            "child_timeblocks_{$childId}_day_*",
            "child_capacity_{$childId}",
            "child_quality_{$childId}",
            "child_review_stats_{$childId}",
            "child_dashboard_{$childId}",
            "planning_board_{$childId}",
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // For wildcard patterns, we'd need a more sophisticated cache clear
                // For now, clear specific known keys
                for ($i = 1; $i <= 7; $i++) {
                    $key = str_replace('*', (string) $i, $pattern);
                    Cache::forget($key);
                }
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Clear session-related caches when session is updated
     */
    public function clearSessionCaches(int $childId): void
    {
        $patterns = [
            "child_sessions_{$childId}",
            "child_capacity_{$childId}",
            "child_quality_{$childId}",
            "child_dashboard_{$childId}",
            "planning_board_{$childId}",
        ];

        // Clear sessions by status
        $statuses = ['backlog', 'planned', 'scheduled', 'done'];
        foreach ($statuses as $status) {
            $patterns[] = "child_sessions_{$childId}_{$status}";
        }

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Clear time block related caches
     */
    public function clearTimeBlockCaches(int $childId): void
    {
        Cache::forget("child_timeblocks_{$childId}");
        Cache::forget("child_capacity_{$childId}");
        Cache::forget("child_quality_{$childId}");

        // Clear day-specific caches
        for ($day = 1; $day <= 7; $day++) {
            Cache::forget("child_timeblocks_{$childId}_day_{$day}");
        }
    }

    /**
     * Warm cache with commonly accessed data
     */
    public function warmUserCache(string $userId): void
    {
        // Cache user's children
        $children = Child::forUser($userId);
        $this->cacheUserChildren($userId, $children);

        // Cache user's subjects
        $subjects = Subject::forUser($userId);
        $this->cacheUserSubjects($userId, $subjects);

        // Cache each child's commonly accessed data
        foreach ($children as $child) {
            // Cache sessions
            $sessions = Session::forChild($child->id);
            $this->cacheChildSessions($child->id, $sessions);

            // Cache time blocks
            $timeBlocks = $child->timeBlocks;
            $this->cacheChildTimeBlocks($child->id, $timeBlocks);
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(string $userId): array
    {
        $stats = [
            'cached_children' => Cache::has("user_children_{$userId}"),
            'cached_subjects' => Cache::has("user_subjects_{$userId}"),
            'child_caches' => [],
        ];

        // Check child-specific caches
        $children = $this->getUserChildren($userId);
        if ($children) {
            foreach ($children as $child) {
                /** @var \App\Models\Child $child */
                $stats['child_caches'][$child->id] = [
                    'sessions' => Cache::has("child_sessions_{$child->id}"),
                    'timeblocks' => Cache::has("child_timeblocks_{$child->id}"),
                    'capacity' => Cache::has("child_capacity_{$child->id}"),
                    'quality' => Cache::has("child_quality_{$child->id}"),
                    'dashboard' => Cache::has("child_dashboard_{$child->id}"),
                ];
            }
        }

        return $stats;
    }

    /**
     * Cache expensive query results
     */
    public function cacheExpensiveQuery(string $key, callable $callback, int $ttl = self::MEDIUM_CACHE)
    {
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Cache with tags for easier invalidation (if cache store supports it)
     */
    public function cacheWithTags(array $tags, string $key, $value, int $ttl = self::MEDIUM_CACHE): void
    {
        if (Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
            Cache::tags($tags)->put($key, $value, $ttl);
        } else {
            Cache::put($key, $value, $ttl);
        }
    }

    /**
     * Clear cache by tags
     */
    public function clearByTags(array $tags): void
    {
        if (Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
            Cache::tags($tags)->flush();
        }
    }

    /**
     * Generate cache key with parameters
     */
    public function generateKey(string $base, array $params = []): string
    {
        if (empty($params)) {
            return $base;
        }

        $paramString = implode('_', array_map(
            fn ($key, $value) => "{$key}_{$value}",
            array_keys($params),
            array_values($params)
        ));

        return "{$base}_{$paramString}";
    }
}
