<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->isMethod('POST')) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');
        if (!$key) {
            return $next($request);
        }

        $orgId = defined('CURRENT_ORGANIZATION_ID') ? CURRENT_ORGANIZATION_ID : ($request->user()->organization_id ?? 1);
        $userId = $request->user()->id ?? null;
        
        $lockKey = "idempotency_lock_{$orgId}_{$userId}_{$key}";
        if (!Cache::add($lockKey, true, 60)) {
            return response()->json(['success' => false, 'message' => 'Concurrent request in progress.'], 409);
        }

        try {
            $existing = DB::table('idempotency_keys')
                ->where('organization_id', $orgId)
                ->where('user_id', $userId)
                ->where('key', $key)
                ->first();

            if ($existing) {
                $currentHash = md5(json_encode($request->all()));
                if ($existing->request_hash !== $currentHash) {
                    return response()->json(['success' => false, 'message' => 'Idempotency key already used for a different payload.'], 422);
                }
                
                return response(json_decode($existing->response_body, true), $existing->response_status)
                        ->header('Idempotent-Replayed', 'true');
            }

            $response = $next($request);

            DB::table('idempotency_keys')->insert([
                'organization_id' => $orgId,
                'user_id' => $userId,
                'key' => $key,
                'method' => $request->method(),
                'path' => $request->path(),
                'request_hash' => md5(json_encode($request->all())),
                'response_status' => $response->status(),
                'response_body' => $response->getContent(),
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return $response;
        } finally {
            Cache::forget($lockKey);
        }
    }
}
