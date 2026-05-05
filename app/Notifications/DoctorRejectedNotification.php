<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DoctorRejectedNotification extends Notification
{
    use Queueable;

    public $reason;

    public function __construct($reason)
    {
        $this->reason = $reason;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('تحديث بخصوص طلب توثيق حسابك - VetVision')
            ->greeting('أهلاً دكتور ' . $notifiable->name)
            ->line('للأسف، نود إبلاغك بأنه تم رفض طلب توثيق حسابك حالياً.')
            ->line('سبب الرفض: ' . $this->reason)
            ->action('إعادة رفع البيانات', url('/doctor/verify'))
            ->line('يرجى التأكد من وضوح الصور ومطابقة البيانات والمحاولة مرة أخرى.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}