<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class RankingController extends Controller
{
    /**
     * レビュー評価の平均値と件数から上位10件の書籍ランキングを生成する。
     *
     * 未評価の書籍を後方へ配置し、平均評価、レビュー件数、書籍IDの順で順位を確定する。
     *
     * @return View 書籍ランキング画面
     */
    public function index(): View
    {
        $rankedBooks = Book::query()
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderByRaw('reviews_avg_rating IS NULL')
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('reviews_count')
            ->orderBy('id')
            ->limit(10)
            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
