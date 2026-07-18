@extends('layouts.admin')

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Bulk Email</h1>

    @if(session('success'))
        <div class="bg-green-50 border border-green-300 text-green-800 rounded px-4 py-3 mb-4">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.bulk-mail.send') }}" id="bulk-form">
        @csrf

        {{-- Audience --}}
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Select Audience</h2>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Audience Type</label>
                <select name="audience" id="audience" class="w-full border rounded px-3 py-2 text-sm" onchange="toggleFilters()">
                    <option value="all_students">All Active Students</option>
                    <option value="all_applicants">All Applicants</option>
                    <option value="applicants_by_status">Applicants by Status</option>
                    <option value="program_batch">Students by Program / Batch</option>
                    <option value="role">By Role</option>
                </select>
            </div>

            {{-- Applicant status filter --}}
            <div id="filter-applicant-status" class="hidden mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Applicant Status</label>
                <select aria-label="Applicant Status" name="applicant_status" class="w-full border rounded px-3 py-2 text-sm">
                    @foreach(['draft','submitted','under_review','shortlisted','selected','rejected','withdrawn'] as $s)
                        <option value="{{ $s }}">{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Program / Batch filter --}}
            <div id="filter-program-batch" class="hidden grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Program</label>
                    <select aria-label="Program" name="program_id" class="w-full border rounded px-3 py-2 text-sm">
                        <option value="">All Programs</option>
                        @foreach($programs as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Batch</label>
                    <select aria-label="Batch" name="batch_id" class="w-full border rounded px-3 py-2 text-sm">
                        <option value="">All Batches</option>
                        @foreach($batches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Role filter --}}
            <div id="filter-role" class="hidden mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                <select aria-label="Role" name="role" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="student">Student</option>
                    <option value="applicant">Applicant</option>
                    <option value="teacher">Teacher</option>
                    <option value="admission_officer">Admission Officer</option>
                    <option value="admission_head">Admission Head</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" onclick="previewCount()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm px-4 py-2 rounded">
                    Preview Count
                </button>
                <span id="count-display" class="text-sm text-gray-600"></span>
            </div>
        </div>

        {{-- Email Content --}}
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Email Content</h2>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Subject <span class="text-red-500">*</span></label>
                <input aria-label="Email subject" type="text" name="subject" value="{{ old('subject') }}" class="w-full border rounded px-3 py-2 text-sm" required placeholder="Email subject">
                @error('subject') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Message Body <span class="text-red-500">*</span></label>
                <textarea aria-label="Bulk email message" name="body" rows="10" class="w-full border rounded px-3 py-2 text-sm" required placeholder="Write your message here...">{{ old('body') }}</textarea>
                @error('body') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" onclick="return confirm('Send this bulk email to all selected recipients? Confirm audience filters, subject, message body, and unsubscribe/contact policy before dispatch.')"
                class="bg-blue-900 hover:bg-blue-800 text-white font-semibold px-6 py-2 rounded">
                Send Bulk Email
            </button>
        </div>
    </form>
</div>

<script>
function toggleFilters() {
    const val = document.getElementById('audience').value;
    document.getElementById('filter-applicant-status').classList.toggle('hidden', val !== 'applicants_by_status');
    document.getElementById('filter-program-batch').classList.toggle('hidden', val !== 'program_batch');
    document.getElementById('filter-role').classList.toggle('hidden', val !== 'role');
}

function previewCount() {
    const form = document.getElementById('bulk-form');
    const data = new FormData(form);
    const params = new URLSearchParams(data).toString();

    fetch('{{ route("admin.bulk-mail.count") }}?' + params)
        .then(r => r.json())
        .then(j => {
            document.getElementById('count-display').textContent = j.count + ' recipient(s) will receive this email.';
        });
}
</script>
@endsection
