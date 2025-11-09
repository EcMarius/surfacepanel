<?php

namespace VirPanel\Core\Contracts;

use VirPanel\Core\Application;

/**
 * Module Interface
 *
 * All modules must implement this interface
 */
interface ModuleInterface
{
    /**
     * Create a new module instance
     *
     * @param Application $app
     */
    public function __construct(Application $app);

    /**
     * Register the module
     *
     * @return void
     */
    public function register(): void;

    /**
     * Boot the module
     *
     * @return void
     */
    public function boot(): void;

    /**
     * Get module name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get module version
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Get module description
     *
     * @return string
     */
    public function getDescription(): string;
}
