<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\PendingApproval;
use Symfony\Component\HttpFoundation\Response;

class MakerCheckerMiddleware
{
    /**
     * Intercept write operations and route them through the Maker-Checker workflow.
     * Checkers cannot approve their own requests.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $pending = PendingApproval::create([
                'maker_id'       => $request->user()->id,
                'maker_name'     => $request->user()->name,
                'branch_code'    => $request->user()->branch_code,
                'module'         => $this->resolveModule($request),
                'action'         => $request->method() . ' ' . $request->path(),
                'payload'        => encrypt(json_encode($request->all())),
                'route'          => $request->path(),
                'method'         => $request->method(),
                'status'         => 'PENDING',
                'checker_level'  => config('cts.processing.dual_verify_threshold') ? 2 : 1,
                'expires_at'     => now()->addHours(24),
            ]);

            return response()->json([
                'status'        => 'PENDING_APPROVAL',
                'approval_id'   => $pending->id,
                'message'       => 'Request submitted for checker approval.',
                'expires_at'    => $pending->expires_at,
            ], Response::HTTP_ACCEPTED);
        }

        return $next($request);
    }

    private function resolveModule(Request $request): string
    {
        return strtoupper(explode('/', $request->path())[1] ?? 'UNKNOWN');
    }
}
