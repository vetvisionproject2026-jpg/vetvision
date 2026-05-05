<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DoctorApprovedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
{
    return (new MailMessage)
                ->subject('تم توثيق حسابك في VetVision')
                ->greeting('أهلاً دكتور ' . $notifiable->name)
                ->line('يسعدنا إبلاغك بأن الإدارة قد راجعت بياناتك وتمت الموافقة على توثيق حسابك.')
                ->action('تسجيل الدخول للمنصة', url('/login'))
                ->line('الآن يمكنك استقبال الحجوزات والتواصل مع العملاء.');
}
    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
