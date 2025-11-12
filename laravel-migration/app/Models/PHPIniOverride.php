<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PHPIniOverride extends Model
{
    use HasFactory;

    protected $table = 'vp_php_ini_overrides';

    protected $fillable = [
        'domain_php_setting_id',
        'directive',
        'value',
    ];

    /**
     * Get the domain PHP setting
     */
    public function domainPhpSetting()
    {
        return $this->belongsTo(DomainPHPSetting::class);
    }

    /**
     * Common PHP.ini directives with descriptions
     */
    public static function getCommonDirectives(): array
    {
        return [
            'memory_limit' => [
                'label' => 'Memory Limit',
                'description' => 'Maximum amount of memory a script may consume',
                'default' => '128M',
                'type' => 'size',
            ],
            'upload_max_filesize' => [
                'label' => 'Upload Max Filesize',
                'description' => 'Maximum allowed size for uploaded files',
                'default' => '64M',
                'type' => 'size',
            ],
            'post_max_size' => [
                'label' => 'Post Max Size',
                'description' => 'Maximum size of POST data that PHP will accept',
                'default' => '64M',
                'type' => 'size',
            ],
            'max_execution_time' => [
                'label' => 'Max Execution Time',
                'description' => 'Maximum time in seconds a script is allowed to run',
                'default' => '300',
                'type' => 'integer',
            ],
            'max_input_time' => [
                'label' => 'Max Input Time',
                'description' => 'Maximum time in seconds a script is allowed to parse input data',
                'default' => '60',
                'type' => 'integer',
            ],
            'max_input_vars' => [
                'label' => 'Max Input Vars',
                'description' => 'How many input variables may be accepted',
                'default' => '1000',
                'type' => 'integer',
            ],
            'display_errors' => [
                'label' => 'Display Errors',
                'description' => 'Display errors on screen (recommended: Off for production)',
                'default' => 'Off',
                'type' => 'boolean',
            ],
            'log_errors' => [
                'label' => 'Log Errors',
                'description' => 'Log errors to file',
                'default' => 'On',
                'type' => 'boolean',
            ],
            'error_reporting' => [
                'label' => 'Error Reporting',
                'description' => 'Error reporting level',
                'default' => 'E_ALL & ~E_DEPRECATED & ~E_STRICT',
                'type' => 'constant',
            ],
            'session.gc_maxlifetime' => [
                'label' => 'Session Max Lifetime',
                'description' => 'Number of seconds after which data will be seen as garbage',
                'default' => '1440',
                'type' => 'integer',
            ],
            'allow_url_fopen' => [
                'label' => 'Allow URL fopen',
                'description' => 'Enable the URL-aware fopen wrappers',
                'default' => 'On',
                'type' => 'boolean',
            ],
            'date.timezone' => [
                'label' => 'Default Timezone',
                'description' => 'Default timezone used by all date/time functions',
                'default' => 'UTC',
                'type' => 'string',
            ],
        ];
    }

    /**
     * Validate directive value based on type
     */
    public static function validateDirectiveValue(string $directive, string $value): bool
    {
        $directives = self::getCommonDirectives();

        if (!isset($directives[$directive])) {
            // Allow custom directives
            return true;
        }

        $info = $directives[$directive];

        switch ($info['type']) {
            case 'size':
                return preg_match('/^\d+[KMG]?$/i', $value);
            case 'integer':
                return is_numeric($value);
            case 'boolean':
                return in_array(strtolower($value), ['on', 'off', '1', '0', 'true', 'false']);
            default:
                return true;
        }
    }
}
