<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ReportController
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:300'],
            'category' => ['required', Rule::in(['rights', 'consent', 'safety', 'other'])],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
        ]);
        $report = Report::query()->create($data);
        Log::info('cms.report.received', ['report_id' => $report->getKey()]);

        return redirect('/report')->with('status', 'Report received by this site operator.');
    }

    public function update(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'reviewing', 'removed', 'rejected', 'appealed', 'closed'])],
            'operator_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $from = $report->status;
        $to = $data['status'];
        $allowed = [
            'open' => ['reviewing', 'closed'],
            'reviewing' => ['removed', 'rejected', 'closed'],
            'removed' => ['appealed', 'closed'],
            'rejected' => ['appealed', 'closed'],
            'appealed' => ['reviewing', 'closed'],
            'closed' => [],
        ];
        abort_unless(in_array($to, $allowed[$from] ?? [], true), 422, 'Invalid case transition.');

        $report->forceFill([
            'status' => $to,
            'operator_note' => $data['operator_note'] ?? null,
            'reviewed_at' => $to === 'reviewing' && $report->reviewed_at === null ? now() : $report->reviewed_at,
            'resolved_at' => in_array($to, ['removed', 'rejected', 'closed'], true) ? now() : null,
        ])->save();

        Log::info('cms.report.status_changed', ['report_id' => $report->getKey(), 'from' => $from, 'to' => $to]);

        return redirect('/studio/reports')->with('status', 'Report case updated.');
    }
}
