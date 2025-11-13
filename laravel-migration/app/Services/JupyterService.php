<?php

namespace App\Services;

use App\Models\GPUContainer;
use App\Models\GPUAllocation;
use App\Models\MLFramework;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class JupyterService
{
    /**
     * Launch Jupyter Notebook/Lab container with GPU
     */
    public function launchJupyter(
        GPUAllocation $allocation,
        string $type = 'jupyterlab',
        ?MLFramework $framework = null
    ): GPUContainer {
        // Generate unique container name
        $containerName = "jupyter-{$allocation->account_id}-" . Str::random(8);

        // Generate Jupyter token
        $token = Str::random(48);

        // Find available port
        $port = $this->findAvailablePort();

        // Determine Docker image
        $image = $this->getDockerImage($type, $framework);

        // Get GPU device index
        $gpuIndex = $allocation->gpuDevice->index;

        // Prepare environment variables
        $envVars = [
            'JUPYTER_ENABLE_LAB' => $type === 'jupyterlab' ? 'yes' : 'no',
            'JUPYTER_TOKEN' => $token,
            'NVIDIA_VISIBLE_DEVICES' => (string) $gpuIndex,
            'CUDA_VISIBLE_DEVICES' => (string) $gpuIndex,
        ];

        // Prepare volumes
        $userDir = "/home/virpanel/users/{$allocation->account->username}";
        if (!is_dir($userDir)) {
            mkdir($userDir, 0755, true);
        }

        $volumes = [
            "{$userDir}:/home/jovyan/work",
        ];

        // Build Docker command
        $command = $this->buildDockerCommand(
            $containerName,
            $image,
            $port,
            $envVars,
            $volumes,
            $gpuIndex
        );

        // Execute Docker command
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('Failed to launch Jupyter container', [
                'command' => $command,
                'output' => $output,
            ]);
            throw new \Exception('Failed to launch Jupyter container: ' . implode("\n", $output));
        }

        // Get container ID
        $containerId = $this->getContainerIdByName($containerName);

        // Create container record
        $container = GPUContainer::create([
            'account_id' => $allocation->account_id,
            'allocation_id' => $allocation->id,
            'framework_id' => $framework?->id,
            'container_name' => $containerName,
            'container_id' => $containerId,
            'image' => $image,
            'port' => $port,
            'token' => $token,
            'type' => $type,
            'status' => 'running',
            'environment_vars' => $envVars,
            'volumes' => $volumes,
            'started_at' => now(),
        ]);

        return $container;
    }

    /**
     * Build Docker run command
     */
    private function buildDockerCommand(
        string $name,
        string $image,
        int $port,
        array $envVars,
        array $volumes,
        int $gpuIndex
    ): string {
        $cmd = "docker run -d --name {$name}";

        // Add GPU support
        $cmd .= " --gpus device={$gpuIndex}";

        // Add port mapping
        $cmd .= " -p {$port}:8888";

        // Add environment variables
        foreach ($envVars as $key => $value) {
            $cmd .= " -e {$key}=\"{$value}\"";
        }

        // Add volumes
        foreach ($volumes as $volume) {
            $cmd .= " -v {$volume}";
        }

        // Add restart policy
        $cmd .= " --restart unless-stopped";

        // Add image
        $cmd .= " {$image}";

        return $cmd;
    }

    /**
     * Get Docker image for Jupyter
     */
    private function getDockerImage(string $type, ?MLFramework $framework): string
    {
        // If framework specified and has Docker image, use it
        if ($framework && $framework->docker_image) {
            return $framework->docker_image;
        }

        // Default images
        $images = [
            'jupyter' => 'jupyter/tensorflow-notebook:latest',
            'jupyterlab' => 'jupyter/tensorflow-notebook:latest',
            'vscode' => 'codercom/code-server:latest',
        ];

        return $images[$type] ?? $images['jupyterlab'];
    }

    /**
     * Find available port
     */
    private function findAvailablePort(int $start = 8888, int $end = 9999): int
    {
        for ($port = $start; $port <= $end; $port++) {
            exec("netstat -tuln | grep :{$port} 2>&1", $output);

            if (empty($output)) {
                return $port;
            }
        }

        throw new \Exception('No available ports found');
    }

    /**
     * Get container ID by name
     */
    private function getContainerIdByName(string $name): ?string
    {
        exec("docker ps -aqf name={$name} 2>&1", $output);
        return !empty($output) ? trim($output[0]) : null;
    }

    /**
     * Stop Jupyter container
     */
    public function stopContainer(GPUContainer $container): bool
    {
        if (!$container->container_id) {
            return false;
        }

        exec("docker stop {$container->container_id} 2>&1", $output, $returnCode);

        if ($returnCode === 0) {
            $container->update([
                'status' => 'stopped',
                'stopped_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Start stopped container
     */
    public function startContainer(GPUContainer $container): bool
    {
        if (!$container->container_id) {
            return false;
        }

        exec("docker start {$container->container_id} 2>&1", $output, $returnCode);

        if ($returnCode === 0) {
            $container->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Remove container
     */
    public function removeContainer(GPUContainer $container): bool
    {
        if (!$container->container_id) {
            return false;
        }

        // Stop first if running
        if ($container->isRunning()) {
            $this->stopContainer($container);
        }

        exec("docker rm {$container->container_id} 2>&1", $output, $returnCode);

        if ($returnCode === 0) {
            $container->delete();
            return true;
        }

        return false;
    }

    /**
     * Get container logs
     */
    public function getContainerLogs(GPUContainer $container, int $lines = 100): string
    {
        if (!$container->container_id) {
            return '';
        }

        exec("docker logs --tail {$lines} {$container->container_id} 2>&1", $output);

        return implode("\n", $output);
    }

    /**
     * Get container stats
     */
    public function getContainerStats(GPUContainer $container): ?array
    {
        if (!$container->container_id) {
            return null;
        }

        exec("docker stats --no-stream --format '{{json .}}' {$container->container_id} 2>&1", $output);

        if (empty($output)) {
            return null;
        }

        return json_decode($output[0], true);
    }

    /**
     * Install framework in container
     */
    public function installFramework(GPUContainer $container, string $package): bool
    {
        if (!$container->container_id || !$container->isRunning()) {
            return false;
        }

        $command = "docker exec {$container->container_id} pip install {$package}";

        exec($command . ' 2>&1', $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Execute command in container
     */
    public function executeCommand(GPUContainer $container, string $command): array
    {
        if (!$container->container_id || !$container->isRunning()) {
            return [
                'success' => false,
                'output' => 'Container is not running',
            ];
        }

        $dockerCommand = "docker exec {$container->container_id} {$command}";

        exec($dockerCommand . ' 2>&1', $output, $returnCode);

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output),
        ];
    }
}
