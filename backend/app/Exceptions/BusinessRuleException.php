<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A business-rule violation (not a validation error). Rendered as 422 so the
 * frontend can show the message with the same envelope as validation errors.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(string $message, private array $errors = [])
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => $this->errors ?: ['order' => [$this->getMessage()]],
        ], 422);
    }
}
