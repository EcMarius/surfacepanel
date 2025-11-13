<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailFilterAction extends Model
{
    use HasFactory;

    protected $table = 'vp_email_filter_actions';

    protected $fillable = [
        'filter_id',
        'action_type',
        'action_value',
        'vacation_message',
        'vacation_subject',
        'vacation_days',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
        'vacation_days' => 'integer',
    ];

    /**
     * Get the filter that owns this action
     */
    public function filter(): BelongsTo
    {
        return $this->belongsTo(EmailFilter::class, 'filter_id');
    }

    /**
     * Get available action types
     */
    public static function getAvailableActionTypes(): array
    {
        return [
            'move_to_folder' => 'Move to Folder',
            'forward' => 'Forward to Email',
            'redirect' => 'Redirect to Email',
            'delete' => 'Delete Message',
            'reject' => 'Reject with Message',
            'discard' => 'Discard Silently',
            'mark_as_read' => 'Mark as Read',
            'flag' => 'Flag Message',
            'pipe_to_program' => 'Pipe to Program',
            'vacation' => 'Vacation Auto-Reply',
            'stop' => 'Stop Processing',
        ];
    }

    /**
     * Get actions that require a value
     */
    public static function getActionsRequiringValue(): array
    {
        return [
            'move_to_folder',
            'forward',
            'redirect',
            'reject',
            'pipe_to_program',
        ];
    }

    /**
     * Get actions that support vacation settings
     */
    public static function getVacationActions(): array
    {
        return ['vacation'];
    }

    /**
     * Check if this action requires a value
     */
    public function requiresValue(): bool
    {
        return in_array($this->action_type, self::getActionsRequiringValue());
    }

    /**
     * Check if this action is a vacation auto-reply
     */
    public function isVacation(): bool
    {
        return $this->action_type === 'vacation';
    }

    /**
     * Check if this action stops processing
     */
    public function stopsProcessing(): bool
    {
        return in_array($this->action_type, ['stop', 'delete', 'discard', 'reject']);
    }

    /**
     * Get a human-readable description of this action
     */
    public function getDescription(): string
    {
        $types = self::getAvailableActionTypes();
        $actionName = $types[$this->action_type] ?? $this->action_type;

        if ($this->isVacation()) {
            return "{$actionName}: \"{$this->vacation_subject}\"";
        }

        if ($this->requiresValue() && $this->action_value) {
            return "{$actionName}: {$this->action_value}";
        }

        return $actionName;
    }

    /**
     * Validate action configuration
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->requiresValue() && empty($this->action_value)) {
            $errors[] = "Action '{$this->action_type}' requires a value";
        }

        if ($this->isVacation()) {
            if (empty($this->vacation_subject)) {
                $errors[] = "Vacation auto-reply requires a subject";
            }
            if (empty($this->vacation_message)) {
                $errors[] = "Vacation auto-reply requires a message";
            }
            if ($this->vacation_days && ($this->vacation_days < 1 || $this->vacation_days > 30)) {
                $errors[] = "Vacation days must be between 1 and 30";
            }
        }

        if ($this->action_type === 'forward' || $this->action_type === 'redirect') {
            if ($this->action_value && !filter_var($this->action_value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email address: {$this->action_value}";
            }
        }

        if ($this->action_type === 'move_to_folder') {
            if ($this->action_value && !$this->isValidFolderName($this->action_value)) {
                $errors[] = "Invalid folder name: {$this->action_value}";
            }
        }

        return $errors;
    }

    /**
     * Check if folder name is valid
     */
    private function isValidFolderName(string $folder): bool
    {
        // Basic validation: no special characters except dots, underscores, and hyphens
        return preg_match('/^[a-zA-Z0-9._-]+$/', $folder) === 1;
    }

    /**
     * Scope to order actions
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }
}
