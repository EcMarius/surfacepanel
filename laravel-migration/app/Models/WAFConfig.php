<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WAFConfig extends Model
{
    use HasFactory;

    protected $table = 'vp_waf_config';

    protected $fillable = [
        'is_enabled',
        'mode',
        'use_owasp_crs',
        'paranoia_level',
        'disabled_rules',
        'custom_rules',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'use_owasp_crs' => 'boolean',
        'paranoia_level' => 'integer',
        'disabled_rules' => 'array',
        'custom_rules' => 'array',
    ];

    /**
     * Get the singleton configuration instance
     */
    public static function getInstance(): self
    {
        $config = self::first();

        if (!$config) {
            $config = self::create([
                'is_enabled' => true,
                'mode' => 'blocking',
                'use_owasp_crs' => true,
                'paranoia_level' => 1,
            ]);
        }

        return $config;
    }

    /**
     * Check if WAF is in blocking mode
     */
    public function isBlocking(): bool
    {
        return $this->is_enabled && $this->mode === 'blocking';
    }

    /**
     * Check if WAF is in detection mode
     */
    public function isDetectionOnly(): bool
    {
        return $this->is_enabled && $this->mode === 'detection';
    }

    /**
     * Get paranoia level description
     */
    public function getParanoiaLevelDescription(): string
    {
        return match($this->paranoia_level) {
            1 => 'Normal - Standard protection',
            2 => 'Elevated - Increased protection with minimal false positives',
            3 => 'High - Strong protection, may cause some false positives',
            4 => 'Paranoid - Maximum protection, higher false positive rate',
            default => 'Unknown',
        };
    }

    /**
     * Common attack patterns for detection
     */
    public static function getAttackPatterns(): array
    {
        return [
            'sql_injection' => [
                'name' => 'SQL Injection',
                'patterns' => [
                    '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|CONCAT)\b)/i',
                    '/(\%27)|(\')|(\-\-)|(\%23)|(#)/i',
                    '/((\%3D)|(=))[^\n]*((\%27)|(\')|(\-\-)|(\%3B)|(;))/i',
                ],
                'severity' => 1,
            ],
            'xss' => [
                'name' => 'Cross-Site Scripting (XSS)',
                'patterns' => [
                    '/(<script[^>]*>.*?<\/script>)/is',
                    '/(<iframe[^>]*>.*?<\/iframe>)/is',
                    '/(on\w+\s*=\s*[\'"].*?[\'"])/i',
                    '/(javascript:)/i',
                ],
                'severity' => 2,
            ],
            'lfi' => [
                'name' => 'Local File Inclusion',
                'patterns' => [
                    '/(\.\.\/)/i',
                    '/(\.\.\\\\)/i',
                    '/(\%2e\%2e\%2f)/i',
                ],
                'severity' => 1,
            ],
            'rfi' => [
                'name' => 'Remote File Inclusion',
                'patterns' => [
                    '/(http|https|ftp):\/\//i',
                ],
                'severity' => 1,
            ],
            'rce' => [
                'name' => 'Remote Code Execution',
                'patterns' => [
                    '/\b(eval|exec|system|shell_exec|passthru|popen|proc_open)\b/i',
                    '/\$\{/i',
                    '/\$\(/i',
                ],
                'severity' => 1,
            ],
        ];
    }
}
