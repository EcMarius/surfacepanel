<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModalFunctionVersion extends Model
{
    use HasFactory;

    protected $table = 'vp_modal_function_versions';

    protected $fillable = [
        'function_id',
        'version',
        'code',
        'requirements',
        'environment_variables',
        'configuration',
        'changelog',
        'is_active',
        'deployed_at',
    ];

    protected $casts = [
        'requirements' => 'array',
        'environment_variables' => 'array',
        'configuration' => 'array',
        'is_active' => 'boolean',
        'deployed_at' => 'datetime',
    ];

    /**
     * Get the function that owns this version
     */
    public function function(): BelongsTo
    {
        return $this->belongsTo(ModalFunction::class, 'function_id');
    }

    /**
     * Scope for active version
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for versions by function
     */
    public function scopeForFunction($query, $functionId)
    {
        return $query->where('function_id', $functionId);
    }

    /**
     * Get the active version for a function
     */
    public static function getActiveVersion($functionId): ?self
    {
        return self::forFunction($functionId)
            ->active()
            ->first();
    }
}
