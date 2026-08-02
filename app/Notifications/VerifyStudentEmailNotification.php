<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyStudentEmailNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $verificationUrl,
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
            ->subject('Verifikasi email akun siswa')
            ->greeting('Halo '.$notifiable->full_name.',')
            ->line('Pendaftaran akun siswa pada '.$this->institutionName.' sudah diterima.')
            ->line('Tekan tombol berikut untuk mengaktifkan akun dan memastikan email ini benar-benar milik Anda.')
            ->action('Verifikasi Email', $this->verificationUrl)
            ->line('Tautan verifikasi berlaku selama 60 menit.')
            ->line('Abaikan email ini apabila Anda tidak melakukan pendaftaran.');
    }
}
