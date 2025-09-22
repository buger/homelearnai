<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $unit_id
 * @property string $title
 * @property string|null $description
 * @property string|null $learning_content
 * @property array|null $content_assets
 * @property int $estimated_minutes
 * @property array|null $prerequisites
 * @property bool $required
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \App\Models\Unit $unit
 * @property-read \App\Models\Subject $subject
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $sessions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Flashcard> $flashcards
 * @property-read int $flashcards_count
 */
class Topic extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'unit_id',
        'title',
        'description',
        'learning_content',
        'content_assets',
        'estimated_minutes',
        'prerequisites',
        'required',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'unit_id' => 'integer',
        'estimated_minutes' => 'integer',
        'content_assets' => 'array',
        'prerequisites' => 'array',
        'required' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'estimated_minutes' => 30,
        'learning_content' => '',
        'content_assets' => null,
        'prerequisites' => '[]',
        'required' => true,
    ];

    /**
     * Get the unit that owns the topic.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the sessions for this topic.
     * Note: Session is still using Supabase pattern, so this relationship is not functional yet
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    /**
     * Get the flashcards for this topic.
     */
    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class)->where('is_active', true);
    }

    /**
     * Get the subject through the unit.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'id', 'id')
            ->join('units', 'units.subject_id', '=', 'subjects.id')
            ->where('units.id', $this->unit_id);
    }

    /**
     * Get topic content for display (unified markdown content)
     */
    public function getContent(): string
    {
        return $this->learning_content ?? '';
    }

    /**
     * Get content assets for file management
     */
    public function getContentAssets(): array
    {
        return $this->content_assets ?? ['images' => [], 'files' => []];
    }

    /**
     * Check if topic has content assets
     */
    public function hasContentAssets(): bool
    {
        $assets = $this->getContentAssets();

        return ! empty($assets['images']) || ! empty($assets['files']);
    }

    /**
     * Check if topic has learning materials (unified content)
     */
    public function hasLearningMaterials(): bool
    {
        return ! empty($this->learning_content);
    }

    /**
     * Get count of learning materials for this topic
     */
    public function getLearningMaterialsCount(): int
    {
        // Count non-empty content assets
        $assets = $this->content_assets ?? [];
        $count = 0;

        if (! empty($this->learning_content)) {
            $count++;
        }

        $count += count($assets);

        return $count;
    }

    /**
     * Check if topic has rich content
     */
    public function hasRichContent(): bool
    {
        return $this->hasLearningMaterials();
    }

    /**
     * Get unified content (simplified system)
     */
    public function getUnifiedContent(): string
    {
        return $this->learning_content ?? '';
    }

    /**
     * Update content assets
     */
    public function updateContentAssets(array $assets): void
    {
        $this->content_assets = array_merge($this->getContentAssets(), $assets);
        $this->save();
    }

    /**
     * Get estimated reading time based on content
     */
    public function getEstimatedReadingTime(): int
    {
        $content = $this->getContent();
        $wordCount = str_word_count(strip_tags($content));

        // Average reading speed: 200 words per minute
        $readingTime = ceil($wordCount / 200);

        // Minimum 1 minute, add time for multimedia content
        $baseTime = max(1, $readingTime);

        // Add time for assets
        $assets = $this->getContentAssets();
        $imageTime = count($assets['images'] ?? []) * 0.5; // 30 seconds per image
        $fileTime = count($assets['files'] ?? []) * 1; // 1 minute per file

        return ceil($baseTime + $imageTime + $fileTime);
    }

    /**
     * Scope to get topics for a specific unit
     */
    public function scopeForUnit($query, int $unitId)
    {
        return $query->where('unit_id', $unitId)->orderBy('created_at');
    }

    /**
     * Scope to get required topics
     */
    public function scopeRequired($query)
    {
        return $query->where('required', true);
    }

    /**
     * Scope to get optional topics
     */
    public function scopeOptional($query)
    {
        return $query->where('required', false);
    }

    /**
     * Scope to get topics with flashcards
     */
    public function scopeWithFlashcards($query)
    {
        return $query->whereHas('flashcards');
    }

    /**
     * Check if this topic has prerequisites
     */
    public function hasPrerequisites(): bool
    {
        return ! empty($this->prerequisites);
    }

    /**
     * Get count of flashcards for this topic
     */
    public function getFlashcardsCount(): int
    {
        return $this->flashcards()->count();
    }

    /**
     * Check if topic has flashcards
     */
    public function hasFlashcards(): bool
    {
        return $this->flashcards()->exists();
    }

    /**
     * Get prerequisite topics
     */
    public function getPrerequisiteTopics()
    {
        if (! $this->hasPrerequisites()) {
            return collect();
        }

        return self::whereIn('id', $this->prerequisites)->get();
    }

    /**
     * Check if prerequisites are met for a given child
     */
    public function prerequisitesMet(int $childId): bool
    {
        if (! $this->hasPrerequisites()) {
            return true;
        }

        // Check if all prerequisite topics have been completed
        // This would need Session model integration when available
        return true; // Placeholder for now
    }

    /**
     * Get topic complexity score based on content
     */
    public function getComplexityScore(): int
    {
        $content = $this->getContent();
        $score = 1; // Base score

        // Add points for length
        $wordCount = str_word_count(strip_tags($content));
        $score += min(3, floor($wordCount / 500)); // Max 3 points for length

        // Add points for assets
        $assets = $this->getContentAssets();
        $score += min(2, count($assets['images'] ?? [])); // Max 2 points for images
        $score += min(3, count($assets['files'] ?? [])); // Max 3 points for files

        // Add points for prerequisites
        if ($this->hasPrerequisites()) {
            $score += count($this->prerequisites);
        }

        return min(10, $score); // Cap at 10
    }

    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'title' => $this->title,
            'description' => $this->description,
            'learning_content' => $this->learning_content,
            'content_assets' => $this->content_assets,
            'estimated_minutes' => $this->estimated_minutes,
            'prerequisites' => $this->prerequisites,
            'required' => $this->required,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Computed properties
            'has_content_assets' => $this->hasContentAssets(),
            'estimated_reading_time' => $this->getEstimatedReadingTime(),
            'complexity_score' => $this->getComplexityScore(),
            'has_prerequisites' => $this->hasPrerequisites(),
            'flashcards_count' => $this->getFlashcardsCount(),
            'has_flashcards' => $this->hasFlashcards(),
        ];
    }

    /**
     * Compatibility methods for existing controllers
     */
    public static function forUnit(int $unitId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('unit_id', $unitId)->orderBy('created_at')->get();
    }

    // Override find to support string IDs for compatibility
    public static function find($id, $columns = ['*'])
    {
        return static::query()->find((int) $id, $columns);
    }

    /**
     * Get content for kids view (simplified)
     */
    public function getKidsContent(): string
    {
        return $this->getContent();
    }

    /**
     * Get content for parent/admin view (full)
     */
    public function getAdminContent(): string
    {
        return $this->getContent();
    }
}
