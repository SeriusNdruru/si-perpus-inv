<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentDueReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $subjectText,
        private readonly string $messageText,
        private readonly string $historyUrl,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subjectText)
            ->greeting('Halo '.$notifiable->full_name.',')
            ->line($this->messageText)
            ->action('Lihat Peminjaman', $this->historyUrl)
            ->line('Segera hubungi petugas perpustakaan apabila ada kendala pengembalian.');
    }
}
