<?php

namespace App\Services;

use App\Models\ModalConfig;
use App\Models\ModalFunction;
use App\Models\ModalJob;
use App\Models\ModalUsage;
use App\Models\ModalFunctionVersion;
use App\Models\ModalExecutionLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ModalService
{
    protected $config;
    protected $baseUrl = 'https://api.modal.com/v1';

    public function __construct()
    {
        $this->config = ModalConfig::getInstance();
    }

    /**
     * Check if Modal.com is configured
     */
    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        try {
            if (!$this->isConfigured()) {
                return [
                    'success' => false,
                    'message' => 'Modal.com API credentials not configured',
                ];
            }

            $response = $this->makeRequest('GET', '/workspaces/current');

            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Successfully connected to Modal.com',
                    'workspace' => $response['data'] ?? null,
                ];
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Modal.com connection test failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Deploy a function to Modal.com
     */
    public function deployFunction(ModalFunction $function): array
    {
        try {
            if (!$this->isConfigured()) {
                return [
                    'success' => false,
                    'message' => 'Modal.com not configured',
                ];
            }

            // Update status to deploying
            $function->update(['status' => 'deploying']);

            // Prepare deployment payload
            $payload = [
                'name' => $function->function_name,
                'runtime' => $function->runtime,
                'code' => base64_encode($function->code),
                'entrypoint' => $function->entrypoint,
                'requirements' => $function->requirements ?? [],
                'environment_variables' => $function->environment_variables ?? [],
                'resources' => [
                    'cpu' => $function->cpu_count,
                    'memory_mb' => $function->memory_mb,
                    'timeout' => $function->timeout,
                ],
            ];

            // Add GPU configuration if enabled
            if ($function->gpu_enabled) {
                $payload['resources']['gpu'] = [
                    'type' => $function->gpu_type,
                    'count' => $function->gpu_count,
                ];
            }

            // Deploy to Modal
            $response = $this->makeRequest('POST', '/functions', $payload);

            if ($response['success']) {
                $functionData = $response['data'] ?? [];

                // Create new version
                ModalFunctionVersion::create([
                    'function_id' => $function->id,
                    'version' => $function->version,
                    'code' => $function->code,
                    'requirements' => $function->requirements,
                    'environment_variables' => $function->environment_variables,
                    'configuration' => $function->getResourceConfig(),
                    'is_active' => true,
                    'deployed_at' => now(),
                ]);

                // Update function with deployment info
                $function->update([
                    'function_id' => $functionData['id'] ?? null,
                    'endpoint_url' => $functionData['endpoint_url'] ?? null,
                    'status' => 'active',
                    'deployment_error' => null,
                    'last_deployed_at' => now(),
                ]);

                return [
                    'success' => true,
                    'message' => 'Function deployed successfully',
                    'function_id' => $functionData['id'] ?? null,
                    'endpoint_url' => $functionData['endpoint_url'] ?? null,
                ];
            }

            // Deployment failed
            $function->update([
                'status' => 'failed',
                'deployment_error' => $response['message'] ?? 'Deployment failed',
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Modal function deployment failed', [
                'function_id' => $function->id,
                'error' => $e->getMessage(),
            ]);

            $function->update([
                'status' => 'failed',
                'deployment_error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Deployment failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Invoke a function
     */
    public function invokeFunction(ModalFunction $function, array $input = []): array
    {
        try {
            if (!$function->isDeployed()) {
                return [
                    'success' => false,
                    'message' => 'Function not deployed',
                ];
            }

            $startTime = now();

            // Create execution log
            $executionLog = ModalExecutionLog::create([
                'account_id' => $function->account_id,
                'function_id' => $function->id,
                'started_at' => $startTime,
                'status' => 'running',
                'input' => json_encode($input),
            ]);

            // Invoke function via Modal API
            $response = $this->makeRequest('POST', "/functions/{$function->function_id}/invoke", [
                'input' => $input,
            ]);

            $endTime = now();
            $duration = $startTime->diffInMilliseconds($endTime);

            if ($response['success']) {
                $executionData = $response['data'] ?? [];

                // Update execution log
                $executionLog->update([
                    'execution_id' => $executionData['execution_id'] ?? null,
                    'completed_at' => $endTime,
                    'duration_ms' => $duration,
                    'status' => 'success',
                    'output' => json_encode($executionData['output'] ?? null),
                    'logs' => $executionData['logs'] ?? null,
                    'memory_used_mb' => $executionData['memory_used_mb'] ?? 0,
                ]);

                // Update usage statistics
                $this->recordUsage($function, $duration, true);

                return [
                    'success' => true,
                    'output' => $executionData['output'] ?? null,
                    'execution_id' => $executionData['execution_id'] ?? null,
                    'duration_ms' => $duration,
                ];
            }

            // Invocation failed
            $executionLog->update([
                'completed_at' => $endTime,
                'duration_ms' => $duration,
                'status' => 'failed',
                'error_message' => $response['message'] ?? 'Invocation failed',
            ]);

            $this->recordUsage($function, $duration, false);

            return $response;
        } catch (\Exception $e) {
            Log::error('Modal function invocation failed', [
                'function_id' => $function->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Invocation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a function from Modal.com
     */
    public function deleteFunction(ModalFunction $function): array
    {
        try {
            if (!$function->isDeployed()) {
                return [
                    'success' => true,
                    'message' => 'Function not deployed, nothing to delete',
                ];
            }

            $response = $this->makeRequest('DELETE', "/functions/{$function->function_id}");

            if ($response['success']) {
                $function->update([
                    'function_id' => null,
                    'status' => 'draft',
                    'endpoint_url' => null,
                ]);

                return [
                    'success' => true,
                    'message' => 'Function deleted successfully',
                ];
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Modal function deletion failed', [
                'function_id' => $function->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Deletion failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create a scheduled job
     */
    public function createJob(ModalJob $job): array
    {
        try {
            if (!$this->isConfigured()) {
                return [
                    'success' => false,
                    'message' => 'Modal.com not configured',
                ];
            }

            $function = $job->function;
            if (!$function || !$function->isDeployed()) {
                return [
                    'success' => false,
                    'message' => 'Function not deployed',
                ];
            }

            $payload = [
                'name' => $job->job_name,
                'function_id' => $function->function_id,
                'schedule' => $job->schedule,
                'parameters' => $job->parameters ?? [],
            ];

            $response = $this->makeRequest('POST', '/jobs', $payload);

            if ($response['success']) {
                $jobData = $response['data'] ?? [];

                $job->update([
                    'job_id' => $jobData['id'] ?? null,
                    'next_run_at' => $jobData['next_run_at'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message' => 'Job created successfully',
                    'job_id' => $jobData['id'] ?? null,
                ];
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Modal job creation failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Job creation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a scheduled job
     */
    public function deleteJob(ModalJob $job): array
    {
        try {
            if (!$job->isDeployed()) {
                return [
                    'success' => true,
                    'message' => 'Job not deployed, nothing to delete',
                ];
            }

            $response = $this->makeRequest('DELETE', "/jobs/{$job->job_id}");

            if ($response['success']) {
                $job->update([
                    'job_id' => null,
                    'is_active' => false,
                ]);

                return [
                    'success' => true,
                    'message' => 'Job deleted successfully',
                ];
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Modal job deletion failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Job deletion failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get usage statistics from Modal.com
     */
    public function syncUsageStatistics($accountId, $startDate, $endDate): array
    {
        try {
            $response = $this->makeRequest('GET', '/usage', [
                'account_id' => $accountId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            if ($response['success']) {
                $usageData = $response['data'] ?? [];

                foreach ($usageData as $dailyUsage) {
                    ModalUsage::updateOrCreate(
                        [
                            'account_id' => $accountId,
                            'function_id' => $dailyUsage['function_id'] ?? null,
                            'usage_date' => $dailyUsage['date'],
                        ],
                        [
                            'total_invocations' => $dailyUsage['invocations'] ?? 0,
                            'successful_invocations' => $dailyUsage['successful_invocations'] ?? 0,
                            'failed_invocations' => $dailyUsage['failed_invocations'] ?? 0,
                            'total_execution_time_ms' => $dailyUsage['execution_time_ms'] ?? 0,
                            'total_cpu_time_ms' => $dailyUsage['cpu_time_ms'] ?? 0,
                            'total_gpu_time_ms' => $dailyUsage['gpu_time_ms'] ?? 0,
                            'total_memory_mb_seconds' => $dailyUsage['memory_mb_seconds'] ?? 0,
                            'estimated_cost' => $dailyUsage['cost'] ?? 0,
                            'detailed_metrics' => $dailyUsage['metrics'] ?? [],
                        ]
                    );
                }

                return [
                    'success' => true,
                    'message' => 'Usage statistics synced successfully',
                ];
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Modal usage sync failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Usage sync failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Record usage for a function execution
     */
    protected function recordUsage(ModalFunction $function, int $durationMs, bool $success): void
    {
        $today = Carbon::today();

        $usage = ModalUsage::firstOrNew([
            'account_id' => $function->account_id,
            'function_id' => $function->id,
            'usage_date' => $today,
        ]);

        $usage->total_invocations += 1;
        if ($success) {
            $usage->successful_invocations += 1;
        } else {
            $usage->failed_invocations += 1;
        }
        $usage->total_execution_time_ms += $durationMs;

        // Estimate cost (simplified calculation)
        $executionCost = $function->getEstimatedHourlyCost() * ($durationMs / 1000 / 3600);
        $usage->estimated_cost += $executionCost;

        $usage->save();
    }

    /**
     * Make an HTTP request to Modal API
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        try {
            $url = $this->baseUrl . $endpoint;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config->api_key,
                'X-API-Secret' => $this->config->api_secret,
                'Content-Type' => 'application/json',
            ])->$method($url, $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'API request failed',
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Request failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get function logs
     */
    public function getFunctionLogs(ModalFunction $function, int $limit = 100): array
    {
        try {
            if (!$function->isDeployed()) {
                return [
                    'success' => false,
                    'message' => 'Function not deployed',
                ];
            }

            $response = $this->makeRequest('GET', "/functions/{$function->function_id}/logs", [
                'limit' => $limit,
            ]);

            return $response;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch logs: ' . $e->getMessage(),
            ];
        }
    }
}
