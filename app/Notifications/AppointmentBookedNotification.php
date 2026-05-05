<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Appointment;

class AppointmentBookedNotification extends Notification
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
            ->subject('موعد جديد — VetVision')
            ->greeting('مرحباً د. ' . $notifiable->name)
            ->line('تم حجز موعد جديد معك!')
            ->line('التاريخ: ' . $this->appointment->date_time)
            ->line('المكان: ' . $this->appointment->location)
            ->action('عرض الموعد', url('/api/doctor/appointments'))
            ->line('شكراً لاستخدامك VetVision!');
    }

    public function toDatabase($notifiable)
    {
        return [
            'title'          => 'موعد جديد',
            'body'           => 'تم حجز موعد جديد بتاريخ ' . $this->appointment->date_time,
            'appointment_id' => $this->appointment->id,
            'type'           => 'appointment_booked',
        ];
    }
}