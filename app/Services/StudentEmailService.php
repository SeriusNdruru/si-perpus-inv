<?php

namespace App\Services;

use App\Models\EmailDeliveryLog;
use App\Models\LoanRequest;
use App\Models\Member;
use App\Models\User;
use App\Notifications\StudentDueReminderNotification;
use App\Notifications\StudentLoanStatusNotification;
use App\Notifications\SystemTestEmailNotification;
use App\Notifications\PasswordResetLinkNotification;
use App\Notifications\VerifyStudentEmailNotification;
use Illuminate\Notifications\Notification as NotificationContract;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Throwable;

class StudentEmailService
{
    public function sendVerification(User $user): bool
    {
        if ($user->email === null || trim($user->email) === '') {
            return false;
        }

        $verificationUrl = URL::temporarySignedRoute(
            'student.verification.verify',
            now()->addMinutes(60),
            [
                'user' => $user->id,
                'hash' => sha1(strtolower($user->email)),
            ]
        );

        $institutionName = $this->institutionName();

        return $this->deliverToUser(
            user: $user,
            notification: new VerifyStudentEmailNotification(
                $verificationUrl,
                $institutionName,
            ),
            mailType: 'email_verification',
            subject: 'Verifikasi email akun siswa',
            referenceType: 'user',
            referenceId: $user->id,
        );
    }

    public function sendLoanRequestStatus(
        int $loanRequestId,
        string $status,
        string $title,
        string $message,
    ): bool {
        $loanRequest = LoanRequest::query()
            ->with('member.user')
            ->find($loanRequestId);

        $user = $loanRequest?->member?->user;

        if (! $this->canReceiveStudentEmail($user)) {
            return false;
        }

        return $this->deliverToUser(
            user: $user,
            notification: new StudentLoanStatusNotification(
                subjectText: $title,
                messageText: $message,
                detailUrl: route('member.loan-requests.show', $loanRequest),
            ),
            mailType: 'loan_request_'.$status,
            subject: $title,
            memberId: $loanRequest->member_id,
            referenceType: 'loan_request',
            referenceId: $loanRequest->id,
        );
    }

    public function sendDueReminder(
        int $memberId,
        int $loanItemId,
        string $mailType,
        string $subject,
        string $message,
    ): bool {
        $member = Member::query()->with('user')->find($memberId);
        $user = $member?->user;

        if (! $this->canReceiveStudentEmail($user)) {
            return false;
        }

        return $this->deliverToUser(
            user: $user,
            notification: new StudentDueReminderNotification(
                subjectText: $subject,
                messageText: $message,
                historyUrl: route('member.history.loans'),
            ),
            mailType: $mailType,
            subject: $subject,
            memberId: $memberId,
            referenceType: 'loan_item',
            referenceId: $loanItemId,
        );
    }

    public function sendPasswordReset(
        User $user,
        string $resetUrl,
        string $accountLabel,
        string $mailType,
    ): bool {
        if ($user->email === null || trim($user->email) === '') {
            return false;
        }

        return $this->deliverToUser(
            user: $user,
            notification: new PasswordResetLinkNotification(
                resetUrl: $resetUrl,
                accountLabel: $accountLabel,
                institutionName: $this->institutionName(),
            ),
            mailType: $mailType,
            subject: 'Atur ulang password '.$accountLabel,
            referenceType: 'user',
            referenceId: $user->id,
        );
    }

    public function sendTest(string $recipientEmail, User $sender): bool
    {
        return $this->deliverToAddress(
            recipientEmail: $recipientEmail,
            notification: new SystemTestEmailNotification(
                institutionName: $this->institutionName(),
                sentBy: $sender->full_name,
            ),
            mailType: 'system_test',
            subject: 'Pengujian email sistem berhasil',
            userId: $sender->id,
            referenceType: 'user',
            referenceId: $sender->id,
        );
    }

    private function canReceiveStudentEmail(?User $user): bool
    {
        return $user !== null
            && $user->email !== null
            && trim($user->email) !== ''
            && $user->hasVerifiedEmail();
    }

    private function deliverToUser(
        User $user,
        NotificationContract $notification,
        string $mailType,
        string $subject,
        ?int $memberId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): bool {
        $recipientEmail = (string) $user->email;

        try {
            $user->notify($notification);

            $this->writeLog(
                userId: $user->id,
                memberId: $memberId ?? $user->member?->id,
                recipientEmail: $recipientEmail,
                mailType: $mailType,
                subject: $subject,
                deliveryStatus: 'sent',
                referenceType: $referenceType,
                referenceId: $referenceId,
                errorMessage: null,
            );

            return true;
        } catch (Throwable $exception) {
            report($exception);

            $this->writeLog(
                userId: $user->id,
                memberId: $memberId ?? $user->member?->id,
                recipientEmail: $recipientEmail,
                mailType: $mailType,
                subject: $subject,
                deliveryStatus: 'failed',
                referenceType: $referenceType,
                referenceId: $referenceId,
                errorMessage: $exception->getMessage(),
            );

            return false;
        }
    }

    private function deliverToAddress(
        string $recipientEmail,
        NotificationContract $notification,
        string $mailType,
        string $subject,
        ?int $userId = null,
        ?int $memberId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): bool {
        try {
            Notification::route('mail', $recipientEmail)->notify($notification);

            $this->writeLog(
                userId: $userId,
                memberId: $memberId,
                recipientEmail: $recipientEmail,
                mailType: $mailType,
                subject: $subject,
                deliveryStatus: 'sent',
                referenceType: $referenceType,
                referenceId: $referenceId,
                errorMessage: null,
            );

            return true;
        } catch (Throwable $exception) {
            report($exception);

            $this->writeLog(
                userId: $userId,
                memberId: $memberId,
                recipientEmail: $recipientEmail,
                mailType: $mailType,
                subject: $subject,
                deliveryStatus: 'failed',
                referenceType: $referenceType,
                referenceId: $referenceId,
                errorMessage: $exception->getMessage(),
            );

            return false;
        }
    }

    private function writeLog(
        ?int $userId,
        ?int $memberId,
        string $recipientEmail,
        string $mailType,
        string $subject,
        string $deliveryStatus,
        ?string $referenceType,
        ?int $referenceId,
        ?string $errorMessage,
    ): void {
        try {
            EmailDeliveryLog::query()->create([
                'user_id' => $userId,
                'member_id' => $memberId,
                'recipient_email' => $recipientEmail,
                'mail_type' => $mailType,
                'subject' => $subject,
                'delivery_status' => $deliveryStatus,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'error_message' => $errorMessage !== null
                    ? mb_substr($errorMessage, 0, 2000)
                    : null,
                'sent_at' => $deliveryStatus === 'sent' ? now() : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function institutionName(): string
    {
        try {
            return (string) (\Illuminate\Support\Facades\DB::table('system_settings')
                ->where('setting_key', 'institution.name')
                ->value('setting_value') ?: config('app.name'));
        } catch (Throwable) {
            return (string) config('app.name');
        }
    }
}
