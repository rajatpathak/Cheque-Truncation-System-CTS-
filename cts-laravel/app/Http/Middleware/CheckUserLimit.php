<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckUserLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $user  = $request->user();
        $limit = $user->daily_cheque_limit ?? config('cts.processing.max_cheques_per_user_day');
        $key   = "user_cheque_count_{$user->id}_" . now()->format('Y-m-d');
        $count = Cache::get($key, 0);

        if ($count >= $limit) {
            return response()->json([
                'error'   => 'DAILY_LIMIT_EXCEEDED',
                'message' => "Daily cheque processing limit of {$limit} reached.",
                'count'   => $count,
                'limit'   => $limit,
            ], Response::HTTP_FORBIDDEN);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 201 || $response->getStatusCode() === 200) {
            Cache::increment($key, 1);
            Cache::expire($key, now()->endOfDay());
        }

        return $response;
    }
}
