@extends('layouts.student')

@section('content')
<div class="max-w-xl mx-auto py-8 px-4">
    <h1 class="text-xl font-bold text-gray-800 mb-6">Notification Settings</h1>

    @if(session('success'))
        <div class="bg-green-50 border border-green-300 text-green-800 rounded px-4 py-3 mb-4">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('student.notifications.update') }}">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-lg shadow p-6 space-y-4">
            <p class="text-sm text-gray-600 mb-2">Control which email notifications you receive.</p>

            @foreach([
                'email_application_updates' => 'Application & Admission Updates',
                'email_payment_updates'     => 'Payment Confirmations & Reminders',
                'email_result_published'    => 'Exam Results Published',
                'email_notices'             => 'Notices & Announcements',
            ] as $key => $label)
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="{{ $key }}" value="1" {{ $pref->$key ? 'checked' : '' }}
                    class="rounded border-gray-300 text-blue-600">
                <span class="text-sm text-gray-700">{{ $label }}</span>
            </label>
            @endforeach

            <div class="pt-4 border-t">
                <button type="submit" class="bg-blue-900 text-white text-sm px-5 py-2 rounded">Save Preferences</button>
            </div>
        </div>
    </form>
</div>
@endsection
