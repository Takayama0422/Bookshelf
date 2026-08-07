<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GenreController extends Controller
{
    public function index(): View
    {
        $genres = Genre::query()
            ->withCount('books')
            ->orderBy('name')
            ->get();

        return view('genres.index', compact('genres'));
    }

    public function show(Genre $genre): View
    {
        $books = $genre->books()
            ->with('genres')
            ->oldest('books.created_at')
            ->paginate(10);

        return view('genres.show', compact('genre', 'books'));
    }

    public function create(): View
    {
        return view('genres.create');
    }

    public function store(StoreGenreRequest $request): RedirectResponse
    {
        $genre = Genre::create($request->validated());

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを作成しました。');
    }

    public function edit(Genre $genre): View
    {
        return view('genres.edit', compact('genre'));
    }

    public function update(UpdateGenreRequest $request, Genre $genre): RedirectResponse
    {
        $genre->update($request->validated());

        return redirect()
            ->route('genres.show', $genre)
            ->with('success', 'ジャンルを更新しました。');
    }

    public function destroy(Genre $genre): RedirectResponse
    {
        if ($genre->books()->exists()) {
            return redirect()
                ->route('genres.index')
                ->with('error', '書籍に利用されているジャンルは削除できません。');
        }

        $genre->delete();

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを削除しました。');
    }
}
