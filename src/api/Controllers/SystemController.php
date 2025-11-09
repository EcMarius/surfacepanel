<?php

namespace VirPanel\Api\Controllers;

use VirPanel\Api\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * System API Controller
 *
 * Handles system-level API endpoints
 */
class SystemController extends Controller
{
    /**
     * Get system information
     *
     * GET /api/v1/system/info
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function info(Request $request): JsonResponse
    {
        $info = [
            'version' => '0.1.0',
            'name' => config('app.name', 'VirPanel'),
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
        ];

        return $this->success($info);
    }

    /**
     * Get system statistics
     *
     * GET /api/v1/system/stats
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $stats = [
            'accounts' => [
                'total' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}accounts"),
                'active' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}accounts WHERE status = 'active'"),
                'suspended' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}accounts WHERE status = 'suspended'"),
            ],
            'domains' => [
                'total' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}domains"),
                'with_ssl' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}domains WHERE ssl_enabled = 1"),
            ],
            'databases' => [
                'total' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}databases"),
                'mysql' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}databases WHERE type = 'mysql'"),
                'postgresql' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}databases WHERE type = 'postgresql'"),
            ],
            'emails' => [
                'total' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}email_accounts"),
                'active' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}email_accounts WHERE is_active = 1"),
            ],
            'disk_usage' => [
                'total_mb' => (int) $db->fetchOne("SELECT SUM(disk_used) FROM {$prefix}accounts"),
                'average_mb' => (int) $db->fetchOne("SELECT AVG(disk_used) FROM {$prefix}accounts"),
            ],
        ];

        return $this->success($stats);
    }

    /**
     * Get server health
     *
     * GET /api/v1/system/health
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function health(Request $request): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'checks' => [],
        ];

        // Database check
        try {
            $db = app('database');
            $db->fetchOne('SELECT 1');
            $health['checks']['database'] = 'ok';
        } catch (\Throwable $e) {
            $health['checks']['database'] = 'error';
            $health['status'] = 'unhealthy';
        }

        // Cache check
        try {
            $cache = app('cache');
            $cache->set('health_check', '1', 10);
            $health['checks']['cache'] = 'ok';
        } catch (\Throwable $e) {
            $health['checks']['cache'] = 'error';
        }

        // Disk space check
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskUsagePercent = (($diskTotal - $diskFree) / $diskTotal) * 100;

        $health['checks']['disk'] = $diskUsagePercent < 90 ? 'ok' : 'warning';
        $health['disk_usage_percent'] = round($diskUsagePercent, 2);

        // Memory check
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit !== '-1') {
            $memoryLimit = $this->parseSize($memoryLimit);
            $memoryPercent = ($memoryUsage / $memoryLimit) * 100;
            $health['checks']['memory'] = $memoryPercent < 90 ? 'ok' : 'warning';
            $health['memory_usage_percent'] = round($memoryPercent, 2);
        } else {
            $health['checks']['memory'] = 'ok';
        }

        return $this->success($health);
    }

    /**
     * Get list of packages
     *
     * GET /api/v1/system/packages
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function packages(Request $request): JsonResponse
    {
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $packages = $db->fetchAllAssociative(
            "SELECT * FROM {$prefix}packages WHERE is_active = 1 ORDER BY monthly_price ASC"
        );

        return $this->success($packages);
    }

    /**
     * Get system settings
     *
     * GET /api/v1/system/settings
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function settings(Request $request): JsonResponse
    {
        $group = $request->query->get('group');

        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $queryBuilder = $db->createQueryBuilder()
            ->select('*')
            ->from($prefix . 'settings')
            ->orderBy('group', 'ASC')
            ->addOrderBy('key', 'ASC');

        if ($group) {
            $queryBuilder->where('`group` = :group')
                ->setParameter('group', $group);
        }

        $settings = $queryBuilder->executeQuery()->fetchAllAssociative();

        // Group settings by group
        $grouped = [];
        foreach ($settings as $setting) {
            $grouped[$setting['group']][$setting['key']] = $this->parseSettingValue($setting);
        }

        return $this->success($grouped);
    }

    /**
     * Update a system setting
     *
     * PUT /api/v1/system/settings/{key}
     *
     * @param Request $request
     * @param string $key
     * @return JsonResponse
     */
    public function updateSetting(Request $request, string $key): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['value'])) {
            return $this->error('Value is required', 400);
        }

        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $setting = $db->fetchAssociative(
            "SELECT * FROM {$prefix}settings WHERE `key` = ?",
            [$key]
        );

        if (!$setting) {
            return $this->notFound('Setting not found');
        }

        $value = $this->formatSettingValue($data['value'], $setting['type']);

        $db->update($prefix . 'settings', [
            'value' => $value,
        ], ['key' => $key]);

        logger("Setting updated: {$key}");

        return $this->success(['message' => 'Setting updated successfully']);
    }

    /**
     * Get license information
     *
     * GET /api/v1/system/license
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function license(Request $request): JsonResponse
    {
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $license = $db->fetchAssociative(
            "SELECT * FROM {$prefix}licenses ORDER BY id DESC LIMIT 1"
        );

        if (!$license) {
            return $this->success([
                'active' => false,
                'message' => 'No license found',
            ]);
        }

        return $this->success([
            'active' => $license['status'] === 'active',
            'type' => $license['type'],
            'max_accounts' => $license['max_accounts'],
            'expires_at' => $license['expires_at'],
            'status' => $license['status'],
        ]);
    }

    /**
     * Parse a size string to bytes
     *
     * @param string $size
     * @return int
     */
    protected function parseSize(string $size): int
    {
        $unit = strtoupper(substr($size, -1));
        $value = (int) substr($size, 0, -1);

        return match ($unit) {
            'K' => $value * 1024,
            'M' => $value * 1024 * 1024,
            'G' => $value * 1024 * 1024 * 1024,
            default => (int) $size,
        };
    }

    /**
     * Parse setting value based on type
     *
     * @param array $setting
     * @return mixed
     */
    protected function parseSettingValue(array $setting): mixed
    {
        return match ($setting['type']) {
            'bool' => filter_var($setting['value'], FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $setting['value'],
            'json' => json_decode($setting['value'], true),
            default => $setting['value'],
        };
    }

    /**
     * Format setting value for storage
     *
     * @param mixed $value
     * @param string $type
     * @return string
     */
    protected function formatSettingValue(mixed $value, string $type): string
    {
        return match ($type) {
            'bool' => $value ? 'true' : 'false',
            'int' => (string) $value,
            'json' => json_encode($value),
            default => (string) $value,
        };
    }
}
