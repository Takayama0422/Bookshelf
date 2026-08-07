<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            通知一覧
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($notifications->isEmpty())
                        <p class="text-gray-500">通知はありません。</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状態</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">通知内容</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">通知種別</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">書籍タイトル</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">読書計画ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">通知日時</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($notifications as $notification)
                                        @php
                                            $data = $notification->data ?? [];
                                            $title = data_get($data, 'title') ?? '通知';
                                            $body = data_get($data, 'body') ?? data_get($data, 'message') ?? '通知内容はありません。';
                                            $timing = data_get($data, 'timing') ?? data_get($data, 'notification_type');
                                            $bookTitle = data_get($data, 'book_title');
                                            $planId = data_get($data, 'plan_id');
                                        @endphp
                                        <tr class="{{ $notification->unread() ? 'bg-blue-50' : '' }}">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($notification->unread())
                                                    <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">未読</span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">既読</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4"><p class="font-semibold">{{ $title }}</p><p>{{ $body }}</p></td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $timing ?? '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $bookTitle ?? '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $planId ?? '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $notification->created_at->format('Y-m-d H:i') }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @if($notification->unread())
                                                    <form action="{{ route('notifications.read', $notification) }}" method="POST" class="inline" novalidate>
                                                        @csrf
                                                        <button type="submit" class="text-indigo-600 hover:text-indigo-900">既読にする</button>
                                                    </form>
                                                @else
                                                    <span class="text-gray-500">既読済み</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6">
                            {{ $notifications->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
