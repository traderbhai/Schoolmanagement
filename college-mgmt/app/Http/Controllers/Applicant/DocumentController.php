<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\ApplicantDocument;
use App\Models\RequiredDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function index()
    {
        $applicant = auth()->user()->applicant()->with(['program', 'documents.requiredDocument'])->firstOrFail();
        $requiredDocs = RequiredDocument::where('program_id', $applicant->program_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $uploaded = $applicant->documents->keyBy('required_document_id');
        $verifiedCount = $applicant->documents->where('status', 'verified')->count();
        $locked = in_array($applicant->status, ['selected', 'rejected', 'withdrawn']);

        return view('applicant.documents.index', compact('applicant', 'requiredDocs', 'uploaded', 'verifiedCount', 'locked'));
    }

    public function store(Request $request, RequiredDocument $requiredDocument)
    {
        $applicant = auth()->user()->applicant()->firstOrFail();

        if (in_array($applicant->status, ['selected', 'rejected', 'withdrawn'])) {
            return back()->with('error', 'You cannot upload documents at this stage.');
        }

        $formats = $requiredDocument->accepted_formats_array;
        $mimeMap = ['jpg' => 'jpeg,jpg', 'jpeg' => 'jpeg,jpg', 'png' => 'png', 'pdf' => 'pdf'];
        $mimes = implode(',', array_unique(array_filter(array_map(fn($f) => $mimeMap[$f] ?? $f, $formats))));
        $maxKb = $requiredDocument->max_size_kb ?? 2048;

        $request->validate([
            'document' => "required|file|mimes:{$mimes}|max:{$maxKb}",
        ]);

        $file = $request->file('document');
        $ext  = $file->getClientOriginalExtension();
        $slug = Str::slug($requiredDocument->name);

        // Determine version
        $existing = ApplicantDocument::where('applicant_id', $applicant->id)
            ->where('required_document_id', $requiredDocument->id)
            ->first();

        $version = $existing ? ($existing->version + 1) : 1;
        $filename = "{$slug}_v{$version}_" . time() . ".{$ext}";
        $dir  = "applicant-documents/{$applicant->id}";

        $path = Storage::disk('local')->putFileAs($dir, $file, $filename);

        if ($existing) {
            // Delete old file
            Storage::disk('local')->delete($existing->file_path);
            $existing->update([
                'file_path'     => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_size_kb'  => (int) ceil($file->getSize() / 1024),
                'status'        => 'pending',
                'rejection_reason' => null,
                'verified_by'   => null,
                'verified_at'   => null,
                'uploaded_at'   => now(),
                'version'       => $version,
            ]);
        } else {
            ApplicantDocument::create([
                'applicant_id'         => $applicant->id,
                'required_document_id' => $requiredDocument->id,
                'file_path'            => $path,
                'original_name'        => $file->getClientOriginalName(),
                'file_size_kb'         => (int) ceil($file->getSize() / 1024),
                'status'               => 'pending',
                'uploaded_at'          => now(),
                'version'              => 1,
            ]);
        }

        $sizeStr = $file->getSize() >= 1048576
            ? number_format($file->getSize() / 1048576, 1) . ' MB'
            : ceil($file->getSize() / 1024) . ' KB';

        return back()->with('success', "Document uploaded successfully: {$file->getClientOriginalName()} ({$sizeStr})");
    }

    public function destroy(ApplicantDocument $document)
    {
        $applicant = auth()->user()->applicant()->firstOrFail();

        if ($document->applicant_id !== $applicant->id) {
            abort(403);
        }

        if ($document->status === 'verified') {
            return back()->with('error', 'Cannot delete a verified document.');
        }

        if (in_array($applicant->status, ['selected', 'rejected', 'withdrawn'])) {
            return back()->with('error', 'You cannot remove documents at this stage.');
        }

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }
}
