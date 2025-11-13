<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailFilterCondition extends Model
{
    use HasFactory;

    protected $table = 'vp_email_filter_conditions';

    protected $fillable = [
        'filter_id',
        'field',
        'header_name',
        'operator',
        'value',
        'case_sensitive',
    ];

    protected $casts = [
        'case_sensitive' => 'boolean',
    ];

    /**
     * Get the filter that owns this condition
     */
    public function filter(): BelongsTo
    {
        return $this->belongsTo(EmailFilter::class, 'filter_id');
    }

    /**
     * Get available fields for filtering
     */
    public static function getAvailableFields(): array
    {
        return [
            'from' => 'From Address',
            'to' => 'To Address',
            'subject' => 'Subject',
            'body' => 'Message Body',
            'header' => 'Custom Header',
            'size' => 'Message Size (KB)',
            'spam_score' => 'Spam Score',
            'recipient' => 'Recipient',
            'sender' => 'Sender',
        ];
    }

    /**
     * Get available operators
     */
    public static function getAvailableOperators(): array
    {
        return [
            'contains' => 'Contains',
            'not_contains' => 'Does Not Contain',
            'equals' => 'Equals',
            'not_equals' => 'Does Not Equal',
            'begins_with' => 'Begins With',
            'ends_with' => 'Ends With',
            'matches_regex' => 'Matches Regex',
            'greater_than' => 'Greater Than',
            'less_than' => 'Less Than',
        ];
    }

    /**
     * Get operators applicable for the current field
     */
    public function getApplicableOperators(): array
    {
        $numericFields = ['size', 'spam_score'];
        $textFields = ['from', 'to', 'subject', 'body', 'header', 'recipient', 'sender'];

        if (in_array($this->field, $numericFields)) {
            return [
                'equals' => 'Equals',
                'not_equals' => 'Does Not Equal',
                'greater_than' => 'Greater Than',
                'less_than' => 'Less Than',
            ];
        }

        if (in_array($this->field, $textFields)) {
            return [
                'contains' => 'Contains',
                'not_contains' => 'Does Not Contain',
                'equals' => 'Equals',
                'not_equals' => 'Does Not Equal',
                'begins_with' => 'Begins With',
                'ends_with' => 'Ends With',
                'matches_regex' => 'Matches Regex',
            ];
        }

        return self::getAvailableOperators();
    }

    /**
     * Get a human-readable description of this condition
     */
    public function getDescription(): string
    {
        $fields = self::getAvailableFields();
        $operators = self::getAvailableOperators();

        $field = $fields[$this->field] ?? $this->field;
        $operator = $operators[$this->operator] ?? $this->operator;
        $value = $this->value;

        if ($this->field === 'header' && $this->header_name) {
            $field = "Header: {$this->header_name}";
        }

        if ($this->field === 'size') {
            $value = "{$value} KB";
        }

        return "{$field} {$operator} \"{$value}\"";
    }

    /**
     * Test if this condition matches the given email data
     */
    public function matches(array $emailData): bool
    {
        $fieldValue = $this->getFieldValue($emailData);
        $compareValue = $this->value;

        // Handle case sensitivity
        if (!$this->case_sensitive && !in_array($this->field, ['size', 'spam_score'])) {
            $fieldValue = strtolower($fieldValue);
            $compareValue = strtolower($compareValue);
        }

        return match ($this->operator) {
            'contains' => str_contains($fieldValue, $compareValue),
            'not_contains' => !str_contains($fieldValue, $compareValue),
            'equals' => $fieldValue === $compareValue,
            'not_equals' => $fieldValue !== $compareValue,
            'begins_with' => str_starts_with($fieldValue, $compareValue),
            'ends_with' => str_ends_with($fieldValue, $compareValue),
            'matches_regex' => @preg_match($compareValue, $fieldValue) === 1,
            'greater_than' => (float)$fieldValue > (float)$compareValue,
            'less_than' => (float)$fieldValue < (float)$compareValue,
            default => false,
        };
    }

    /**
     * Get the field value from email data
     */
    private function getFieldValue(array $emailData): string
    {
        return match ($this->field) {
            'from' => $emailData['from'] ?? '',
            'to' => $emailData['to'] ?? '',
            'subject' => $emailData['subject'] ?? '',
            'body' => $emailData['body'] ?? '',
            'size' => (string)($emailData['size'] ?? 0),
            'spam_score' => (string)($emailData['spam_score'] ?? 0),
            'recipient' => $emailData['recipient'] ?? $emailData['to'] ?? '',
            'sender' => $emailData['sender'] ?? $emailData['from'] ?? '',
            'header' => $emailData['headers'][$this->header_name] ?? '',
            default => '',
        };
    }
}
