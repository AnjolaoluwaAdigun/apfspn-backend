<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class JsonResponser
{
    public static function send(bool $error, string $message, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'error' => $error,
            'message' => $message,
            'data' => self::resolve($data),
        ], $code);
    }

    public static function success(mixed $data = null, string $message = 'Request successful.', int $code = 200): JsonResponse
    {
        return self::send(false, $message, $data, $code);
    }

    public static function error(string $message, int $code = 400, mixed $data = null): JsonResponse
    {
        return self::send(true, $message, $data, $code);
    }

    private static function resolve(mixed $data): mixed
    {
        if ($data instanceof ResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            return [
                'items' => $data->resolve(request()),
                'pagination' => [
                    'current_page' => $data->resource->currentPage(),
                    'per_page' => $data->resource->perPage(),
                    'total' => $data->resource->total(),
                    'last_page' => $data->resource->lastPage(),
                ],
            ];
        }

        if ($data instanceof JsonResource) {
            return $data->resolve(request());
        }

        return $data;
    }
}
