<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        $unreadCount = auth()->user()->notifications()
            ->where('is_read', false)
            ->count();
        $layout = $this->layoutFor(auth()->user());

        return view('notifications.index', compact('notifications', 'unreadCount', 'layout'));
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
