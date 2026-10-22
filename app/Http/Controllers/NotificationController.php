<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();
        \Illuminate\Support\Facades\Cache::forget('user_topbar_notifs_'.$request->user()->id);
        return back()->with('success', 'Notification marked as read.');
    }
}
