<?php

namespace App\Http\Controllers;

use App\Services\ReadingReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingReportController extends Controller
{
    /**
     * ログインユーザーの読書実績を集計サービスで要約し、レポート画面を表示する。
     *
     * @param  Request  $request  認証済みユーザーを保持するリクエスト
     * @param  ReadingReportService  $readingReportService  読書実績の集計サービス
     * @return View 集計済みレポートを含む読書レポート画面
     */
    public function __invoke(Request $request, ReadingReportService $readingReportService): View
    {
        $report = $readingReportService->summarizeFor($request->user());

        return view('reading-report.show', compact('report'));
    }
}
