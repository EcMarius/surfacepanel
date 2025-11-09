<?php

namespace VirPanel\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base API Controller
 *
 * All API controllers should extend this class
 */
abstract class Controller
{
    /**
     * Return a success JSON response
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @return JsonResponse
     */
    protected function success(mixed $data = [], int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => $data,
        ], $status, $headers);
    }

    /**
     * Return an error JSON response
     *
     * @param string $message
     * @param int $status
     * @param array $errors
     * @param array $headers
     * @return JsonResponse
     */
    protected function error(string $message, int $status = 400, array $errors = [], array $headers = []): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return new JsonResponse($response, $status, $headers);
    }

    /**
     * Return a created response
     *
     * @param mixed $data
     * @param array $headers
     * @return JsonResponse
     */
    protected function created(mixed $data = [], array $headers = []): JsonResponse
    {
        return $this->success($data, 201, $headers);
    }

    /**
     * Return a no content response
     *
     * @param array $headers
     * @return Response
     */
    protected function noContent(array $headers = []): Response
    {
        return new Response('', 204, $headers);
    }

    /**
     * Return a not found response
     *
     * @param string $message
     * @param array $headers
     * @return JsonResponse
     */
    protected function notFound(string $message = 'Resource not found', array $headers = []): JsonResponse
    {
        return $this->error($message, 404, [], $headers);
    }

    /**
     * Return an unauthorized response
     *
     * @param string $message
     * @param array $headers
     * @return JsonResponse
     */
    protected function unauthorized(string $message = 'Unauthorized', array $headers = []): JsonResponse
    {
        return $this->error($message, 401, [], $headers);
    }

    /**
     * Return a forbidden response
     *
     * @param string $message
     * @param array $headers
     * @return JsonResponse
     */
    protected function forbidden(string $message = 'Forbidden', array $headers = []): JsonResponse
    {
        return $this->error($message, 403, [], $headers);
    }

    /**
     * Return a validation error response
     *
     * @param array $errors
     * @param string $message
     * @param array $headers
     * @return JsonResponse
     */
    protected function validationError(array $errors, string $message = 'Validation failed', array $headers = []): JsonResponse
    {
        return $this->error($message, 422, $errors, $headers);
    }

    /**
     * Return a paginated response
     *
     * @param array $items
     * @param int $total
     * @param int $page
     * @param int $perPage
     * @param array $headers
     * @return JsonResponse
     */
    protected function paginated(array $items, int $total, int $page, int $perPage, array $headers = []): JsonResponse
    {
        $lastPage = (int) ceil($total / $perPage);

        return new JsonResponse([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total),
            ],
        ], 200, $headers);
    }

    /**
     * Validate request data
     *
     * @param array $data
     * @param array $rules
     * @return array
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($fieldRules as $rule) {
                $ruleParts = explode(':', $rule);
                $ruleName = $ruleParts[0];
                $ruleParams = $ruleParts[1] ?? null;

                if (!$this->validateRule($data, $field, $ruleName, $ruleParams)) {
                    $errors[$field][] = $this->getErrorMessage($field, $ruleName, $ruleParams);
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a single rule
     *
     * @param array $data
     * @param string $field
     * @param string $rule
     * @param string|null $params
     * @return bool
     */
    protected function validateRule(array $data, string $field, string $rule, ?string $params = null): bool
    {
        $value = $data[$field] ?? null;

        return match ($rule) {
            'required' => !empty($value),
            'email' => empty($value) || filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'numeric' => empty($value) || is_numeric($value),
            'min' => empty($value) || strlen($value) >= (int) $params,
            'max' => empty($value) || strlen($value) <= (int) $params,
            'in' => empty($value) || in_array($value, explode(',', $params)),
            default => true,
        };
    }

    /**
     * Get validation error message
     *
     * @param string $field
     * @param string $rule
     * @param string|null $params
     * @return string
     */
    protected function getErrorMessage(string $field, string $rule, ?string $params = null): string
    {
        return match ($rule) {
            'required' => "The {$field} field is required.",
            'email' => "The {$field} must be a valid email address.",
            'numeric' => "The {$field} must be a number.",
            'min' => "The {$field} must be at least {$params} characters.",
            'max' => "The {$field} must not exceed {$params} characters.",
            'in' => "The {$field} must be one of: {$params}.",
            default => "The {$field} is invalid.",
        };
    }
}
