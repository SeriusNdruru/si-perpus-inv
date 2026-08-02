<?php

namespace App\Services\Library;

use App\Models\Member;
use Illuminate\Support\Facades\DB;

class DueReminderService
{
    public function __construct(
        private readonly \App\Services\StudentEmailService $emails,
    ) {
    }

    public function generateAll(): array
    {
        return [
            'due_tomorrow' => $this->generateDueTomorrow(),
            'overdue' => $this->generateOverdue(),
        ];
    }

    public function generateForMember(Member $member): array
    {
        return [
            'due_tomorrow' => $this->generateDueTomorrow($member->id),
            'overdue' => $this->generateOverdue($member->id),
        ];
    }

    private function generateDueTomorrow(?int $memberId = null): int
    {
        $rows = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->join('members', 'members.id', '=', 'loans.member_id')
            ->join('assets', 'assets.id', '=', 'loan_items.asset_id')
            ->join('items', 'items.id', '=', 'assets.item_id')
            ->where('loan_items.return_status', 'borrowed')
            ->whereDate('loan_items.due_date', today()->addDay())
            ->when($memberId !== null, fn ($query) => $query->where('members.id', $memberId))
            ->get([
                'members.id as member_id',
                'loan_items.id as loan_item_id',
                'loan_items.due_date',
                'items.item_name',
                'loans.loan_code',
            ]);

        $inserted = 0;

        foreach ($rows as $row) {
            $title = 'Pengembalian buku besok';
            $message = "Buku {$row->item_name} pada transaksi {$row->loan_code} jatuh tempo besok. Segera kembalikan atau hubungi petugas perpustakaan.";
            $created = DB::table('member_notifications')->insertOrIgnore([
                'member_id' => $row->member_id,
                'loan_item_id' => $row->loan_item_id,
                'notification_key' => 'due-tomorrow:'.$row->loan_item_id.':'.$row->due_date,
                'notification_type' => 'due_tomorrow',
                'title' => $title,
                'message' => $message,
                'is_read' => 0,
                'read_at' => null,
                'created_at' => now(),
            ]);

            $inserted += $created;

            if ($created === 1) {
                $this->emails->sendDueReminder(
                    memberId: $row->member_id,
                    loanItemId: $row->loan_item_id,
                    mailType: 'due_tomorrow',
                    subject: $title,
                    message: $message,
                );
            }
        }

        return $inserted;
    }

    private function generateOverdue(?int $memberId = null): int
    {
        $rows = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->join('members', 'members.id', '=', 'loans.member_id')
            ->join('assets', 'assets.id', '=', 'loan_items.asset_id')
            ->join('items', 'items.id', '=', 'assets.item_id')
            ->where('loan_items.return_status', 'borrowed')
            ->whereDate('loan_items.due_date', '<', today())
            ->when($memberId !== null, fn ($query) => $query->where('members.id', $memberId))
            ->get([
                'members.id as member_id',
                'loan_items.id as loan_item_id',
                'loan_items.due_date',
                'items.item_name',
                'loans.loan_code',
            ]);

        $inserted = 0;

        foreach ($rows as $row) {
            $title = 'Buku melewati jatuh tempo';
            $message = "Buku {$row->item_name} pada transaksi {$row->loan_code} telah melewati tanggal pengembalian {$row->due_date}.";
            $created = DB::table('member_notifications')->insertOrIgnore([
                'member_id' => $row->member_id,
                'loan_item_id' => $row->loan_item_id,
                'notification_key' => 'overdue:'.$row->loan_item_id,
                'notification_type' => 'overdue',
                'title' => $title,
                'message' => $message,
                'is_read' => 0,
                'read_at' => null,
                'created_at' => now(),
            ]);

            $inserted += $created;

            if ($created === 1) {
                $this->emails->sendDueReminder(
                    memberId: $row->member_id,
                    loanItemId: $row->loan_item_id,
                    mailType: 'overdue',
                    subject: $title,
                    message: $message,
                );
            }
        }

        return $inserted;
    }
}
