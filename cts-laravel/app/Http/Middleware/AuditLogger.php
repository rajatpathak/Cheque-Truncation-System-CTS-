<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\AuditTrail;
use Symfony\Component\HttpFoundation\Response;

class AuditLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user()) {
            AuditTrail::create([
                'user_id'        => $request->user()->id,
                'user_name'      => $request->user()->name,
                'branch_code'    => $request->user()->branch_code,
                'ip_address'     => $request->ip(),
                'action'         => $request->method() . ' ' . $request->path(),
                'module'         => $this->resolveModule($request->path()),
                'request_body'   => $this->sanitize($request->all()),
                'response_code'  => $response->getStatusCode(),
                'timestamp'      => now(),
                'session_id'     => $request->session()->getId(),
            ]);
        }

        return $response;
    }

    private function resolveModule(string $path): string
    {
        $map = [
            'auth'        => 'MODULE_01_AUTH',
            'outward'     => 'MODULE_02_OUTWARD',
            'inward'      => 'MODULE_03_INWARD',
            'fraud'       => 'MODULE_04_FRAUD',
            'returns'     => 'MODULE_05_RETURNS',
            'signatures'  => 'MODULE_06_PKI',
            'integration' => 'MODULE_07_INTEGRATION',
            'reports'     => 'MODULE_08_REPORTING',
            'images'      => 'MODULE_09_IMAGE',
            'admin'       => 'MODULE_10_ADMIN',
            'bcp'         => 'MODULE_11_BCP',
        ];

        foreach ($map as $segment => $module) {
            if (str_contains($path, $segment)) {
                return $module;
            }
        }

        return 'UNKNOWN';
    }

    private function sanitize(array $data): array
    {
        $sensitive = ['password', 'pin', 'otp', 'secret', 'token', 'key'];
        foreach ($sensitive as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***REDACTED***';
            }
        }
        return $data;
    }
}
