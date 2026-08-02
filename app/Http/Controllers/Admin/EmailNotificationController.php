<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendTestEmailRequest;
use App\Models\EmailDeliveryLog;
use App\Services\StudentEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailNotificationController extends Controller
{
    public function __construct(
        private readonly StudentEmailService $emails,
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = trim((string) $request->query('status'));
        $type = trim((string) $request->query('type'));

        $logs = EmailDeliveryLog::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('recipient_email', 'like', '%'.$search.'%')
                        ->orWhere('subject', 'like', '%'.$search.'%');
                });
            })
            ->when(in_array($status, ['sent', 'failed'], true), fn ($query) => $query->where('delivery_status', $status))
            ->when($type !== '', fn ($query) => $query->where('mail_type', $type))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        $statistics = [
            'total' => EmailDeliveryLog::query()->count(),
            'sent' => EmailDeliveryLog::query()->where('delivery_status', 'sent')->count(),
            'failed' => EmailDeliveryLog::query()->where('delivery_status', 'failed')->count(),
            'today' => EmailDeliveryLog::query()->whereDate('created_at', today())->count(),
        ];

        $mailTypes = EmailDeliveryLog::query()
            ->select('mail_type')
            ->distinct()
            ->orderBy('mail_type')
            ->pluck('mail_type');

        $configuration = [
            'mailer' => (string) config('mail.default'),
            'host' => (string) config('mail.mailers.smtp.host'),
            'port' => (string) config('mail.mailers.smtp.port'),
            'from_address' => (string) config('mail.from.address'),
            'from_name' => (string) config('mail.from.name'),
        ];

        return view('admin.email-notifications.index', compact(
            'logs',
            'statistics',
            'mailTypes',
            'configuration',
            'search',
            'status',
            'type',
        ));
    }

    public function sendTest(SendTestEmailRequest $request): RedirectResponse
    {
        $sent = $this->emails->sendTest(
            recipientEmail: $request->validated()['recipient_email'],
            sender: $request->user(),
        );

        return back()->with(
            $sent ? 'success' : 'error',
            $sent
                ? 'Email pengujian berhasil diproses. Periksa kotak masuk penerima.'
                : 'Email pengujian gagal dikirim. Periksa konfigurasi SMTP dan riwayat pengiriman.'
        );
    }
}
