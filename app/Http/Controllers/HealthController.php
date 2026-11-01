<?php

namespace App\Http\Controllers;

use App\Services\ConnectionAuditService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ConnectionAuditService $audit): JsonResponse
    {
        $result = $audit->audit();

        return response()->json(
            $result,
            $result['healthy'] ? 200 : 503
        );
    }
}
