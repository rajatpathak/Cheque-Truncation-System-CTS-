<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Ldap;
use App\Models\User;
use App\Services\NotificationService;

class AuthController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Authenticate user — supports local DB auth + AD/SSO/LDAP.
     * Enforces: max login attempts, account lock, MFA.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $lockKey  = "login_attempts_{$request->username}";
        $attempts = Cache::get($lockKey, 0);
        $maxAttempts = config('cts.security.max_login_attempts');

        if ($attempts >= $maxAttempts) {
            return response()->json([
                'error'   => 'ACCOUNT_LOCKED',
                'message' => "Account locked after {$maxAttempts} failed attempts. Contact your administrator.",
            ], 423);
        }

        // Try LDAP/AD first
        $user = $this->authenticateViaLDAP($request->username, $request->password)
              ?? $this->authenticateLocal($request->username, $request->password);

        if (!$user) {
            Cache::put($lockKey, $attempts + 1, now()->addMinutes(config('cts.security.lockout_minutes')));
            return response()->json(['error' => 'INVALID_CREDENTIALS'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['error' => 'ACCOUNT_DISABLED'], 403);
        }

        // Check password expiry
        if ($user->password_changed_at && $user->password_changed_at->diffInDays() > config('cts.security.password_expiry_days')) {
            return response()->json([
                'error'   => 'PASSWORD_EXPIRED',
                'message' => 'Your password has expired. Please change it.',
            ], 403);
        }

        Cache::forget($lockKey);

        // If MFA is enabled, issue temp token and prompt for OTP
        if ($user->mfa_enabled) {
            $tempToken = $user->createToken('temp-mfa', ['mfa:pending'], now()->addMinutes(5))->plainTextToken;
            $this->sendMFAOTP($user);
            return response()->json([
                'status'        => 'MFA_REQUIRED',
                'temp_token'    => $tempToken,
                'mfa_methods'   => $user->mfa_methods,
            ]);
        }

        $token = $user->createToken('cts-session', ['*'], now()->addHours(8))->plainTextToken;

        return response()->json([
            'status'      => 'SUCCESS',
            'token'       => $token,
            'user'        => $this->userPayload($user),
            'expires_in'  => 8 * 3600,
        ]);
    }

    public function verifyMFA(Request $request): JsonResponse
    {
        $request->validate(['otp' => 'required|string|size:6', 'temp_token' => 'required|string']);

        $user = $request->user();
        $storedOTP = Cache::get("mfa_otp_{$user->id}");

        if (!$storedOTP || $storedOTP !== $request->otp) {
            return response()->json(['error' => 'INVALID_OTP'], 401);
        }

        Cache::forget("mfa_otp_{$user->id}");
        $user->tokens()->where('name', 'temp-mfa')->delete();

        $token = $user->createToken('cts-session', ['*'], now()->addHours(8))->plainTextToken;

        return response()->json([
            'status'  => 'SUCCESS',
            'token'   => $token,
            'user'    => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['status' => 'LOGGED_OUT']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:12|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{12,}$/',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'INCORRECT_CURRENT_PASSWORD'], 422);
        }

        $user->update([
            'password'           => Hash::make($request->new_password),
            'password_changed_at'=> now(),
        ]);

        return response()->json(['status' => 'PASSWORD_CHANGED']);
    }

    public function ssoCallback(Request $request): JsonResponse
    {
        // Handle AD/SSO SAML/OAuth callback
        $samlData = $request->input('saml_response');
        $username = $this->parseSAMLResponse($samlData);

        $user = User::where('username', $username)->firstOrFail();
        $token = $user->createToken('cts-sso-session', ['*'], now()->addHours(8))->plainTextToken;

        return response()->json(['token' => $token, 'user' => $this->userPayload($user)]);
    }

    private function authenticateViaLDAP(string $username, string $password): ?User
    {
        try {
            if (!env('LDAP_ENABLED', false)) return null;

            $ldapConn = ldap_connect(env('LDAP_HOST'), (int) env('LDAP_PORT', 389));
            ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
            $bind = @ldap_bind($ldapConn, "uid={$username},ou=users," . env('LDAP_BASE_DN'), $password);

            if ($bind) {
                return User::where('username', $username)->first();
            }
        } catch (\Exception $e) {}
        return null;
    }

    private function authenticateLocal(string $username, string $password): ?User
    {
        $user = User::where('username', $username)->first();
        return ($user && Hash::check($password, $user->password)) ? $user : null;
    }

    private function sendMFAOTP(User $user): void
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("mfa_otp_{$user->id}", $otp, now()->addMinutes(5));

        if (in_array('SMS', $user->mfa_methods ?? [])) {
            $this->notifications->sendSMS($user->mobile, "CTS Login OTP: {$otp}. Valid for 5 minutes. Do not share.");
        }
    }

    private function parseSAMLResponse(string $saml): string
    {
        // Decode and parse SAML response
        $decoded = base64_decode($saml);
        $xml = simplexml_load_string($decoded);
        return (string) $xml->xpath('//saml:NameID')[0] ?? '';
    }

    private function userPayload(User $user): array
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'username'    => $user->username,
            'branch_code' => $user->branch_code,
            'roles'       => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'limits'      => [
                'daily_cheque_limit' => $user->daily_cheque_limit,
            ],
        ];
    }
}
