<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentLoanStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $subjectText,
        private readonly string $messageText,
        private readonly string $detailUrl,
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
            ->action('Lihat Detail Pengajuan', $this->detailUrl)
            ->line('Informasi yang sama juga tersedia pada dashboard siswa.');
    }
}
