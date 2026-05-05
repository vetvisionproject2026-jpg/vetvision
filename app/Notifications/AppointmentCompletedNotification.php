<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Appointment;

class AppointmentCompletedNotification extends Notification
{
    use Queueable;

    protected $appointment;

    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
    }

    public function via($notifiable)
    {
        // نحدد هنا قنوات الإرسال (إيميل وقاعدة بيانات)
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('تم إتمام الكشف بنجاح')
            ->greeting('مرحباً ' . $notifiable->name)
            ->line('نود إعلامك بأن موعدك مع الدكتور ' . $this->appointment->doctor->user->name . ' قد تم بنجاح.')
            ->line('تاريخ الموعد: ' . $this->appointment->date_time)
            ->action('تقييم الخدمة', url('/appointments/' . $this->appointment->id))
            ->line('شكراً لاستخدامك تطبيق VetVision!');
    }

    public function toArray($notifiable)
    {
        return [
            'appointment_id' => $this->appointment->id,
            'status' => 'completed',
            'message' => 'تم إتمام موعدك بنجاح',
        ];
    }
}