<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $baseQuery = auth()->user()->notifications();
        $query = clone $baseQuery;

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $status = (string) $request->query('status', '');
        if ($status === 'unread') {
            $query->where('is_read', false);
        } elseif ($status === 'read') {
            $query->where('is_read', true);
        }

        $type = (string) $request->query('type', '');
        if ($type !== '') {
            $query->where('type', $type);
        }

        $notifications = $query
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();
        $unreadCount = $baseQuery
            ->where('is_read', false)
            ->count();
        $typeOptions = auth()->user()->notifications()
            ->whereNotNull('type')
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');
        $filters = [
            'search' => $search ?? '',
            'status' => $status,
            'type' => $type,
        ];
        $layout = $this->layoutFor(auth()->user());

        return view('notifications.index', compact('notifications', 'unreadCount', 'layout', 'filters', 'typeOptions'));
    }

    public function show(Notification $notification)
    {
        $this->authorize('view', $notification);

        if (!$notification->is_read) {
            $notification->markAsRead();
        }

        $layout = $this->layoutFor(auth()->user());

        return view('notifications.show', compact('notification', 'layout'));
    }

    public function markAsRead(Notification $notification)
    {
        $this->authorize('update', $notification);

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        auth()->user()->notifications()
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function delete(Notification $notification)
    {
        $this->authorize('delete', $notification);

        $notification->delete();

        return response()->json(['success' => true]);
    }

    public function getUnreadCount()
    {
        $count = auth()->user()->notifications()
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    private function layoutFor($user): string
    {
        if ($user?->hasRole('student')) {
            return 'layouts.student';
        }

        if ($user?->hasRole('teacher')) {
            return 'layouts.teacher';
        }

        if ($user?->hasRole('parent')) {
            return 'layouts.parent';
        }

        if ($user?->hasRole('applicant')) {
            return 'layouts.applicant';
        }

        return 'layouts.admin';
    }
}
