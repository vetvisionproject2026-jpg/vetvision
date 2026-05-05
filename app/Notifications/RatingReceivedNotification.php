<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RatingReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $appointment;

    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $stars = str_repeat('⭐', $this->appointment->rating);

        return (new MailMessage)
            ->subject('تقييم جديد — VetVision')
            ->greeting('مرحباً د. ' . $notifiable->name . '!')
            ->line('حصلت على تقييم جديد من أحد عملائك.')
            ->line('التقييم: ' . $stars . ' (' . $this->appointment->rating . '/5)')
            ->line('التعليق: ' . ($this->appointment->review ?? 'لا يوجد تعليق'))
            ->line('الموعد بتاريخ: ' . $this->appointment->date_time->format('Y-m-d H:i'))
            ->action('عرض تقييماتك', url('/doctor/appointments'))
            ->line('شكراً لتميزك في تقديم الخدمة! 🏥');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'rating_received',
            'title'          => 'تقييم جديد ⭐',
            'body'           => 'حصلت على تقييم ' . $this->appointment->rating . '/5',
            'appointment_id' => $this->appointment->id,
            'rating'         => $this->appointment->rating,
            'review'         => $this->appointment->review,
        ];
    }
}