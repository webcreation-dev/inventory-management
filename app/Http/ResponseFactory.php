<?php

namespace App\Http;

use Illuminate\Http\JsonResponse;

class ResponseFactory extends \Illuminate\Routing\ResponseFactory
{
    public function json($data = [], $status = 200, array $headers = [], $options = 0): JsonResponse
    {
        $safeOptions = $options
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE;

        return new JsonResponse($data, $status, $headers, $safeOptions);
    }
}
