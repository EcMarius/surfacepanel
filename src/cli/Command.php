<?php

namespace VirPanel\Cli;

/**
 * Base CLI Command
 *
 * All CLI commands should extend this class
 */
abstract class Command
{
    /**
     * Command arguments
     *
     * @var array
     */
    protected array $args = [];

    /**
     * Command options
     *
     * @var array
     */
    protected array $options = [];

    /**
     * Verbose mode
     *
     * @var bool
     */
    protected bool $verbose = false;

    /**
     * Create a new command instance
     *
     * @param array $args
     * @param array $options
     */
    public function __construct(array $args = [], array $options = [])
    {
        $this->args = $args;
        $this->options = $options;
        $this->verbose = isset($options['v']) || isset($options['verbose']);
    }

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    abstract public function execute(): int;

    /**
     * Get command name
     *
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Get command description
     *
     * @return string
     */
    abstract public function getDescription(): string;

    /**
     * Get command usage
     *
     * @return string
     */
    public function getUsage(): string
    {
        return $this->getName();
    }

    /**
     * Print output
     *
     * @param string $message
     * @param bool $newline
     * @return void
     */
    protected function output(string $message, bool $newline = true): void
    {
        echo $message;

        if ($newline) {
            echo PHP_EOL;
        }
    }

    /**
     * Print success message
     *
     * @param string $message
     * @return void
     */
    protected function success(string $message): void
    {
        $this->output("[SUCCESS] {$message}");
    }

    /**
     * Print error message
     *
     * @param string $message
     * @return void
     */
    protected function error(string $message): void
    {
        $this->output("[ERROR] {$message}", true);
    }

    /**
     * Print warning message
     *
     * @param string $message
     * @return void
     */
    protected function warning(string $message): void
    {
        $this->output("[WARNING] {$message}");
    }

    /**
     * Print info message
     *
     * @param string $message
     * @return void
     */
    protected function info(string $message): void
    {
        if ($this->verbose) {
            $this->output("[INFO] {$message}");
        }
    }

    /**
     * Get an argument by index
     *
     * @param int $index
     * @param mixed $default
     * @return mixed
     */
    protected function argument(int $index, mixed $default = null): mixed
    {
        return $this->args[$index] ?? $default;
    }

    /**
     * Get an option by name
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * Check if an option exists
     *
     * @param string $name
     * @return bool
     */
    protected function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * Confirm action
     *
     * @param string $message
     * @return bool
     */
    protected function confirm(string $message): bool
    {
        $this->output($message . ' (y/n): ', false);
        $handle = fopen('php://stdin', 'r');
        $line = trim(fgets($handle));
        fclose($handle);

        return strtolower($line) === 'y' || strtolower($line) === 'yes';
    }

    /**
     * Ask for input
     *
     * @param string $message
     * @param mixed $default
     * @return string
     */
    protected function ask(string $message, mixed $default = null): string
    {
        if ($default !== null) {
            $this->output($message . " [{$default}]: ", false);
        } else {
            $this->output($message . ': ', false);
        }

        $handle = fopen('php://stdin', 'r');
        $line = trim(fgets($handle));
        fclose($handle);

        return $line ?: ($default ?? '');
    }

    /**
     * Ask for password (hidden input)
     *
     * @param string $message
     * @return string
     */
    protected function secret(string $message): string
    {
        $this->output($message . ': ', false);

        // Disable echo
        system('stty -echo');
        $handle = fopen('php://stdin', 'r');
        $password = trim(fgets($handle));
        fclose($handle);
        system('stty echo');

        $this->output(''); // New line

        return $password;
    }
}
