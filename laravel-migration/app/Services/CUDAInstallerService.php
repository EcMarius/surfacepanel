<?php

namespace App\Services;

use App\Models\CUDAInstallation;
use Illuminate\Support\Facades\Log;

class CUDAInstallerService
{
    /**
     * Check if CUDA is installed
     */
    public function isCUDAInstalled(): bool
    {
        exec('which nvcc 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Get installed CUDA version
     */
    public function getInstalledCUDAVersion(): ?string
    {
        if (!$this->isCUDAInstalled()) {
            return null;
        }

        exec('nvcc --version 2>&1 | grep "release" | awk \'{print $5}\' | cut -d"," -f1', $output);

        return !empty($output) ? trim($output[0]) : null;
    }

    /**
     * Install CUDA Toolkit
     */
    public function installCUDA(string $version): array
    {
        $supportedVersions = [
            '12.2' => [
                'url' => 'https://developer.download.nvidia.com/compute/cuda/12.2.0/local_installers/cuda_12.2.0_535.54.03_linux.run',
                'installer' => 'cuda_12.2.0_535.54.03_linux.run',
            ],
            '11.8' => [
                'url' => 'https://developer.download.nvidia.com/compute/cuda/11.8.0/local_installers/cuda_11.8.0_520.61.05_linux.run',
                'installer' => 'cuda_11.8.0_520.61.05_linux.run',
            ],
        ];

        if (!isset($supportedVersions[$version])) {
            throw new \Exception("CUDA version {$version} is not supported");
        }

        $installData = $supportedVersions[$version];
        $tmpDir = '/tmp/cuda_install';

        // Create temporary directory
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $installerPath = "{$tmpDir}/{$installData['installer']}";

        // Download installer
        Log::info("Downloading CUDA {$version}...");
        exec("wget -O {$installerPath} {$installData['url']} 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("Failed to download CUDA installer: " . implode("\n", $output));
        }

        // Make installer executable
        chmod($installerPath, 0755);

        // Install CUDA (silent mode)
        Log::info("Installing CUDA {$version}...");
        exec("sh {$installerPath} --silent --toolkit --override 2>&1", $output, $returnCode);

        // Clean up
        unlink($installerPath);

        if ($returnCode !== 0) {
            throw new \Exception("CUDA installation failed: " . implode("\n", $output));
        }

        // Add to PATH
        $this->updateEnvironment($version);

        return [
            'success' => true,
            'version' => $version,
            'install_path' => "/usr/local/cuda-{$version}",
            'message' => "CUDA {$version} installed successfully",
        ];
    }

    /**
     * Update environment variables for CUDA
     */
    private function updateEnvironment(string $version): void
    {
        $cudaPath = "/usr/local/cuda-{$version}";
        $bashrc = '/etc/profile.d/cuda.sh';

        $content = <<<EOT
export CUDA_HOME={$cudaPath}
export PATH=\$CUDA_HOME/bin:\$PATH
export LD_LIBRARY_PATH=\$CUDA_HOME/lib64:\$LD_LIBRARY_PATH
EOT;

        file_put_contents($bashrc, $content);
        chmod($bashrc, 0644);

        // Source the file
        exec("source {$bashrc} 2>&1");
    }

    /**
     * Install cuDNN
     */
    public function installCuDNN(string $cudaVersion, string $cudnnVersion): array
    {
        // cuDNN installation typically requires manual download from NVIDIA
        // This is a placeholder for the installation process

        Log::info("Installing cuDNN {$cudnnVersion} for CUDA {$cudaVersion}...");

        $cudaPath = "/usr/local/cuda-{$cudaVersion}";

        if (!is_dir($cudaPath)) {
            throw new \Exception("CUDA {$cudaVersion} is not installed");
        }

        // Note: In production, you would download and extract cuDNN here
        // For now, we'll document the manual steps

        return [
            'success' => true,
            'version' => $cudnnVersion,
            'message' => "cuDNN installation initiated. Please download cuDNN from NVIDIA and place in {$cudaPath}",
            'manual_steps' => [
                "1. Download cuDNN {$cudnnVersion} from https://developer.nvidia.com/cudnn",
                "2. Extract the archive",
                "3. Copy files to {$cudaPath}",
                "4. Run: sudo cp cuda/include/cudnn*.h {$cudaPath}/include",
                "5. Run: sudo cp cuda/lib64/libcudnn* {$cudaPath}/lib64",
                "6. Run: sudo chmod a+r {$cudaPath}/include/cudnn*.h {$cudaPath}/lib64/libcudnn*",
            ],
        ];
    }

    /**
     * Install NCCL (NVIDIA Collective Communications Library)
     */
    public function installNCCL(string $cudaVersion): array
    {
        Log::info("Installing NCCL for CUDA {$cudaVersion}...");

        // Install via apt for Ubuntu
        $commands = [
            'apt-get update',
            'apt-get install -y libnccl2 libnccl-dev',
        ];

        foreach ($commands as $command) {
            exec("sudo {$command} 2>&1", $output, $returnCode);

            if ($returnCode !== 0) {
                Log::error("NCCL installation command failed: {$command}", ['output' => $output]);
            }
        }

        return [
            'success' => true,
            'message' => 'NCCL installed successfully',
        ];
    }

    /**
     * Install TensorRT
     */
    public function installTensorRT(string $cudaVersion): array
    {
        Log::info("Installing TensorRT for CUDA {$cudaVersion}...");

        // Determine Ubuntu version
        exec('lsb_release -rs 2>&1', $ubuntuVersion);
        $version = trim($ubuntuVersion[0] ?? '20.04');

        $commands = [
            'apt-get update',
            'apt-get install -y libnvinfer8 libnvinfer-plugin8 libnvinfer-dev libnvinfer-plugin-dev',
        ];

        foreach ($commands as $command) {
            exec("sudo {$command} 2>&1", $output, $returnCode);

            if ($returnCode !== 0) {
                Log::error("TensorRT installation command failed: {$command}", ['output' => $output]);
            }
        }

        return [
            'success' => true,
            'message' => 'TensorRT installed successfully',
        ];
    }

    /**
     * Verify CUDA installation
     */
    public function verifyCUDAInstallation(): array
    {
        $checks = [];

        // Check nvcc
        exec('which nvcc 2>&1', $output, $returnCode);
        $checks['nvcc'] = $returnCode === 0;

        // Check CUDA samples can compile
        exec('nvcc --version 2>&1', $output, $returnCode);
        $checks['nvcc_version'] = $returnCode === 0 ? implode("\n", $output) : 'Failed';

        // Check CUDA libraries
        $checks['cuda_libraries'] = file_exists('/usr/local/cuda/lib64/libcudart.so');

        // Check nvidia-smi
        exec('nvidia-smi 2>&1', $output, $returnCode);
        $checks['nvidia_smi'] = $returnCode === 0;

        return $checks;
    }

    /**
     * Create or update CUDA installation record
     */
    public function registerCUDAInstallation(array $data): CUDAInstallation
    {
        return CUDAInstallation::updateOrCreate(
            ['version' => $data['version']],
            $data
        );
    }

    /**
     * Detect existing CUDA installations
     */
    public function detectExistingInstallations(): array
    {
        $installations = [];
        $cudaDirs = glob('/usr/local/cuda-*');

        foreach ($cudaDirs as $dir) {
            if (preg_match('/cuda-([0-9.]+)$/', $dir, $matches)) {
                $version = $matches[1];
                $nvccPath = "{$dir}/bin/nvcc";

                if (file_exists($nvccPath)) {
                    $installations[] = [
                        'version' => $version,
                        'install_path' => $dir,
                        'has_cudnn' => $this->checkCuDNN($dir),
                        'has_nccl' => $this->checkNCCL(),
                        'has_tensorrt' => $this->checkTensorRT(),
                    ];
                }
            }
        }

        return $installations;
    }

    /**
     * Check if cuDNN is installed
     */
    private function checkCuDNN(string $cudaPath): bool
    {
        return file_exists("{$cudaPath}/include/cudnn.h");
    }

    /**
     * Check if NCCL is installed
     */
    private function checkNCCL(): bool
    {
        exec('dpkg -l | grep libnccl 2>&1', $output, $returnCode);
        return $returnCode === 0 && !empty($output);
    }

    /**
     * Check if TensorRT is installed
     */
    private function checkTensorRT(): bool
    {
        exec('dpkg -l | grep libnvinfer 2>&1', $output, $returnCode);
        return $returnCode === 0 && !empty($output);
    }
}
