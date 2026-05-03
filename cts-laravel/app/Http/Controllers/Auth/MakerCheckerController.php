<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\PendingApproval;
use Illuminate\Support\Facades\Http;

class MakerCheckerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $approvals = PendingApproval::where('status', 'PENDING')
            ->where('expires_at', '>', now())
            ->where('maker_id', '!=', $request->user()->id) // Checker cannot approve own requests
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($approvals);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $request->validate(['remarks' => 'nullable|string|max:500']);

        $pending = PendingApproval::where('id', $id)->where('status', 'PENDING')->firstOrFail();

        if ($pending->maker_id === $request->user()->id) {
            return response()->json(['error' => 'SELF_APPROVAL_NOT_ALLOWED'], 403);
        }

        if ($pending->isExpired()) {
            $pending->update(['status' => 'EXPIRED']);
            return response()->json(['error' => 'APPROVAL_EXPIRED'], 410);
        }

        $checkerField  = $pending->checker1_id ? 'checker2' : 'checker1';
        $pending->update([
            "{$checkerField}_id"      => $request->user()->id,
            "{$checkerField}_at"      => now(),
            "{$checkerField}_remarks" => $request->remarks,
        ]);

        // If all required levels approved — execute the original request
        $allApproved = $pending->checker_level === 1
            ? (bool) $pending->checker1_id
            : ($pending->checker1_id && $pending->checker2_id);

        if ($allApproved) {
            $pending->update(['status' => 'APPROVED']);
            $result = $this->executeApprovedRequest($pending);
            return response()->json(['status' => 'APPROVED', 'result' => $result]);
        }

        return response()->json(['status' => 'PARTIALLY_APPROVED', 'message' => 'Waiting for level-2 checker.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $pending = PendingApproval::where('id', $id)->where('status', 'PENDING')->firstOrFail();

        if ($pending->maker_id === $request->user()->id) {
            return response()->json(['error' => 'SELF_REJECTION_NOT_ALLOWED'], 403);
        }

        $pending->update([
            'status'           => 'REJECTED',
            'rejected_by'      => $request->user()->id,
            'rejected_at'      => now(),
            'rejection_reason' => $request->reason,
        ]);

        return response()->json(['status' => 'REJECTED']);
    }

    private function executeApprovedRequest(PendingApproval $pending): mixed
    {
        $payload = $pending->decryptPayload();

        // Re-dispatch the original API request internally
        $request = Request::create(
            $pending->route,
            $pending->method,
            $payload
        );
        $request->headers->set('X-Internal-Execution', 'maker-checker-approved');

        return app()->handle($request)->getContent();
    }
}
