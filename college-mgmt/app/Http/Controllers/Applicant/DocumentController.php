<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\ApplicantDocument;
use App\Models\RequiredDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        return view('applicant.documents.index', compact('applicant', 'requiredDocs', 'uploaded'));
    }

    public function upload(Request $request, RequiredDocument $requiredDocument)
    {
        $applicant = auth()->user()->applicant()->firstOrFail();

        $formats = array_filter(array_map('trim', explode(',', $requiredDocument->accepted_formats ?? 'pdf,jpg,png')));
        $mimes = implode(',', array_map(fn($f) => match($f) {
            'jpg'  => 'jpeg,jpg',
            'png'  => 'png',
            'pdf'  => 'pdf',
            default => $f
        }, $formats));

        $maxKb = $requiredDocument->max_size_kb ?? 2048;

        $request->validate([
            'document' => "required|file|mimes:{$mimes}|max:{$maxKb}",
        ]);

        $file = $request->file('document');
        $dir  = "applicant-documents/{$applicant->id}";
        $path = $file->store($dir, 'local');

        // Delete existing upload if any
        $existing = ApplicantDocument::where('applicant_id', $applicant->id)
            ->where('required_document_id', $requiredDocument->id)
            ->first();

        if ($existing) {
            Storage::disk('local')->delete($existing->file_path);
            $existing->delete();
        }

        ApplicantDocument::create([
            'applicant_id'         => $applicant->id,
            'required_document_id' => $requiredDocument->id,
            'file_path'            => $path,
            'original_name'        => $file->getClientOriginalName(),
            'file_size_kb'         => (int) ceil($file->getSize() / 1024),
            'status'               => 'pending',
        ]);

        return back()->with('success', 'Document uploaded successfully.');
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

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }
}
