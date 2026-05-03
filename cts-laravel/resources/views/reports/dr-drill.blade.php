<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>DR Drill Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h1 { font-size: 16px; text-align: center; }
        h2 { font-size: 13px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background: #ccc; padding: 5px; border: 1px solid #999; }
        td { padding: 5px; border: 1px solid #999; }
        .pass { color: green; font-weight: bold; }
        .fail { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $bank_name }} — DR Drill Report</h1>
    <p style="text-align:center">Tender Reference: GEM/2026/B/7367951 | Generated: {{ $generated_at }}</p>

    <h2>Drill Summary</h2>
    <table>
        <tr><th>Drill ID</th><td>{{ $drill->id }}</td><th>Type</th><td>{{ $drill->drill_type }}</td></tr>
        <tr><th>Initiated By</th><td>{{ $drill->initiated_by }}</td><th>Status</th><td>{{ $drill->status }}</td></tr>
        <tr><th>Scheduled At</th><td>{{ $drill->scheduled_at }}</td><th>Started At</th><td>{{ $drill->started_at }}</td></tr>
        <tr><th>Completed At</th><td>{{ $drill->completed_at }}</td>
            <th>Outcome</th>
            <td class="{{ strtolower($drill->outcome ?? '') }}">{{ $drill->outcome }}</td>
        </tr>
    </table>

    <h2>SLA Performance</h2>
    <table>
        <tr><th>Metric</th><th>Target</th><th>Achieved</th><th>Status</th></tr>
        <tr>
            <td>RTO (Recovery Time Objective)</td>
            <td>{{ $drill->sla_rto_target }} minutes</td>
            <td>{{ $drill->rto_achieved_minutes }} minutes</td>
            <td class="{{ ($drill->rto_achieved_minutes ?? 999) <= $drill->sla_rto_target ? 'pass' : 'fail' }}">
                {{ ($drill->rto_achieved_minutes ?? 999) <= $drill->sla_rto_target ? 'PASS' : 'FAIL' }}
            </td>
        </tr>
        <tr>
            <td>RPO (Recovery Point Objective)</td>
            <td>{{ $drill->sla_rpo_target }} minutes</td>
            <td>{{ $drill->rpo_achieved_minutes }} minutes</td>
            <td class="{{ ($drill->rpo_achieved_minutes ?? 999) <= $drill->sla_rpo_target ? 'pass' : 'fail' }}">
                {{ ($drill->rpo_achieved_minutes ?? 999) <= $drill->sla_rpo_target ? 'PASS' : 'FAIL' }}
            </td>
        </tr>
        <tr>
            <td>DC → DR Switchover Time</td>
            <td>≤ 1800 seconds</td>
            <td>{{ $drill->dc_to_dr_switchover_time_seconds }} seconds</td>
            <td class="{{ ($drill->dc_to_dr_switchover_time_seconds ?? 9999) <= 1800 ? 'pass' : 'fail' }}">
                {{ ($drill->dc_to_dr_switchover_time_seconds ?? 9999) <= 1800 ? 'PASS' : 'FAIL' }}
            </td>
        </tr>
    </table>

    <h2>Observations</h2>
    <p>{{ $drill->observations ?? 'No observations recorded.' }}</p>

    <h2>Participants</h2>
    <ul>
        @foreach ($drill->participants ?? [] as $participant)
            <li>{{ $participant }}</li>
        @endforeach
    </ul>
</body>
</html>
