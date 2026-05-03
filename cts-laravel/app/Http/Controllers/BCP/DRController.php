<?php

namespace App\Http\Controllers\BCP;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\DRDrill;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;

class DRController extends Controller
{
    public function __construct(private NotificationService $notify) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(DRDrill::orderByDesc('created_at')->paginate(10));
    }

    public function schedule(Request $request): JsonResponse
    {
        $request->validate([
            'drill_type'   => 'required|in:SCHEDULED,UNSCHEDULED',
            'scheduled_at' => 'required|date|after:now',
            'participants' => 'required|array|min:1',
        ]);

        $drill = DRDrill::create([
            'drill_type'     => $request->drill_type,
            'initiated_by'   => $request->user()->name,
            'scheduled_at'   => $request->scheduled_at,
            'participants'   => $request->participants,
            'status'         => 'SCHEDULED',
            'sla_rto_target' => config('cts.sla.rto_minutes'),
            'sla_rpo_target' => config('cts.sla.rpo_minutes'),
        ]);

        $this->notify->notifyITTeam(
            "DR Drill scheduled for {$request->scheduled_at} by {$request->user()->name}",
            'INFO'
        );

        return response()->json($drill, 201);
    }

    public function start(Request $request, int $id): JsonResponse
    {
        $drill = DRDrill::findOrFail($id);
        $drill->update(['status' => 'IN_PROGRESS', 'started_at' => now()]);

        // Trigger automatic DC→DR switchover and measure time
        $startTime = microtime(true);
        $switchResult = app(\App\Services\ReplicationMonitorService::class)
                            ->initiateFailover($request->user()->name, "DR Drill #{$id}");
        $elapsed = round((microtime(true) - $startTime), 2);

        $drill->update(['dc_to_dr_switchover_time_seconds' => $elapsed]);

        return response()->json([
            'status'              => 'DRILL_STARTED',
            'switchover_seconds'  => $elapsed,
            'switch_result'       => $switchResult,
        ]);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'outcome'       => 'required|in:PASS,FAIL,PARTIAL',
            'observations'  => 'nullable|string',
            'rto_achieved'  => 'required|numeric',
            'rpo_achieved'  => 'required|numeric',
        ]);

        $drill = DRDrill::findOrFail($id);
        $drill->update([
            'status'                         => 'COMPLETED',
            'completed_at'                   => now(),
            'outcome'                        => $request->outcome,
            'observations'                   => $request->observations,
            'rto_achieved_minutes'           => $request->rto_achieved,
            'rpo_achieved_minutes'           => $request->rpo_achieved,
            'dr_to_dc_switchover_time_seconds'=> $request->dr_to_dc_seconds ?? 0,
        ]);

        // Switch back to DC after successful drill
        if ($request->outcome === 'PASS') {
            cache()->put('active_node', 'dc', 86400);
        }

        return response()->json(['status' => 'DRILL_COMPLETED', 'outcome' => $request->outcome]);
    }

    public function report(Request $request, int $id): \Illuminate\Http\Response
    {
        $drill = DRDrill::findOrFail($id);

        $pdf = Pdf::loadView('reports.dr-drill', [
            'drill'     => $drill,
            'bank_name' => config('cts.bank.name'),
            'generated_at' => now()->format('d-M-Y H:i:s'),
        ]);

        return $pdf->download("dr_drill_report_{$drill->id}.pdf");
    }
}
