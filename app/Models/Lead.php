<?php

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['owner_id', 'name', 'phone', 'email', 'source', 'status', 'note'])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, SoftDeletes;

    public const SORTABLE = ['created_at', 'updated_at', 'name', 'status'];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'source' => LeadSource::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('owner_id', $userId);
    }

    public function scopeSearch(Builder $query, ?string $q): Builder
    {
        if (blank($q)) {
            return $query;
        }

        return $query->where('name', 'ILIKE', '%'.$q.'%');
    }

    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeFilterSource(Builder $query, ?string $source): Builder
    {
        if (blank($source)) {
            return $query;
        }

        return $query->where('source', $source);
    }

    /**
     * Apply a user-supplied sort expression. Accepts "field" or "-field"
     * (prefix minus for descending). Falls back to created_at desc for
     * invalid or missing values.
     */
    public function scopeSortBy(Builder $query, ?string $sort): Builder
    {
        $direction = 'asc';
        $field = $sort;

        if (is_string($sort) && str_starts_with($sort, '-')) {
            $direction = 'desc';
            $field = substr($sort, 1);
        }

        if (! in_array($field, self::SORTABLE, true)) {
            return $query->orderByDesc('created_at');
        }

        return $query->orderBy($field, $direction);
    }
}
