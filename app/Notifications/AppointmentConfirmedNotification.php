<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Appointment;

class AppointmentConfirmedNotification extends Notification
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
            ->subject('تم تأكيد موعدك — VetVision')
            ->greeting('مرحباً ' . $notifiable->name)
            ->line('تم تأكيد موعدك بنجاح!')
            ->line('التاريخ: ' . $this->appointment->date_time)
            ->line('المكان: ' . $this->appointment->location)
            ->action('عرض الموعد', url('/api/my-appointments'))
            ->line('شكراً لاستخدامك VetVision!');
    }

    public function toDatabase($notifiable)
    {
        return [
            'title'          => 'تم تأكيد موعدك ✅',
            'body'           => 'تم تأكيد موعدك بتاريخ ' . $this->appointment->date_time,
            'appointment_id' => $this->appointment->id,
            'type'           => 'appointment_confirmed',
        ];
    }
}