<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpamAssassinConfig extends Model
{
    use HasFactory;

    protected $table = 'vp_spamassassin_config';

    protected $fillable = [
        'account_id',
        'is_enabled',
        'spam_threshold',
        'spam_action',
        'spam_folder',
        'auto_learn',
        'use_bayes',
        'use_dcc',
        'use_pyzor',
        'use_razor',
        'rewrite_header',
        'spam_subject_tag',
        'emails_processed',
        'spam_detected',
        'ham_detected',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'spam_threshold' => 'decimal:2',
        'auto_learn' => 'boolean',
        'use_bayes' => 'boolean',
        'use_dcc' => 'boolean',
        'use_pyzor' => 'boolean',
        'use_razor' => 'boolean',
        'rewrite_header' => 'boolean',
        'emails_processed' => 'integer',
        'spam_detected' => 'integer',
        'ham_detected' => 'integer',
    ];

    /**
     * Get the account that owns this configuration
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get or create configuration for an account
     */
    public static function getForAccount($accountId): self
    {
        $config = self::where('account_id', $accountId)->first();

        if (!$config) {
            $config = self::create([
                'account_id' => $accountId,
                'is_enabled' => true,
                'spam_threshold' => 5.0,
                'spam_action' => 'tag',
                'spam_folder' => 'Spam',
                'auto_learn' => true,
                'use_bayes' => true,
                'use_dcc' => false,
                'use_pyzor' => false,
                'use_razor' => false,
                'rewrite_header' => true,
                'spam_subject_tag' => '[SPAM]',
            ]);
        }

        return $config;
    }

    /**
     * Get spam action description
     */
    public function getActionDescription(): string
    {
        return match($this->spam_action) {
            'delete' => 'Delete spam emails immediately',
            'quarantine' => 'Move spam to ' . $this->spam_folder . ' folder',
            'tag' => 'Tag subject line with ' . $this->spam_subject_tag,
            default => 'Unknown action',
        };
    }

    /**
     * Get spam detection rate
     */
    public function getSpamDetectionRate(): float
    {
        if ($this->emails_processed === 0) {
            return 0.0;
        }

        return round(($this->spam_detected / $this->emails_processed) * 100, 2);
    }

    /**
     * Get ham detection rate
     */
    public function getHamDetectionRate(): float
    {
        if ($this->emails_processed === 0) {
            return 0.0;
        }

        return round(($this->ham_detected / $this->emails_processed) * 100, 2);
    }

    /**
     * Increment email counters
     */
    public function incrementEmailsProcessed(): void
    {
        $this->increment('emails_processed');
    }

    public function incrementSpamDetected(): void
    {
        $this->increment('spam_detected');
        $this->increment('emails_processed');
    }

    public function incrementHamDetected(): void
    {
        $this->increment('ham_detected');
        $this->increment('emails_processed');
    }

    /**
     * Get active filters list
     */
    public function getActiveFilters(): array
    {
        $filters = [];

        if ($this->use_bayes) {
            $filters[] = 'Bayesian Filtering';
        }

        if ($this->use_dcc) {
            $filters[] = 'DCC';
        }

        if ($this->use_pyzor) {
            $filters[] = 'Pyzor';
        }

        if ($this->use_razor) {
            $filters[] = 'Razor';
        }

        if ($this->auto_learn) {
            $filters[] = 'Auto-Learning';
        }

        return $filters;
    }

    /**
     * Generate SpamAssassin user_prefs file content
     */
    public function generateUserPrefs(): string
    {
        $prefs = "# SpamAssassin user preferences for account {$this->account->username}\n";
        $prefs .= "# Generated: " . now()->toDateTimeString() . "\n\n";

        $prefs .= "# Required spam score\n";
        $prefs .= "required_score {$this->spam_threshold}\n\n";

        $prefs .= "# Rewrite subject\n";
        $prefs .= "rewrite_header Subject {$this->spam_subject_tag}\n\n";

        $prefs .= "# Bayesian filtering\n";
        $prefs .= "use_bayes " . ($this->use_bayes ? '1' : '0') . "\n";
        $prefs .= "bayes_auto_learn " . ($this->auto_learn ? '1' : '0') . "\n\n";

        if ($this->use_dcc) {
            $prefs .= "# DCC (Distributed Checksum Clearinghouse)\n";
            $prefs .= "use_dcc 1\n\n";
        }

        if ($this->use_pyzor) {
            $prefs .= "# Pyzor\n";
            $prefs .= "use_pyzor 1\n\n";
        }

        if ($this->use_razor) {
            $prefs .= "# Razor\n";
            $prefs .= "use_razor2 1\n\n";
        }

        return $prefs;
    }
}
