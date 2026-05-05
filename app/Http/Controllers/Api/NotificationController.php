<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponseTrait;

    // ===================== GET ALL NOTIFICATIONS =====================
    // GET /api/notifications
    public function index()
    {
        $user          = auth()->user();
        $notifications = $user->notifications()->paginate(20);

        return $this->apiResponse(true, 'إشعاراتك', [
            'unread_count'  => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    // ===================== GET UNREAD ONLY =====================
    // GET /api/notifications/unread
    public function unread()
    {
        $user          = auth()->user();
        $notifications = $user->unreadNotifications()->paginate(20);

        return $this->apiResponse(true, 'الإشعارات غير المقروءة', [
            'unread_count'  => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    // ===================== BADGE COUNT =====================
    // GET /api/notifications/badge
    public function badge()
    {
        $count = auth()->user()->unreadNotifications()->count();

        return $this->apiResponse(true, 'عدد الإشعارات غير المقروءة', [
            'badge_count' => $count,
        ]);
    }

    // ===================== MARK ONE AS READ =====================
    // PUT /api/notifications/{id}/read
    public function markAsRead($id)
    {
        $notification = auth()->user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return $this->apiResponse(false, 'الإشعار غير موجود', null, 404);
        }

        if ($notification->read_at) {
            return $this->apiResponse(true, 'الإشعار مقروء بالفعل', null);
        }

        $notification->markAsRead();

        return $this->apiResponse(true, 'تم تحديد الإشعار كمقروء', [
            'unread_count' => auth()->user()->unreadNotifications()->count(),
        ]);
    }

    // ===================== MARK ALL AS READ =====================
    // PUT /api/notifications/read-all
    public function markAllAsRead()
    {
        $user  = auth()->user();
        $count = $user->unreadNotifications()->count();

        $user->unreadNotifications->markAsRead();

        return $this->apiResponse(true, "تم تحديد {$count} إشعار كمقروء", [
            'marked_count' => $count,
            'unread_count' => 0,
        ]);
    }

    // ===================== DELETE ONE =====================
    // DELETE /api/notifications/{id}
    public function destroy($id)
    {
        $notification = auth()->user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return $this->apiResponse(false, 'الإشعار غير موجود', null, 404);
        }

        $notification->delete();

        return $this->apiResponse(true, 'تم حذف الإشعار', [
            'unread_count' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
}