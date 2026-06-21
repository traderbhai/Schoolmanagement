<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Jobs\SendBulkNoticeEmail;
use App\Models\Notice;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeOfficialNoticeManagement($request);

        $query = Notice::with('user')->latest();

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($audience = $request->query('audience')) {
            if (in_array($audience, ['all', 'students', 'teachers', 'admin'], true)) {
                $query->where('audience', $audience);
            }
        }

        if ($status = $request->query('status')) {
            if ($status === 'published') {
                $query->where('is_published', true);
            } elseif ($status === 'draft') {
                $query->where('is_published', false);
            } elseif ($status === 'active') {
                $query->active();
            } elseif ($status === 'scheduled') {
                $query->where('is_published', true)->where('publish_date', '>', now());
            } elseif ($status === 'expired') {
                $query->whereNotNull('expiry_date')->where('expiry_date', '<', now());
            }
        }

        $notices = $query->paginate(15)->withQueryString();
        $filters = [
            'search' => $search ?? '',
            'audience' => $audience ?: '',
            'status' => $status ?: '',
        ];

        return view('admin.notices.index', compact('notices', 'filters'));
    }

    public function create()
    {
        $this->authorizeOfficialNoticeManagement(request());

        return view('admin.notices.create');
    }

    public function store(Request $request)
    {
        $this->authorizeOfficialNoticeManagement($request);

        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'content'      => 'required|string',
            'audience'     => 'required|in:all,students,teachers,admin',
            'publish_date' => 'required|date',
            'expiry_date'  => 'nullable|date|after:publish_date',
            'is_published' => 'boolean',
        ]);
        $data['user_id'] = auth()->id();
        $data['is_published'] = $request->boolean('is_published');
        $notice = Notice::create($data);
        if ($this->shouldDispatchStudentNoticeEmail($notice)) {
            SendBulkNoticeEmail::dispatch($notice);
        }
        return redirect()->route('admin.notices.index')->with('success', 'Notice published.');
    }

    public function show(Notice $notice)
    {
        $this->authorizeOfficialNoticeManagement(request());

        return view('admin.notices.show', compact('notice'));
    }

    public function edit(Notice $notice)
    {
        $this->authorizeOfficialNoticeManagement(request());

        return view('admin.notices.edit', compact('notice'));
    }

    public function update(Request $request, Notice $notice)
    {
        $this->authorizeOfficialNoticeManagement($request);

        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'content'      => 'required|string',
            'audience'     => 'required|in:all,students,teachers,admin',
            'publish_date' => 'required|date',
            'expiry_date'  => 'nullable|date|after:publish_date',
            'is_published' => 'boolean',
        ]);
        $data['is_published'] = $request->boolean('is_published');
        if ($message = $this->publishedNoticeMutationBlocker($notice, $data)) {
            return back()->withErrors(['notice' => $message])->withInput();
        }

        $notice->update($data);
        return redirect()->route('admin.notices.index')->with('success', 'Notice updated.');
    }

    public function destroy(Notice $notice)
    {
        $this->authorizeOfficialNoticeManagement(request());

        $notice->delete();
        return redirect()->route('admin.notices.index')->with('success', 'Notice archived. Communication history was preserved.');
    }

    private function shouldDispatchStudentNoticeEmail(Notice $notice): bool
    {
        return $notice->is_published
            && in_array($notice->audience, ['all', 'students'], true)
            && $notice->publish_date?->lte(now())
            && (! $notice->expiry_date || $notice->expiry_date->gte(now()));
    }

    private function publishedNoticeMutationBlocker(Notice $notice, array $data): ?string
    {
        if (! $notice->is_published || $notice->publish_date?->gt(now())) {
            return null;
        }

        foreach (['title', 'content', 'audience'] as $field) {
            if (array_key_exists($field, $data) && (string) $notice->{$field} !== (string) $data[$field]) {
                return 'Published notices cannot have their title, content, audience, or publish date changed. Archive the notice and create a corrected notice instead.';
            }
        }

        if (array_key_exists('publish_date', $data) && $notice->publish_date?->toDateString() !== (string) $data['publish_date']) {
            return 'Published notices cannot have their title, content, audience, or publish date changed. Archive the notice and create a corrected notice instead.';
        }

        return null;
    }

    private function authorizeOfficialNoticeManagement(Request $request): void
    {
        abort_unless($request->user() && AccessControl::canManageOfficialNotices($request->user()), 403);
    }
}
