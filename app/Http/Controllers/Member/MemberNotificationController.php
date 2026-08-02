<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberNotification;
use App\Services\Library\DueReminderService;
use App\Services\Library\MemberAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberNotificationController extends Controller
{
    public function __construct(
        private readonly MemberAccountService $memberAccounts,
        private readonly DueReminderService $reminders,
    ) {
    }

    public function index(Request $request): View
    {
        $member = $this->memberAccounts->memberFor($request->user());
        $this->reminders->generateForMember($member);

        $notifications = MemberNotification::query()
            ->where('member_id', $member->id)
            ->latest('created_at')
            ->paginate(15);

        return view('member.notifications.index', compact('member', 'notifications'));
    }

    public function read(Request $request, MemberNotification $notification): RedirectResponse
    {
        $member = $this->memberAccounts->memberFor($request->user());
        abort_unless($notification->member_id === $member->id, 404);

        if (! $notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $member = $this->memberAccounts->memberFor($request->user());

        MemberNotification::query()
            ->where('member_id', $member->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
