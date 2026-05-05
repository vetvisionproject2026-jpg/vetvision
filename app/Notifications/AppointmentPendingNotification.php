<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Appointment;

class AppointmentPendingNotification extends Notification
{
    protected $appointment;

    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
    }

    public function via($notifiable)
    {
        // هنبعته داتابيز وإيميل برضه عشان اليوزر يجيله تنبيه على موبايله
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('تم استلام طلب حجزك — VetVision')
            ->greeting('مرحباً ' . $notifiable->name)
            ->line('لقد تم تسجيل طلب حجز موعدك بنجاح وهو الآن قيد المراجعة من قبل الدكتور.')
            ->line('التاريخ: ' . $this->appointment->date_time)
            ->line('نوع الزيارة: ' . ($this->appointment->type == 'clinic' ? 'في العيادة' : 'زيارة منزلية'))
            ->action('متابعة حالة الموعد', url('/api/appointments/' . $this->appointment->id))
            ->line('سنقوم بإبلاغك فور قيام الدكتور بتأكيد الموعد أو تعديله.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'طلب حجز قيد الانتظار',
            'body' => 'تم استلام طلب حجزك بتاريخ ' . $this->appointment->date_time . ' وفي انتظار رد الدكتور.',
            'appointment_id' => $this->appointment->id,
            'type' => 'appointment_pending',
        ];
    }
}