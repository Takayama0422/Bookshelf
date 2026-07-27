<?php

namespace App\Http\Controllers;

use App\Services\ReadingReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingReportController extends Controller
{
    public function __invoke(Request $request, ReadingReportService $readingReportService): View
    {
        $report = $readingReportService->summarizeFor($request->user());

        return view('reading-report.show', compact('report'));
    }
}
