<?php

namespace VirPanel\Core\Http\Controllers\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use VirPanel\Core\Http\Controllers\Controller;

/**
 * Base API Controller
 *
 * Provides JSON response methods and error handling for all API controllers
 */
abstract class ApiController extends Controller
{
    /**
     * Return a success JSON response
     */
    protected function success($data = null, string $message = '', int $status = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return new JsonResponse($response, $status);
    }

    /**
     * Return an error JSON response
     */
    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return new JsonResponse($response, $status);
    }

    /**
     * Return a paginated response
     */
    protected function paginate(array $data, int $total, int $page, int $perPage): JsonResponse
    {
        return $this->success([
            'items' => $data,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total),
            ],
        ]);
    }

    /**
     * Validate required fields
     */
    protected function validateRequired(array $data, array $required): array
    {
        $errors = [];

        foreach ($required as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $errors[$field] = ucfirst($field) . ' is required';
            }
        }

        return $errors;
    }

    /**
     * Handle exceptions and return appropriate JSON response
     */
    protected function handleException(\Exception $e): JsonResponse
    {
        logger('API Error: ' . $e->getMessage());

        // Don't expose internal errors in production
        if (config('app.env') === 'production') {
            return $this->error('An error occurred processing your request', 500);
        }

        return $this->error($e->getMessage(), 500, [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
