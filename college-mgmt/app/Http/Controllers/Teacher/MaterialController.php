<?php
namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{StudyMaterial, Subject, Term, TimetableEntry};
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    private function activeTeacher()
    {
        return auth()->user()->teacher;
    }

    private function ensureActiveTeacher(): void
    {
        abort_unless($this->activeTeacher()?->status === 'active', 403, 'Only active teachers can manage study materials.');
    }

    private function teacherSubjectIds(): array
    {
        $teacher = auth()->user()->teacher;
        if (!$teacher) return [];
        return TimetableEntry::where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn ($version) => $version->where('status', 'published'));
            })
            ->pluck('subject_id')->unique()->toArray();
    }

    private function ensureTeachesSubject(int $subjectId): void
    {
        abort_unless(in_array($subjectId, $this->teacherSubjectIds(), true), 403, 'You do not teach this subject.');
    }

    public function index(Request $request)
    {
        $teacher    = auth()->user()->teacher;
        $subjectIds = $this->teacherSubjectIds();
        $currentTerm = Term::latest('start_date')->first();

        $query = StudyMaterial::whereIn('subject_id', $subjectIds)
            ->where('uploaded_by', auth()->id())
            ->with('subject')
            ->latest();

        if ($request->filled('subject_id')) $query->where('subject_id', $request->subject_id);
        if ($request->filled('type'))       $query->where('type', $request->type);

        $materials = $query->paginate(20)->withQueryString();
        $subjects  = Subject::whereIn('id', $subjectIds)->get();
        $canManageMaterials = $teacher?->status === 'active';

        return view('teacher.materials.index', compact('materials', 'subjects', 'canManageMaterials'));
    }

    public function create()
    {
        $subjects    = Subject::whereIn('id', $this->teacherSubjectIds())->get();
        $currentTerm = Term::latest('start_date')->first();
        $teacher = $this->activeTeacher();
        $actionBlockedReason = match (true) {
            ! $teacher => 'Your login has the Teacher role, but no teacher profile is attached yet. Ask Admin/Academics to link your teacher profile before uploading study materials.',
            $teacher->status !== 'active' => 'Only active teachers can upload study materials.',
            $subjects->isEmpty() => 'No published teaching assignment is linked to your teacher profile yet. Study materials can be uploaded after your subject allocation appears in a published timetable.',
            default => null,
        };

        return view('teacher.materials.create', compact('subjects', 'currentTerm', 'actionBlockedReason'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject_id'   => 'required|exists:subjects,id',
            'title'        => 'required|string|max:255',
            'type'         => 'required|in:pre_read,post_read,notes,reference,slides,video,other',
            'description'  => 'nullable|string|max:1000',
            'file'         => 'nullable|file|max:20480',
            'external_url' => 'nullable|url|max:500',
        ]);
        $this->ensureActiveTeacher();
        $this->ensureTeachesSubject((int) $request->subject_id);

        if (! $request->hasFile('file') && ! $request->filled('external_url') && trim((string) $request->input('description', '')) === '') {
            return back()
                ->withErrors(['file' => 'Attach a file, add an external URL, or enter a description before publishing study material.'])
                ->withInput();
        }

        $path = null;
        $sizeKb = null;
        if ($request->hasFile('file')) {
            $path   = $request->file('file')->store('materials', 'public');
            $sizeKb = (int) ($request->file('file')->getSize() / 1024);
        }

        StudyMaterial::create([
            'subject_id'   => $request->subject_id,
            'uploaded_by'  => auth()->id(),
            'term_id'      => Term::latest('start_date')->first()?->id,
            'title'        => $request->title,
            'description'  => $request->description,
            'type'         => $request->type,
            'file_path'    => $path,
            'external_url' => $request->external_url,
            'file_size_kb' => $sizeKb,
            'is_published' => $request->boolean('is_published', true),
        ]);

        return redirect()->route('teacher.materials.index')->with('success', 'Material uploaded.');
    }

    public function destroy(StudyMaterial $material)
    {
        $this->ensureActiveTeacher();
        if ($material->uploaded_by !== auth()->id()) abort(403);
        $material->delete();
        return back()->with('success', 'Material archived. Teaching history and file reference were preserved.');
    }
}
