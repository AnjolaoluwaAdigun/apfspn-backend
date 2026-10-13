<?php

namespace App\Http\Controllers;

use App\Http\Resources\HealthResource;
use App\Support\JsonResponser;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return JsonResponser::success(new HealthResource([
            'status' => 'ok',
            'app' => config('app.name'),
            'version' => config('apfspn.api_version'),
            'time' => now(),
        ]), 'Service is healthy.');
    }
}
