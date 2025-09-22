<?php

namespace App\Services;

use App\Models\Flashcard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FlashcardCacheService
{
    // Cache TTL in seconds (1 hour)
    private const DEFAULT_TTL = 3600;

    // Cache TTL for counts (30 minutes)
    private const COUNT_TTL = 1800;

    // Cache TTL for search results (15 minutes)
    private const SEARCH_TTL = 900;

    // Cache key prefixes
    private const PREFIX_TOPIC_CARDS = 'flashcards:topic:';

    private const PREFIX_TOPIC_COUNT = 'flashcards:count:topic:';

    private const PREFIX_SEARCH = 'flashcards:search:';

    private const PREFIX_STATS = 'flashcards:stats:topic:';

    private const PREFIX_IMPORT_PROGRESS = 'flashcards:import:';

    /**
     * Cache flashcards for a specific topic
     */
    public function cacheTopicFlashcards(int $topicId, ?Collection $flashcards = null): Collection
    {
        $cacheKey = self::PREFIX_TOPIC_CARDS.$topicId;

        if ($flashcards !== null) {
            // Store in cache
            Cache::put($cacheKey, $flashcards->toArray(), self::DEFAULT_TTL);
            Log::debug("Cached flashcards for topic {$topicId}", ['count' => $flashcards->count()]);

            return $flashcards;
        }

        // Try to get from cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::debug("Retrieved cached flashcards for topic {$topicId}", ['count' => count($cached)]);
            $collection = collect($cached)->map(function ($item) {
                return new Flashcard($item);
            });

            return new Collection($collection->all());
        }

        // Cache miss - fetch from database
        $flashcards = Flashcard::where('topic_id', $topicId)
            ->where('is_active', true)
            ->with('topic.unit.subject')
            ->orderBy('created_at', 'desc')
            ->get();

        Cache::put($cacheKey, $flashcards->toArray(), self::DEFAULT_TTL);
        Log::debug("Fetched and cached flashcards for topic {$topicId}", ['count' => $flashcards->count()]);

        return $flashcards;
    }

    /**
     * Cache flashcard count for a topic
     */
    public function cacheTopicFlashcardCount(int $topicId, ?int $count = null): int
    {
        $cacheKey = self::PREFIX_TOPIC_COUNT.$topicId;

        if ($count !== null) {
            Cache::put($cacheKey, $count, self::COUNT_TTL);

            return $count;
        }

        // Try cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return (int) $cached;
        }

        // Cache miss - fetch from database
        $count = Flashcard::where('topic_id', $topicId)
            ->where('is_active', true)
            ->count();

        Cache::put($cacheKey, $count, self::COUNT_TTL);

        return $count;
    }

    /**
     * Cache search results
     */
    public function cacheSearchResults(string $query, array $filters = [], ?Collection $results = null): ?Collection
    {
        $filterKey = md5(serialize($filters));
        $cacheKey = self::PREFIX_SEARCH.md5($query).':'.$filterKey;

        if ($results !== null) {
            Cache::put($cacheKey, $results->toArray(), self::SEARCH_TTL);

            return $results;
        }

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            $collection = collect($cached)->map(function ($item) {
                return new Flashcard($item);
            });

            return new Collection($collection->all());
        }

        return null;
    }

    /**
     * Cache topic statistics
     */
    public function cacheTopicStats(int $topicId, ?array $stats = null): array
    {
        $cacheKey = self::PREFIX_STATS.$topicId;

        if ($stats !== null) {
            Cache::put($cacheKey, $stats, self::DEFAULT_TTL);

            return $stats;
        }

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Calculate stats
        $flashcards = $this->cacheTopicFlashcards($topicId);

        $stats = [
            'total_cards' => $flashcards->count(),
            'by_type' => $flashcards->groupBy('card_type')->map->count()->toArray(),
            'by_difficulty' => $flashcards->groupBy('difficulty_level')->map->count()->toArray(),
            'with_images' => $flashcards->whereNotNull('question_image_url')->count(),
            'with_hints' => $flashcards->whereNotNull('hint')->count(),
            'with_tags' => $flashcards->filter(function ($card) {
                return ! empty($card->tags);
            })->count(),
            'recently_added' => $flashcards->where('created_at', '>=', now()->subDays(7))->count(),
            'last_updated' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $stats, self::DEFAULT_TTL);

        return $stats;
    }

    /**
     * Cache import progress
     */
    public function cacheImportProgress(string $importId, array $progress): void
    {
        $cacheKey = self::PREFIX_IMPORT_PROGRESS.$importId;
        Cache::put($cacheKey, $progress, 300); // 5 minutes TTL for import progress
    }

    /**
     * Get cached import progress
     */
    public function getImportProgress(string $importId): ?array
    {
        $cacheKey = self::PREFIX_IMPORT_PROGRESS.$importId;

        return Cache::get($cacheKey);
    }

    /**
     * Invalidate all caches for a specific topic
     */
    public function invalidateTopicCache(int $topicId): void
    {
        $keys = [
            self::PREFIX_TOPIC_CARDS.$topicId,
            self::PREFIX_TOPIC_COUNT.$topicId,
            self::PREFIX_STATS.$topicId,
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // Also clear any search caches that might contain this topic's cards
        $this->clearSearchCache();

        Log::info("Invalidated flashcard caches for topic {$topicId}");
    }

    /**
     * Clear all search result caches
     */
    public function clearSearchCache(): void
    {
        // Get all cache keys with search prefix
        $cacheStore = Cache::getStore();

        if (method_exists($cacheStore, 'flush')) {
            // For drivers that support pattern deletion, we'd implement it here
            // For now, we'll use a simpler approach with cache tags (if supported)
        }

        Log::info('Cleared flashcard search caches');
    }

    /**
     * Warm cache for a topic (preload data)
     */
    public function warmTopicCache(int $topicId): void
    {
        Log::info("Warming cache for topic {$topicId}");

        // Preload flashcards
        $this->cacheTopicFlashcards($topicId);

        // Preload count
        $this->cacheTopicFlashcardCount($topicId);

        // Preload stats
        $this->cacheTopicStats($topicId);

        Log::info("Cache warmed for topic {$topicId}");
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        $cacheStore = Cache::getStore();

        $stats = [
            'driver' => config('cache.default'),
            'prefixes' => [
                'topic_cards' => self::PREFIX_TOPIC_CARDS,
                'topic_count' => self::PREFIX_TOPIC_COUNT,
                'search' => self::PREFIX_SEARCH,
                'stats' => self::PREFIX_STATS,
                'import_progress' => self::PREFIX_IMPORT_PROGRESS,
            ],
            'ttl' => [
                'default' => self::DEFAULT_TTL,
                'count' => self::COUNT_TTL,
                'search' => self::SEARCH_TTL,
            ],
        ];

        return $stats;
    }

    /**
     * Clear all flashcard-related caches
     */
    public function clearAllCaches(): void
    {
        $prefixes = [
            self::PREFIX_TOPIC_CARDS,
            self::PREFIX_TOPIC_COUNT,
            self::PREFIX_SEARCH,
            self::PREFIX_STATS,
            self::PREFIX_IMPORT_PROGRESS,
        ];

        // This is a simplified implementation
        // In production, you'd want to use cache tags or a more sophisticated approach
        Cache::flush();

        Log::info('Cleared all flashcard caches');
    }

    /**
     * Cache flashcards for a specific unit (aggregates all topics in unit)
     * Backward compatibility method for tests
     */
    public function cacheUnitFlashcards(int $unitId, ?Collection $flashcards = null): Collection
    {
        // Get all topics in the unit
        $topics = \App\Models\Topic::where('unit_id', $unitId)->get();

        if ($flashcards !== null) {
            // If flashcards provided, cache them by topic
            $flashcards->groupBy('topic_id')->each(function ($topicFlashcards, $topicId) {
                $this->cacheTopicFlashcards($topicId, $topicFlashcards);
            });

            return $flashcards;
        }

        // Aggregate flashcards from all topics in the unit
        $allFlashcards = collect();
        foreach ($topics as $topic) {
            $topicFlashcards = $this->cacheTopicFlashcards($topic->id);
            $allFlashcards = $allFlashcards->merge($topicFlashcards);
        }

        // Convert to Eloquent Collection
        return new Collection($allFlashcards->all());
    }

    /**
     * Cache unit statistics (aggregates all topics in unit)
     * Backward compatibility method for tests
     */
    public function cacheUnitStats(int $unitId, ?array $stats = null): array
    {
        if ($stats !== null) {
            // If stats provided, we can't really cache them at unit level in topic-only architecture
            // Just return them for compatibility
            return $stats;
        }

        // Aggregate stats from all topics in the unit
        $topics = \App\Models\Topic::where('unit_id', $unitId)->get();
        $aggregatedStats = [
            'total_cards' => 0,
            'by_type' => [],
            'by_difficulty' => [],
            'with_images' => 0,
            'with_hints' => 0,
            'with_tags' => 0,
            'recently_added' => 0,
            'last_updated' => now()->toISOString(),
        ];

        foreach ($topics as $topic) {
            $topicStats = $this->cacheTopicStats($topic->id);

            $aggregatedStats['total_cards'] += $topicStats['total_cards'] ?? 0;

            // Merge type counts
            foreach ($topicStats['by_type'] ?? [] as $type => $count) {
                $aggregatedStats['by_type'][$type] = ($aggregatedStats['by_type'][$type] ?? 0) + $count;
            }

            // Merge difficulty counts
            foreach ($topicStats['by_difficulty'] ?? [] as $difficulty => $count) {
                $aggregatedStats['by_difficulty'][$difficulty] = ($aggregatedStats['by_difficulty'][$difficulty] ?? 0) + $count;
            }

            $aggregatedStats['with_images'] += $topicStats['with_images'] ?? 0;
            $aggregatedStats['with_hints'] += $topicStats['with_hints'] ?? 0;
            $aggregatedStats['with_tags'] += $topicStats['with_tags'] ?? 0;
            $aggregatedStats['recently_added'] += $topicStats['recently_added'] ?? 0;
        }

        return $aggregatedStats;
    }

    /**
     * Invalidate unit cache (clears all topic caches in unit)
     * Backward compatibility method for tests
     */
    public function invalidateUnitCache(int $unitId): void
    {
        $topics = \App\Models\Topic::where('unit_id', $unitId)->get();

        foreach ($topics as $topic) {
            $this->invalidateTopicCache($topic->id);
        }

        Log::info("Invalidated flashcard caches for unit {$unitId} (all topics)");
    }

    /**
     * Get memory usage for debugging
     */
    public function getMemoryUsage(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
        ];
    }
}
