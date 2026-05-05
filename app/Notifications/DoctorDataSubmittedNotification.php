<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DoctorDataSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct()
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('تم استلام بيانات التوثيق - VetVision')
            ->greeting('أهلاً دكتور ' . $notifiable->name)
            ->line('نشكرك على رفع بيانات التوثيق الخاصة بك.')
            ->line('ملفاتك الآن قيد المراجعة من قبل فريق الإدارة، وسنقوم بالرد عليك في أقرب وقت.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}