<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $subject_id
 * @property string $name
 * @property string|null $description
 * @property \Carbon\Carbon|null $target_completion_date
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read int $completed_topics_count
 * @property-read int $total_topics_count
 * @property-read float $completion_percentage
 * @property-read bool $can_complete
 * @property-read \App\Models\Subject $subject
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Topic> $topics
 */
class Unit extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'subject_id',
        'name',
        'description',
        'target_completion_date',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'subject_id' => 'integer',
        'target_completion_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Computed attributes that are not stored in database
     * but calculated dynamically
     */
    public function getCompletedTopicsCountAttribute(): int
    {
        // This will be calculated dynamically when needed
        return $this->topics()->count(); // Simplified for now
    }

    public function getTotalTopicsCountAttribute(): int
    {
        return $this->topics()->count();
    }

    public function getCompletionPercentageAttribute(): float
    {
        $total = $this->getTotalTopicsCountAttribute();
        if ($total === 0) {
            return 0.0;
        }

        $completed = $this->getCompletedTopicsCountAttribute();

        return round(($completed / $total) * 100, 2);
    }

    public function getCanCompleteAttribute(): bool
    {
        // Simplified logic - can be completed if all required topics are done
        return true; // Will implement proper logic later
    }

    /**
     * Get the subject that owns the unit.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the topics for this unit.
     */
    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    /**
     * Scope to get units for a specific subject
     */
    public function scopeForSubject($query, int $subjectId)
    {
        return $query->where('subject_id', $subjectId)->orderBy('target_completion_date');
    }

    /**
     * Compatibility methods for existing controllers
     */
    public static function forSubject(int $subjectId): Collection
    {
        return self::where('subject_id', $subjectId)
            ->orderBy('target_completion_date')
            ->get();
    }

    // Override find to support string IDs for compatibility
    public static function find($id, $columns = ['*'])
    {
        return static::query()->find((int) $id, $columns);
    }

    // The save() and delete() methods are now handled by Eloquent automatically

    /**
     * Get count of topics in this unit
     */
    public function getTopicCount(): int
    {
        return $this->topics()->count();
    }

    /**
     * Compatibility method for controllers
     */
    public function getTopicCount_compat(): int
    {
        return $this->getTopicCount();
    }

    /**
     * Get progress statistics for this unit
     */
    public function getProgressStats(): array
    {
        $topics = $this->topics;
        $totalTopics = $topics->count();
        $requiredTopics = $topics->where('required', true)->count();

        return [
            'total_topics' => $totalTopics,
            'required_topics' => $requiredTopics,
            'optional_topics' => $totalTopics - $requiredTopics,
            'estimated_hours' => round($topics->sum('estimated_minutes') / 60, 1),
            'completed_topics_count' => $this->completed_topics_count,
            'completion_percentage' => $this->completion_percentage,
            'can_complete' => $this->can_complete,
        ];
    }

    /**
     * Get detailed progress for a specific child
     */
    public function getProgressForChild(int $childId): array
    {
        $topics = $this->topics()->get();
        $requiredTopics = $topics->where('required', true);

        // Get sessions for this unit's topics and child
        $completedSessions = collect([]);
        foreach ($topics as $topic) {
            /** @var \App\Models\Topic $topic */
            // Session model not yet converted to Eloquent - skip this for now
            // $sessions = Session::where('topic_id', $topic->id)
            //     ->where('child_id', $childId)
            //     ->where('status', 'done');
            $sessions = collect(); // Empty collection until Session model is converted
            $completedSessions = $completedSessions->merge($sessions);
        }

        $completedTopicIds = $completedSessions->pluck('topic_id')->unique();
        $completedRequiredTopics = $requiredTopics->whereIn('id', $completedTopicIds);

        $completionPercentage = $requiredTopics->count() > 0
            ? ($completedRequiredTopics->count() / $requiredTopics->count()) * 100
            : 0;

        return [
            'total_topics' => $topics->count(),
            'required_topics' => $requiredTopics->count(),
            'completed_topics' => $completedTopicIds->count(),
            'completed_required_topics' => $completedRequiredTopics->count(),
            'completion_percentage' => round($completionPercentage, 2),
            'can_complete' => $completedRequiredTopics->count() >= $requiredTopics->count(),
            'remaining_required' => $requiredTopics->count() - $completedRequiredTopics->count(),
            'topics_breakdown' => [
                'completed' => $completedTopicIds->toArray(),
                'required_remaining' => $requiredTopics->whereNotIn('id', $completedTopicIds)->pluck('id')->toArray(),
                'optional_remaining' => $topics->where('required', false)->whereNotIn('id', $completedTopicIds)->pluck('id')->toArray(),
            ],
        ];
    }

    /**
     * Get progress bar data for UI display
     */
    public function getProgressBarData(int $childId): array
    {
        $progress = $this->getProgressForChild($childId);

        $color = 'bg-gray-200';
        if ($progress['completion_percentage'] >= 100) {
            $color = 'bg-green-500';
        } elseif ($progress['completion_percentage'] >= 75) {
            $color = 'bg-blue-500';
        } elseif ($progress['completion_percentage'] >= 50) {
            $color = 'bg-yellow-500';
        } elseif ($progress['completion_percentage'] >= 25) {
            $color = 'bg-orange-500';
        } else {
            $color = 'bg-red-500';
        }

        return [
            'percentage' => $progress['completion_percentage'],
            'color' => $color,
            'text' => $progress['completed_required_topics'].'/'.$progress['required_topics'].' required topics',
            'status' => $progress['can_complete'] ? 'complete' : 'in_progress',
            'total_sessions' => $progress['completed_topics'],
        ];
    }

    /**
     * Check if unit meets completion gate requirements
     */
    public function meetsCompletionGate(int $childId): bool
    {
        $progress = $this->getProgressForChild($childId);

        return $progress['can_complete'];
    }

    /**
     * Get completion status label
     */
    public function getCompletionStatus(int $childId): string
    {
        $progress = $this->getProgressForChild($childId);

        if ($progress['can_complete']) {
            return 'Complete';
        } elseif ($progress['completion_percentage'] >= 75) {
            return 'Nearly Complete';
        } elseif ($progress['completion_percentage'] >= 50) {
            return 'In Progress';
        } elseif ($progress['completion_percentage'] >= 25) {
            return 'Started';
        } else {
            return 'Not Started';
        }
    }

    /**
     * Get next topics that should be worked on
     */
    public function getNextTopics(int $childId, int $limit = 3): Collection
    {
        $progress = $this->getProgressForChild($childId);
        $topics = $this->topics()->get();

        // Get topics that are not yet completed
        $remainingTopics = $topics->whereNotIn('id', $progress['topics_breakdown']['completed']);

        // Prioritize required topics first
        $requiredRemaining = $remainingTopics->where('required', true);
        $optionalRemaining = $remainingTopics->where('required', false);

        // Combine with required first
        $nextTopics = $requiredRemaining->merge($optionalRemaining)->take($limit);

        return $nextTopics;
    }

    /**
     * Check if this unit is overdue
     */
    public function isOverdue(): bool
    {
        return $this->target_completion_date
            && $this->target_completion_date->isPast();
    }

    /**
     * Get days until target completion
     */
    public function getDaysUntilTarget(): ?int
    {
        if (! $this->target_completion_date) {
            return null;
        }

        return (int) now()->diffInDays($this->target_completion_date, false);
    }

    /**
     * Get direct flashcards relationship (topic-independent)
     * In the topic-only architecture, this returns empty for backward compatibility
     * because all flashcards must have a topic_id (database constraint)
     */
    public function flashcards(): HasMany
    {
        // Return empty relationship as there are no unit-level flashcards in topic-only architecture
        // topic_id is now required (NOT NULL), so whereNull('topic_id') will always return empty
        return $this->hasMany(Flashcard::class, 'unit_id')
            ->whereNull('topic_id') // This will always be empty in topic-only architecture
            ->where('is_active', true);
    }

    /**
     * Get all flashcards relationship (unit + topic flashcards)
     * In the topic-only architecture, this returns all topic flashcards
     */
    public function allFlashcards()
    {
        // In topic-only architecture, get flashcards from all topics within this unit
        // This returns a HasManyThrough relationship through topics
        return $this->hasManyThrough(
            Flashcard::class,
            Topic::class,
            'unit_id', // Foreign key on topics table
            'topic_id', // Foreign key on flashcards table
            'id', // Local key on units table
            'id' // Local key on topics table
        )->where('flashcards.is_active', true)
            ->select('flashcards.*'); // Explicitly select flashcards columns to avoid ambiguity
    }

    /**
     * Get count of flashcards directly attached to this unit (topic-independent)
     * In the topic-only architecture, this always returns 0 for backward compatibility
     */
    public function getDirectFlashcardsCount(): int
    {
        return 0; // In topic-only architecture, there are no unit-level flashcards
    }

    /**
     * Get count of flashcards in topics only
     * In the topic-only architecture, this is the same as getAllFlashcardsCount
     */
    public function getTopicFlashcardsCount(): int
    {
        return $this->getAllFlashcardsCount();
    }

    /**
     * Get total count of all flashcards in this unit (across all topics)
     * In the topic-only architecture, this sums flashcards from all topics
     */
    public function getAllFlashcardsCount(): int
    {
        // Check if count was preloaded to avoid N+1 queries
        if (isset($this->attributes['all_flashcards_count'])) {
            return (int) $this->attributes['all_flashcards_count'];
        }

        return $this->topics()
            ->withCount('flashcards')
            ->get()
            ->sum('flashcards_count');
    }

    /**
     * Check if unit has any flashcards (direct or topic)
     */
    public function hasAnyFlashcards(): bool
    {
        return $this->getAllFlashcardsCount() > 0;
    }

    /**
     * Check if unit has direct flashcards (topic-independent)
     * In the topic-only architecture, this always returns false
     */
    public function hasDirectFlashcards(): bool
    {
        return false; // In topic-only architecture, there are no unit-level flashcards
    }

    /**
     * Check if unit has topic flashcards
     * In the topic-only architecture, this is the same as hasAnyFlashcards
     */
    public function hasTopicFlashcards(): bool
    {
        return $this->hasAnyFlashcards();
    }

    /**
     * Get flashcard counts for multiple units efficiently
     */
    public static function getFlashcardCountsForUnits($units): array
    {
        if ($units->isEmpty()) {
            return [];
        }

        $unitIds = $units->pluck('id')->toArray();

        // Get flashcard counts grouped by unit_id
        $counts = Flashcard::whereIn('unit_id', $unitIds)
            ->where('is_active', true)
            ->selectRaw('unit_id, COUNT(*) as count')
            ->groupBy('unit_id')
            ->pluck('count', 'unit_id')
            ->toArray();

        // Ensure all units are represented, even with 0 count
        $result = [];
        foreach ($unitIds as $unitId) {
            $result[$unitId] = $counts[$unitId] ?? 0;
        }

        return $result;
    }

    /**
     * Convert the model instance to an array.
     *
     * Note: This method optimizes for performance by checking for pre-loaded counts
     * and avoiding N+1 queries when possible. For collections, consider using:
     * Unit::withCount(['allFlashcards', 'flashcards', 'topics'])->get() before serialization.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'subject_id' => $this->subject_id,
            'name' => $this->name,
            'description' => $this->description,
            'target_completion_date' => $this->target_completion_date?->format('Y-m-d'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'days_until_target' => $this->getDaysUntilTarget(),
            'completed_topics_count' => $this->completed_topics_count,
            'total_topics_count' => isset($this->attributes['topics_count'])
                ? (int) $this->attributes['topics_count']
                : null,
            'completion_percentage' => $this->completion_percentage,
            'can_complete' => $this->can_complete,
        ];
    }
}
