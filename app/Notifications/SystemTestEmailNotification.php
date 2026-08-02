<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemTestEmailNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $institutionName,
        private readonly string $sentBy,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengujian email sistem berhasil')
            ->greeting('Pengujian email')
            ->line('Email ini dikirim dari sistem '.$this->institutionName.'.')
            ->line('Pengujian dilakukan oleh '.$this->sentBy.' pada '.now()->format('d/m/Y H:i:s').'.')
            ->line('Apabila email ini diterima, konfigurasi pengiriman email sudah dapat digunakan.');
    }
}
