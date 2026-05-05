<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $appointment;
    protected $recipientType; // 'user' أو 'doctor'

    /**
     * Create a new notification instance.
     */
    public function __construct(Appointment $appointment, string $recipientType = 'user')
    {
        $this->appointment = $appointment;
        $this->recipientType = $recipientType;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // إرسال عبر Email و Database معاً
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment->load('doctor.user', 'animal');
        $appointmentTime = $appointment->date_time;

        if ($this->recipientType === 'user') {
            // تذكير للمستخدم (صاحب الحيوان)
            return (new MailMessage)
                ->subject('🔔 تذكير: موعدك الطبي البيطري غداً')
                ->greeting('مرحباً ' . $notifiable->name . '!')
                ->line('هذا تذكير لموعدك الطبي البيطري غداً.')
                ->line('')
                ->line('📋 **تفاصيل الموعد:**')
                ->line('🏥 الطبيب: ' . $appointment->doctor->user->name)
                ->line('🎓 التخصص: ' . $appointment->doctor->specialization)
                ->line('🐾 الحيوان: ' . $appointment->animal->name)
                ->line('📅 التاريخ والوقت: ' . $appointmentTime->format('Y-m-d H:i'))
                ->line('📍 نوع الموعد: ' . $this->getAppointmentType($appointment->type))
                ->line('📍 الموقع: ' . ($appointment->location ?? 'العيادة'))
                ->line('')
                ->action('عرض تفاصيل الموعد', url('/appointments/' . $appointment->id))
                ->line('')
                ->line('⚠️ لو بتحتاج تعدل أو تلغي الموعد، اتصل بالدكتور قبل ساعة من الموعد.')
                ->line('')
                ->line('شكراً لاستخدامك VetVision! 🐾');

        } else {
            // تذكير للدكتور
            return (new MailMessage)
                ->subject('🔔 تذكير: موعد استشارة غداً')
                ->greeting('مرحباً ' . $notifiable->name . '!')
                ->line('هذا تذكير لموعد الاستشارة غداً.')
                ->line('')
                ->line('📋 **تفاصيل الموعد:**')
                ->line('👤 المالك: ' . $appointment->user->name)
                ->line('📞 الهاتف: ' . ($appointment->user->phone ?? 'N/A'))
                ->line('🐾 الحيوان: ' . $appointment->animal->name . ' (' . $appointment->animal->species . ')')
                ->line('🎯 السلالة: ' . $appointment->animal->breed)
                ->line('⚕️ سبب الزيارة: ' . $appointment->reason)
                ->line('📅 التاريخ والوقت: ' . $appointmentTime->format('Y-m-d H:i'))
                ->line('📍 نوع الموعد: ' . $this->getAppointmentType($appointment->type))
                ->line('📍 الموقع: ' . ($appointment->location ?? 'العيادة'))
                ->line('📝 ملاحظات: ' . ($appointment->notes ?? 'لا توجد ملاحظات'))
                ->line('')
                ->action('عرض جميع المواعيد', url('/doctor/appointments'))
                ->line('')
                ->line('✅ تأكد من تجهيزك للموعد في الوقت المحدد.')
                ->line('')
                ->line('شكراً! 🏥');
        }
    }

    /**
     * Get the array representation of the notification.
     * (للـ In-app Notification في الـ Database)
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'doctor_name' => $this->appointment->doctor->user->name,
            'animal_name' => $this->appointment->animal->name,
            'date_time' => $this->appointment->date_time->format('Y-m-d H:i'),
            'type' => $this->getAppointmentType($this->appointment->type),
            'location' => $this->appointment->location ?? 'العيادة',
            'message' => $this->recipientType === 'user' 
                ? '🔔 تذكير: موعدك الطبي غداً'
                : '🔔 تذكير: موعد الاستشارة غداً',
            'recipient_type' => $this->recipientType,
        ];
    }

    /**
     * ترجمة نوع الموعد
     */
    private function getAppointmentType(string $type): string
    {
        return match($type) {
            'online' => '💻 استشارة أونلاين',
            'clinic' => '🏥 زيارة العيادة',
            'home_visit' => '🏠 زيارة منزلية',
            default => $type,
        };
    }
}