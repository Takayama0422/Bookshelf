<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('マイ読書レポート') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if(! $report['has_data'])
                        <p class="text-gray-500 text-center py-8">
                            まだ読書レポートに表示できるデータがありません。
                        </p>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="border rounded-lg p-5">
                            <p class="text-sm text-gray-500">総レビュー数</p>
                            <p class="mt-2 text-3xl font-bold text-gray-900">{{ $report['review_count'] }}</p>
                            <p class="mt-1 text-sm text-gray-500">件</p>
                        </div>

                        <div class="border rounded-lg p-5">
                            <p class="text-sm text-gray-500">読了冊数</p>
                            <p class="mt-2 text-3xl font-bold text-gray-900">{{ $report['completed_book_count'] }}</p>
                            <p class="mt-1 text-sm text-gray-500">冊</p>
                        </div>

                        <div class="border rounded-lg p-5">
                            <p class="text-sm text-gray-500">平均評価</p>
                            <p class="mt-2 text-3xl font-bold text-yellow-500">
                                {{ $report['average_rating'] === null ? '-' : number_format($report['average_rating'], 2) }}
                            </p>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ $report['review_count'] }}件のレビューをもとに集計
                            </p>
                        </div>
                    </div>

                    <div class="mt-8 border rounded-lg p-5">
                        <h3 class="font-semibold text-lg text-gray-800">評価分布</h3>
                        <div class="mt-4 space-y-3">
                            @foreach($report['rating_counts'] as $rating => $count)
                                <div class="flex items-center">
                                    <div class="w-16 text-sm text-gray-600">評価{{ $rating }}</div>
                                    <div class="flex-1 mx-3 h-3 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-3 bg-yellow-400 rounded-full" style="width: {{ $report['review_count'] > 0 ? ($count / $report['review_count']) * 100 : 0 }}%"></div>
                                    </div>
                                    <div class="w-12 text-right text-sm text-gray-700">{{ $count }}件</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="border rounded-lg p-5">
                            <h3 class="font-semibold text-lg text-gray-800">高評価書籍TOP5</h3>
                            @if($report['top_rated_books']->isEmpty())
                                <p class="mt-4 text-sm text-gray-500">該当データなし</p>
                            @else
                                <ol class="mt-4 space-y-3">
                                    @foreach($report['top_rated_books'] as $review)
                                        <li class="flex items-center justify-between">
                                            <a href="{{ route('books.show', $review->book) }}" class="text-indigo-600 hover:text-indigo-900">
                                                {{ $review->book->title }}
                                            </a>
                                            <span class="text-sm text-gray-600">評価{{ $review->rating }}</span>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>

                        <div class="border rounded-lg p-5">
                            <h3 class="font-semibold text-lg text-gray-800">ジャンル別評価傾向TOP5</h3>
                            @if($report['genre_trends']->isEmpty())
                                <p class="mt-4 text-sm text-gray-500">該当データなし</p>
                            @else
                                <ol class="mt-4 space-y-3">
                                    @foreach($report['genre_trends'] as $trend)
                                        <li class="flex items-center justify-between">
                                            <a href="{{ route('genres.show', $trend['genre']) }}" class="text-indigo-600 hover:text-indigo-900">
                                                {{ $trend['genre']->name }}
                                            </a>
                                            <span class="text-sm text-gray-600">
                                                平均{{ number_format($trend['average_rating'], 2) }} / {{ $trend['review_count'] }}件
                                            </span>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
