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
}
