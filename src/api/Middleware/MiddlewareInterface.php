<?php

namespace VirPanel\Api\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware Interface
 *
 * All middleware must implement this interface
 */
interface MiddlewareInterface
{
    /**
     * Handle an incoming request
     *
     * @param Request $request
     * @return Response|null Return Response to halt, null to continue
     */
    public function handle(Request $request): ?Response;
}
