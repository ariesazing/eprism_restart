<?php

namespace App\Http\Controllers;

use App\Models\SimilarityCheck;
use App\Similarity\PendingNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The two calls behind the "your similarity check is done" popup (see
 * resources/views/components/similarity-notifier.blade.php): a page that knows a check is still
 * running polls index() until it finishes, and dismiss() marks a finished check as announced.
 */
class SimilarityNotificationController extends Controller
{
    public function index(Request $request, PendingNotifications $pending): JsonResponse
    {
        return response()->json($pending->for($request->user()));
    }

    public function dismiss(Request $request, SimilarityCheck $check): JsonResponse
    {
        abort_unless($check->requested_by === $request->user()->id, 403);

        $check->acknowledge();

        return response()->json(['dismissed' => true]);
    }
}
