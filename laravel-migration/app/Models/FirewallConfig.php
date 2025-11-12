<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirewallConfig extends Model
{
    use HasFactory;

    protected $table = 'vp_firewall_config';

    protected $fillable = [
        'is_enabled',
        'default_policy',
        'block_ping',
        'syn_flood_protection',
        'connection_limit',
        'port_scan_threshold',
        'login_failure_threshold',
        'login_failure_ban_time',
        'allowed_countries',
        'blocked_countries',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'block_ping' => 'boolean',
        'syn_flood_protection' => 'boolean',
        'connection_limit' => 'integer',
        'port_scan_threshold' => 'integer',
        'login_failure_threshold' => 'integer',
        'login_failure_ban_time' => 'integer',
        'allowed_countries' => 'array',
        'blocked_countries' => 'array',
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
                'default_policy' => 'drop',
                'block_ping' => false,
                'syn_flood_protection' => true,
                'connection_limit' => 100,
                'port_scan_threshold' => 10,
                'login_failure_threshold' => 5,
                'login_failure_ban_time' => 3600,
            ]);
        }

        return $config;
    }

    /**
     * Get common service ports
     */
    public static function getCommonPorts(): array
    {
        return [
            22 => ['name' => 'SSH', 'protocol' => 'tcp'],
            21 => ['name' => 'FTP', 'protocol' => 'tcp'],
            20 => ['name' => 'FTP Data', 'protocol' => 'tcp'],
            25 => ['name' => 'SMTP', 'protocol' => 'tcp'],
            53 => ['name' => 'DNS', 'protocol' => 'both'],
            80 => ['name' => 'HTTP', 'protocol' => 'tcp'],
            110 => ['name' => 'POP3', 'protocol' => 'tcp'],
            143 => ['name' => 'IMAP', 'protocol' => 'tcp'],
            443 => ['name' => 'HTTPS', 'protocol' => 'tcp'],
            465 => ['name' => 'SMTPS', 'protocol' => 'tcp'],
            587 => ['name' => 'SMTP Submission', 'protocol' => 'tcp'],
            993 => ['name' => 'IMAPS', 'protocol' => 'tcp'],
            995 => ['name' => 'POP3S', 'protocol' => 'tcp'],
            3306 => ['name' => 'MySQL', 'protocol' => 'tcp'],
            5432 => ['name' => 'PostgreSQL', 'protocol' => 'tcp'],
            6379 => ['name' => 'Redis', 'protocol' => 'tcp'],
            8080 => ['name' => 'HTTP Alt', 'protocol' => 'tcp'],
            15443 => ['name' => 'VirPanel WHM', 'protocol' => 'tcp'],
            15444 => ['name' => 'VirPanel User', 'protocol' => 'tcp'],
        ];
    }
}
