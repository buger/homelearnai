<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Subject extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'color',
        'user_id',
        'child_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'user_id' => 'string',
        'child_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'color' => '#3b82f6',
    ];

    /**
     * Get the user that owns the subject.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the child that owns the subject (if any).
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    /**
     * Get the units for this subject.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Get all topics for this subject through units.
     */
    public function topics()
    {
        return $this->hasManyThrough(Topic::class, Unit::class);
    }

    /**
     * Get all sessions for this subject through topics.
     * Note: Session is still using Supabase pattern, so this relationship is not functional yet
     */
    public function sessions()
    {
        // Cannot use hasManyThrough with non-Eloquent Session model
        // return $this->hasManyThrough(\App\Models\Session::class, \App\Models\Topic::class);
        throw new \BadMethodCallException('Session relationship not yet available - Session model is still using Supabase pattern');
    }

    /**
     * Scope to get subjects for a specific user
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId)->orderBy('name');
    }

    /**
     * Scope to get subjects for a specific child
     */
    public function scopeForChild($query, int $childId)
    {
        return $query->where('child_id', $childId)->orderBy('name');
    }

    /**
     * Compatibility methods for existing controllers that expect SupabaseClient
     * These maintain API compatibility during migration
     */
    public static function forUser(string $userId): Collection
    {
        return self::where('user_id', $userId)->orderBy('name')->get();
    }

    public static function forChild(int $childId): Collection
    {
        return self::where('child_id', $childId)->orderBy('name')->get();
    }

    // Override find to support string IDs for compatibility
    public static function find($id, $columns = ['*'])
    {
        return static::query()->find((int) $id, $columns);
    }

    /**
     * Get count of units in this subject
     */
    public function getUnitCount(): int
    {
        return $this->units()->count();
    }

    /**
     * Compatibility method for controllers that still pass SupabaseClient
     */
    public function getUnitCount_compat(): int
    {
        return $this->getUnitCount();
    }

    /**
     * Validate color is valid hex format
     */
    public static function validateColor(string $color): bool
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
    }

    /**
     * Get predefined color options for subjects
     */
    public static function getColorOptions(): array
    {
        return [
            '#ef4444' => 'Red',
            '#f59e0b' => 'Orange',
            '#eab308' => 'Yellow',
            '#10b981' => 'Green',
            '#3b82f6' => 'Blue',
            '#06b6d4' => 'Cyan',
            '#6366f1' => 'Indigo',
            '#8b5cf6' => 'Purple',
            '#ec4899' => 'Pink',
            '#6b7280' => 'Gray',
            '#14b8a6' => 'Teal',
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'user_id' => $this->user_id,
            'child_id' => $this->child_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
