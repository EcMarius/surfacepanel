<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailFilter extends Model
{
    use HasFactory;

    protected $table = 'vp_email_filters';

    protected $fillable = [
        'account_id',
        'email',
        'name',
        'description',
        'priority',
        'is_active',
        'match_type',
        'stop_processing',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'stop_processing' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Get the account that owns the filter
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the conditions for this filter
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(EmailFilterCondition::class, 'filter_id');
    }

    /**
     * Get the actions for this filter
     */
    public function actions(): HasMany
    {
        return $this->hasMany(EmailFilterAction::class, 'filter_id')->orderBy('order');
    }

    /**
     * Scope to get active filters only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get filters for a specific email address
     */
    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Scope to order filters by priority
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }

    /**
     * Check if this filter matches all conditions (AND logic)
     */
    public function matchesAll(): bool
    {
        return $this->match_type === 'all';
    }

    /**
     * Check if this filter matches any condition (OR logic)
     */
    public function matchesAny(): bool
    {
        return $this->match_type === 'any';
    }

    /**
     * Get a summary of the filter
     */
    public function getSummary(): string
    {
        $conditionCount = $this->conditions()->count();
        $actionCount = $this->actions()->count();

        return sprintf(
            '%s: %d condition%s, %d action%s',
            $this->name,
            $conditionCount,
            $conditionCount === 1 ? '' : 's',
            $actionCount,
            $actionCount === 1 ? '' : 's'
        );
    }

    /**
     * Duplicate this filter with all conditions and actions
     */
    public function duplicate(string $newName): self
    {
        $newFilter = $this->replicate();
        $newFilter->name = $newName;
        $newFilter->save();

        // Duplicate conditions
        foreach ($this->conditions as $condition) {
            $newCondition = $condition->replicate();
            $newCondition->filter_id = $newFilter->id;
            $newCondition->save();
        }

        // Duplicate actions
        foreach ($this->actions as $action) {
            $newAction = $action->replicate();
            $newAction->filter_id = $newFilter->id;
            $newAction->save();
        }

        return $newFilter;
    }
}
