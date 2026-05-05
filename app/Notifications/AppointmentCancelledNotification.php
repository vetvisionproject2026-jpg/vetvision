<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Appointment;

class AppointmentCancelledNotification extends Notification
{
    

    protected $appointment;

    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('تم إلغاء الموعد — VetVision')
            ->greeting('مرحباً ' . $notifiable->name)
            ->line('للأسف تم إلغاء موعدك!')
            ->line('التاريخ: ' . $this->appointment->date_time)
            ->line('السبب: ' . ($this->appointment->reason ?? 'لم يتم تحديد سبب'))
            ->action('حجز موعد جديد', url('/api/appointments'))
            ->line('شكراً لاستخدامك VetVision!');
    }

    public function toDatabase($notifiable)
    {
        return [
            'title'          => 'تم إلغاء موعدك ❌',
            'body'           => 'تم إلغاء موعدك بتاريخ ' . $this->appointment->date_time,
            'appointment_id' => $this->appointment->id,
            'type'           => 'appointment_cancelled',
        ];
    }
}