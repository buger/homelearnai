<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $topic_id
 * @property string $card_type
 * @property string $question
 * @property string $answer
 * @property string|null $hint
 * @property array|null $choices
 * @property array|null $correct_choices
 * @property string|null $cloze_text
 * @property array|null $cloze_answers
 * @property string|null $question_image_url
 * @property string|null $answer_image_url
 * @property array|null $occlusion_data
 * @property string $difficulty_level
 * @property array|null $tags
 * @property bool $is_active
 * @property string|null $import_source
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read \App\Models\Topic $topic
 * @property-read \App\Models\Unit $unit
 * @property-read \App\Models\Subject $subject
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $reviews
 */
class Flashcard extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'unit_id',
        'topic_id',
        'card_type',
        'question',
        'answer',
        'hint',
        'choices',
        'correct_choices',
        'cloze_text',
        'cloze_answers',
        'question_image_url',
        'answer_image_url',
        'occlusion_data',
        'difficulty_level',
        'tags',
        'is_active',
        'import_source',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'unit_id' => 'integer',
        'topic_id' => 'integer',
        'choices' => 'array',
        'correct_choices' => 'array',
        'cloze_answers' => 'array',
        'occlusion_data' => 'array',
        'tags' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'card_type' => 'basic',
        'difficulty_level' => 'medium',
        'is_active' => true,
        'choices' => '[]',
        'correct_choices' => '[]',
        'cloze_answers' => '[]',
        'tags' => '[]',
        'occlusion_data' => '[]',
    ];

    /**
     * Card type constants
     */
    public const CARD_TYPE_BASIC = 'basic';

    public const CARD_TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    public const CARD_TYPE_TRUE_FALSE = 'true_false';

    public const CARD_TYPE_CLOZE = 'cloze';

    public const CARD_TYPE_TYPED_ANSWER = 'typed_answer';

    public const CARD_TYPE_IMAGE_OCCLUSION = 'image_occlusion';

    /**
     * Difficulty level constants
     */
    public const DIFFICULTY_EASY = 'easy';

    public const DIFFICULTY_MEDIUM = 'medium';

    public const DIFFICULTY_HARD = 'hard';

    /**
     * Get all valid card types
     */
    public static function getCardTypes(): array
    {
        return [
            self::CARD_TYPE_BASIC,
            self::CARD_TYPE_MULTIPLE_CHOICE,
            self::CARD_TYPE_TRUE_FALSE,
            self::CARD_TYPE_CLOZE,
            self::CARD_TYPE_TYPED_ANSWER,
            self::CARD_TYPE_IMAGE_OCCLUSION,
        ];
    }

    /**
     * Get all valid difficulty levels
     */
    public static function getDifficultyLevels(): array
    {
        return [
            self::DIFFICULTY_EASY,
            self::DIFFICULTY_MEDIUM,
            self::DIFFICULTY_HARD,
        ];
    }

    /**
     * Get the topic that owns the flashcard.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * Get the unit that owns the flashcard.
     * This can be either direct (for backward compatibility) or through topic.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the subject through the topic -> unit chain.
     */
    public function subject()
    {
        return $this->topic->unit->subject ?? null;
    }

    /**
     * Get the reviews for this flashcard.
     * Note: Review model not yet converted to Eloquent, so this relationship is not functional yet.
     */
    public function reviews(): HasMany
    {
        // Review class doesn't exist as an Eloquent model yet - this is a placeholder
        return $this->hasMany(\App\Models\Flashcard::class, 'flashcard_id'); // Temporary placeholder
    }

    /**
     * Scope to get flashcards for a specific topic
     */
    public function scopeForTopic($query, int $topicId)
    {
        return $query->where('topic_id', $topicId)->where('is_active', true)->orderBy('created_at');
    }

    /**
     * Scope to get flashcards by card type
     */
    public function scopeByCardType($query, string $cardType)
    {
        return $query->where('card_type', $cardType);
    }

    /**
     * Scope to get flashcards by difficulty level
     */
    public function scopeByDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty_level', $difficulty);
    }

    /**
     * Scope to get active flashcards only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if this flashcard requires multiple choice data
     */
    public function requiresMultipleChoiceData(): bool
    {
        return in_array($this->card_type, [
            self::CARD_TYPE_MULTIPLE_CHOICE,
            self::CARD_TYPE_TRUE_FALSE,
        ]);
    }

    /**
     * Check if this flashcard requires cloze data
     */
    public function requiresClozeData(): bool
    {
        return $this->card_type === self::CARD_TYPE_CLOZE;
    }

    /**
     * Check if this flashcard requires image data
     */
    public function requiresImageData(): bool
    {
        return $this->card_type === self::CARD_TYPE_IMAGE_OCCLUSION;
    }

    /**
     * Validate the flashcard data based on card type
     */
    public function validateCardData(): array
    {
        $errors = [];

        switch ($this->card_type) {
            case self::CARD_TYPE_MULTIPLE_CHOICE:
                if (empty($this->choices) || count($this->choices) < 2) {
                    $errors[] = 'Multiple choice cards must have at least 2 choices';
                }
                if (empty($this->correct_choices)) {
                    $errors[] = 'Multiple choice cards must have correct choices specified';
                }
                break;

            case self::CARD_TYPE_TRUE_FALSE:
                if (empty($this->choices) || count($this->choices) !== 2) {
                    $errors[] = 'True/false cards must have exactly 2 choices';
                }
                break;

            case self::CARD_TYPE_CLOZE:
                if (empty($this->cloze_text)) {
                    $errors[] = 'Cloze deletion cards must have cloze text';
                }
                if (empty($this->cloze_answers)) {
                    $errors[] = 'Cloze deletion cards must have cloze answers';
                }
                break;

            case self::CARD_TYPE_IMAGE_OCCLUSION:
                if (empty($this->question_image_url)) {
                    $errors[] = 'Image occlusion cards must have a question image';
                }
                if (empty($this->occlusion_data)) {
                    $errors[] = 'Image occlusion cards must have occlusion data';
                }
                break;
        }

        return $errors;
    }

    /**
     * Check if user can access this flashcard
     */
    public function canBeAccessedBy(string $userId): bool
    {
        // If topic_id is set, check through topic -> unit -> subject
        if ($this->topic_id && $this->topic) {
            return $this->topic->unit->subject->user_id === $userId;
        }

        // Fallback to unit -> subject
        return $this->unit->subject->user_id === $userId;
    }

    /**
     * Compatibility methods for controllers that still use legacy patterns
     * Returns only unit-scoped flashcards (excludes topic flashcards)
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Flashcard>
     */
    public static function forUnit(int $unitId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('unit_id', $unitId)
            ->whereNull('topic_id') // Only unit-scoped flashcards
            ->where('is_active', true)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Get flashcards for a specific topic
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Flashcard>
     */
    public static function forTopic(int $topicId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('topic_id', $topicId)->where('is_active', true)->orderBy('created_at')->get();
    }

    // Override find to support string IDs for compatibility
    public static function find($id, $columns = ['*'])
    {
        return static::query()->find((int) $id, $columns);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'topic_id' => $this->topic_id,
            'card_type' => $this->card_type,
            'question' => $this->question,
            'answer' => $this->answer,
            'hint' => $this->hint,
            'choices' => $this->choices,
            'correct_choices' => $this->correct_choices,
            'cloze_text' => $this->cloze_text,
            'cloze_answers' => $this->cloze_answers,
            'question_image_url' => $this->question_image_url,
            'answer_image_url' => $this->answer_image_url,
            'occlusion_data' => $this->occlusion_data,
            'difficulty_level' => $this->difficulty_level,
            'tags' => $this->tags,
            'is_active' => $this->is_active,
            'import_source' => $this->import_source,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'requires_multiple_choice_data' => $this->requiresMultipleChoiceData(),
            'requires_cloze_data' => $this->requiresClozeData(),
            'requires_image_data' => $this->requiresImageData(),
        ];
    }
}
