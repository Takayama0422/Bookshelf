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
                            <p class="text-sm text-gray-500">登録した書籍</p>
                            <p class="mt-2 text-3xl font-bold text-gray-900">{{ $report['book_count'] }}</p>
                            <p class="mt-1 text-sm text-gray-500">冊</p>
                        </div>

                        <div class="border rounded-lg p-5">
                            <p class="text-sm text-gray-500">お気に入り</p>
                            <p class="mt-2 text-3xl font-bold text-gray-900">{{ $report['favorite_count'] }}</p>
                            <p class="mt-1 text-sm text-gray-500">冊</p>
                        </div>

                        <div class="border rounded-lg p-5">
                            <p class="text-sm text-gray-500">投稿したレビュー</p>
                            <p class="mt-2 text-3xl font-bold text-gray-900">{{ $report['review_count'] }}</p>
                            <p class="mt-1 text-sm text-gray-500">件</p>
                        </div>
                    </div>

                    <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="border rounded-lg p-5">
                            <h3 class="font-semibold text-lg text-gray-800">レビュー傾向</h3>
                            <div class="mt-4 flex items-end">
                                <p class="text-4xl font-bold text-yellow-500">
                                    {{ $report['average_rating'] === null ? '-' : number_format($report['average_rating'], 2) }}
                                </p>
                                <p class="ml-2 pb-1 text-sm text-gray-500">平均評価</p>
                            </div>
                            <p class="mt-3 text-sm text-gray-500">
                                {{ $report['review_count'] }}件のレビューをもとに集計しています。
                            </p>
                        </div>

                        <div class="border rounded-lg p-5">
                            <h3 class="font-semibold text-lg text-gray-800">評価別レビュー件数</h3>
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
