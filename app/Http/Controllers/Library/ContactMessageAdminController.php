<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\UpdateContactMessageRequest;
use App\Models\PublicContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactMessageAdminController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status');

        $messages = PublicContactMessage::query()
            ->with('handler:id,full_name')
            ->when(
                in_array($status, ['unread', 'read', 'replied', 'closed'], true),
                fn ($query) => $query->where('status', $status)
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('library.contact-messages.index', compact('messages'));
    }

    public function show(PublicContactMessage $contactMessage): View
    {
        if ($contactMessage->status === 'unread') {
            $contactMessage->update([
                'status' => 'read',
                'handled_by' => auth()->id(),
                'handled_at' => now(),
            ]);
        }

        return view('library.contact-messages.show', compact('contactMessage'));
    }

    public function update(
        UpdateContactMessageRequest $request,
        PublicContactMessage $contactMessage
    ): RedirectResponse {
        $contactMessage->update([
            'status' => $request->validated()['status'],
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        return back()->with('success', 'Status pesan berhasil diperbarui.');
    }
}
