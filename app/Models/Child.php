<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property string $grade
 * @property int $user_id
 * @property int $independence_level
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Support\Collection $timeBlocks
 */
class Child extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'grade',
        'user_id',
        'independence_level',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'grade' => 'string',
        'independence_level' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'independence_level' => 1,
    ];

    /**
     * Get the user that owns the child.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subjects for this child.
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    /**
     * Get time blocks for this child.
     */
    public function timeBlocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TimeBlock::class);
    }

    /**
     * Accessor for timeBlocks attribute (backward compatibility)
     */
    public function getTimeBlocksAttribute(): Collection
    {
        // Return empty collection until TimeBlock is converted to Eloquent
        return collect();
    }

    /**
     * Scope to get children for a specific user
     */
    public function scopeForUser($query, int|string $userId)
    {
        return $query->where('user_id', $userId)->orderBy('name');
    }

    /**
     * Static method for backward compatibility with controllers that pass SupabaseClient
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Child>
     */
    public static function forUser(int|string $userId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('user_id', $userId)->orderBy('name')->get();
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::created(function (Child $child) {
            // TODO: Create default time blocks when TimeBlock is converted to Eloquent
            // $child->createDefaultTimeBlocks();
        });
    }

    /**
     * Get grade group for appropriate content selection
     */
    public function getGradeGroup(): string
    {
        return match ($this->grade) {
            'PK', 'PreK', 'K', 'Kindergarten' => 'preschool',
            '1st', '2nd', '3rd', '4th', '5th' => 'elementary',
            '6th', '7th', '8th' => 'middle_school',
            '9th', '10th', '11th', '12th' => 'high_school',
            default => $this->getGradeGroupByPattern(),
        };
    }

    /**
     * Get grade group by pattern matching for flexibility
     */
    private function getGradeGroupByPattern(): string
    {
        $grade = strtolower($this->grade);

        if (str_contains($grade, 'pre') || str_contains($grade, 'pk') || $grade === 'k') {
            return 'preschool';
        } elseif (str_contains($grade, '1') || str_contains($grade, '2') || str_contains($grade, '3') || str_contains($grade, '4') || str_contains($grade, '5')) {
            return 'elementary';
        } elseif (str_contains($grade, '6') || str_contains($grade, '7') || str_contains($grade, '8')) {
            return 'middle_school';
        } elseif (str_contains($grade, '9') || str_contains($grade, '10') || str_contains($grade, '11') || str_contains($grade, '12')) {
            return 'high_school';
        }

        return 'elementary'; // default fallback
    }

    /**
     * Get available grade options
     */
    public static function getGradeOptions(): array
    {
        return [
            'PreK' => __('Pre-Kindergarten'),
            'K' => __('Kindergarten'),
            '1st' => __('1st Grade'),
            '2nd' => __('2nd Grade'),
            '3rd' => __('3rd Grade'),
            '4th' => __('4th Grade'),
            '5th' => __('5th Grade'),
            '6th' => __('6th Grade'),
            '7th' => __('7th Grade'),
            '8th' => __('8th Grade'),
            '9th' => __('9th Grade (Freshman)'),
            '10th' => __('10th Grade (Sophomore)'),
            '11th' => __('11th Grade (Junior)'),
            '12th' => __('12th Grade (Senior)'),
        ];
    }

    /**
     * Get independence level label
     */
    public function getIndependenceLevelLabel(): string
    {
        return match ($this->independence_level) {
            1 => 'Guided (View Only)',
            2 => 'Basic (Reorder Tasks)',
            3 => 'Intermediate (Move Within Week)',
            4 => 'Advanced (Plan Proposals)',
            default => 'Guided'
        };
    }

    /**
     * Check if child can reorder today's tasks
     */
    public function canReorderTasks(): bool
    {
        return $this->independence_level >= 2;
    }

    /**
     * Check if child can move sessions within week
     */
    public function canMoveSessionsInWeek(): bool
    {
        return $this->independence_level >= 3;
    }

    /**
     * Check if child can propose weekly plans
     */
    public function canProposeWeeklyPlans(): bool
    {
        return $this->independence_level >= 4;
    }

    /**
     * Check if child has view-only access
     */
    public function isViewOnlyMode(): bool
    {
        return $this->independence_level === 1;
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        // Add computed attributes
        $array['independence_level_label'] = $this->getIndependenceLevelLabel();
        $array['can_reorder_tasks'] = $this->canReorderTasks();
        $array['can_move_sessions_in_week'] = $this->canMoveSessionsInWeek();
        $array['can_propose_weekly_plans'] = $this->canProposeWeeklyPlans();
        $array['is_view_only_mode'] = $this->isViewOnlyMode();
        $array['grade_group'] = $this->getGradeGroup();

        // Format timestamps
        if ($this->created_at) {
            $array['created_at'] = $this->created_at->toIso8601String();
        }
        if ($this->updated_at) {
            $array['updated_at'] = $this->updated_at->toIso8601String();
        }

        return $array;
    }
}
