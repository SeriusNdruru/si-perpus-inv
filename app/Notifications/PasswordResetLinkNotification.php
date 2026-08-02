<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetLinkNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $resetUrl,
        private readonly string $accountLabel,
        private readonly string $institutionName,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Atur ulang password '.$this->accountLabel)
            ->greeting('Halo '.$notifiable->full_name.',')
            ->line('Kami menerima permintaan untuk mengatur ulang password '.$this->accountLabel.' pada '.$this->institutionName.'.')
            ->action('Atur Ulang Password', $this->resetUrl)
            ->line('Tautan ini berlaku selama 60 menit dan hanya dapat digunakan untuk akun ini.')
            ->line('Abaikan email ini apabila Anda tidak meminta perubahan password.')
            ->line('Demi keamanan, jangan membagikan tautan ini kepada siapa pun.');
    }
}
