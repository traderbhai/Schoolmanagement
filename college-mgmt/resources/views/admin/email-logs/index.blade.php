@extends('layouts.admin')

@section('content')
<div class="max-w-7xl mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Email Logs</h1>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded shadow p-4 mb-6 flex gap-4 flex-wrap items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Status</label>
            <select name="status" class="border rounded px-3 py-2 text-sm">
                <option value="">All</option>
                <option value="queued" @selected(request('status') === 'queued')>Queued</option>
                <option value="sent" @selected(request('status') === 'sent')>Sent</option>
                <option value="failed" @selected(request('status') === 'failed')>Failed</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Email / Subject..." class="border rounded px-3 py-2 text-sm">
        </div>
        <button type="submit" class="bg-blue-900 text-white px-4 py-2 rounded text-sm">Filter</button>
        <a href="{{ route('admin.email-logs.index') }}" class="text-sm text-gray-500 py-2">Reset</a>
    </form>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">ID</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Type</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">To</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Subject</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Status</th>
                    <th class="px-4 py-3 text-left text-gray-600 font-medium">Queued At</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($logs as $log)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-500">{{ $log->id }}</td>
                    <td class="px-4 py-3 text-xs text-gray-600">{{ class_basename($log->mailable_class) }}</td>
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ $log->to_email }}</div>
                        @if($log->to_name)<div class="text-xs text-gray-500">{{ $log->to_name }}</div>@endif
                    </td>
                    <td class="px-4 py-3 text-gray-700">{{ Str::limit($log->subject, 50) ?: '—' }}</td>
                    <td class="px-4 py-3">
                        @php
                            $cls = match($log->status) {
                                'queued' => 'bg-yellow-100 text-yellow-700',
                                'sent'   => 'bg-green-100 text-green-700',
                                'failed' => 'bg-red-100 text-red-700',
                                default  => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $cls }}">{{ ucfirst($log->status) }}</span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $log->queued_at?->format('d M Y H:i') ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-10 text-gray-400">No email logs found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
@endsection
