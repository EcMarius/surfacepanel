<?php

namespace App\Services;

use App\Models\GPUDevice;
use Illuminate\Support\Facades\Log;

class GPUDetectionService
{
    /**
     * Detect NVIDIA GPUs using nvidia-smi
     */
    public function detectGPUs(): array
    {
        if (!$this->isNvidiaSmiAvailable()) {
            throw new \Exception('nvidia-smi is not available. Please install NVIDIA drivers.');
        }

        $gpus = [];
        $output = $this->executeNvidiaSmi();

        if (empty($output)) {
            return $gpus;
        }

        foreach ($output as $gpuData) {
            $gpus[] = [
                'uuid' => $gpuData['uuid'],
                'name' => $gpuData['name'],
                'index' => $gpuData['index'],
                'memory_total' => $gpuData['memory_total'],
                'memory_free' => $gpuData['memory_free'],
                'memory_used' => $gpuData['memory_used'],
                'driver_version' => $gpuData['driver_version'],
                'cuda_version' => $gpuData['cuda_version'],
                'temperature' => $gpuData['temperature'],
                'power_draw' => $gpuData['power_draw'],
                'power_limit' => $gpuData['power_limit'],
                'utilization' => $gpuData['utilization'],
                'pci_bus_id' => $gpuData['pci_bus_id'],
                'last_detected_at' => now(),
            ];
        }

        return $gpus;
    }

    /**
     * Execute nvidia-smi and parse output
     */
    private function executeNvidiaSmi(): array
    {
        $command = 'nvidia-smi --query-gpu=index,uuid,name,memory.total,memory.free,memory.used,driver_version,temperature.gpu,power.draw,power.limit,utilization.gpu,pci.bus_id --format=csv,noheader,nounits 2>&1';

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('nvidia-smi execution failed', ['output' => $output]);
            return [];
        }

        // Get CUDA version
        $cudaVersion = $this->getCudaVersion();

        $gpus = [];
        foreach ($output as $line) {
            $fields = array_map('trim', explode(',', $line));

            if (count($fields) < 12) {
                continue;
            }

            $gpus[] = [
                'index' => (int) $fields[0],
                'uuid' => $fields[1],
                'name' => $fields[2],
                'memory_total' => (int) $fields[3],
                'memory_free' => (int) $fields[4],
                'memory_used' => (int) $fields[5],
                'driver_version' => $fields[6],
                'cuda_version' => $cudaVersion,
                'temperature' => (int) $fields[7],
                'power_draw' => (int) floatval($fields[8]),
                'power_limit' => (int) floatval($fields[9]),
                'utilization' => (int) $fields[10],
                'pci_bus_id' => $fields[11],
            ];
        }

        return $gpus;
    }

    /**
     * Get CUDA version
     */
    private function getCudaVersion(): ?string
    {
        exec('nvidia-smi --query-gpu=compute_cap --format=csv,noheader 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            return null;
        }

        // Get CUDA version from nvidia-smi
        exec('nvidia-smi | grep "CUDA Version" 2>&1', $cudaOutput);

        if (!empty($cudaOutput)) {
            preg_match('/CUDA Version:\s*([0-9.]+)/', $cudaOutput[0], $matches);
            return $matches[1] ?? null;
        }

        return null;
    }

    /**
     * Check if nvidia-smi is available
     */
    public function isNvidiaSmiAvailable(): bool
    {
        exec('which nvidia-smi 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Get GPU statistics by UUID
     */
    public function getGPUStats(string $uuid): ?array
    {
        $command = "nvidia-smi --query-gpu=uuid,memory.total,memory.free,memory.used,temperature.gpu,power.draw,utilization.gpu --format=csv,noheader,nounits 2>&1 | grep {$uuid}";

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || empty($output)) {
            return null;
        }

        $fields = array_map('trim', explode(',', $output[0]));

        if (count($fields) < 7) {
            return null;
        }

        return [
            'uuid' => $fields[0],
            'memory_total' => (int) $fields[1],
            'memory_free' => (int) $fields[2],
            'memory_used' => (int) $fields[3],
            'temperature' => (int) $fields[4],
            'power_draw' => (int) floatval($fields[5]),
            'utilization' => (int) $fields[6],
        ];
    }

    /**
     * Get running processes on a GPU
     */
    public function getGPUProcesses(int $gpuIndex): array
    {
        $command = "nvidia-smi --query-compute-apps=pid,process_name,used_memory --format=csv,noheader,nounits -i {$gpuIndex} 2>&1";

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || empty($output)) {
            return [];
        }

        $processes = [];
        foreach ($output as $line) {
            if (empty(trim($line))) continue;

            $fields = array_map('trim', explode(',', $line));

            if (count($fields) >= 3) {
                $processes[] = [
                    'pid' => (int) $fields[0],
                    'name' => $fields[1],
                    'memory_used' => (int) $fields[2],
                ];
            }
        }

        return $processes;
    }

    /**
     * Sync detected GPUs to database
     */
    public function syncGPUsToDatabase(): int
    {
        $detectedGPUs = $this->detectGPUs();
        $synced = 0;

        foreach ($detectedGPUs as $gpuData) {
            $gpu = GPUDevice::updateOrCreate(
                ['uuid' => $gpuData['uuid']],
                $gpuData
            );

            $synced++;
        }

        return $synced;
    }

    /**
     * Get driver information
     */
    public function getDriverInfo(): array
    {
        exec('nvidia-smi --query-gpu=driver_version --format=csv,noheader 2>&1', $output, $returnCode);

        if ($returnCode !== 0 || empty($output)) {
            return [
                'driver_version' => 'Unknown',
                'cuda_version' => 'Unknown',
            ];
        }

        $driverVersion = trim($output[0]);
        $cudaVersion = $this->getCudaVersion();

        return [
            'driver_version' => $driverVersion,
            'cuda_version' => $cudaVersion ?? 'Unknown',
        ];
    }
}
