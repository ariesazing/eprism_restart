<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function markRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable_id === $request->user()->id && $notification->notifiable_type === $request->user()->getMorphClass(), 403);

        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('dashboard'));
    }
}
