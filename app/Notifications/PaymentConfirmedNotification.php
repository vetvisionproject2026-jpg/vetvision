<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تم تأكيد الدفع — VetVision')
            ->greeting('مرحباً ' . $notifiable->name . '!')
            ->line('تم استلام دفعتك بنجاح.')
            ->line('المبلغ: ' . $this->payment->amount . ' ' . $this->payment->currency)
            ->line('رقم المعاملة: ' . $this->payment->transaction_id)
            ->line('تاريخ الدفع: ' . $this->payment->updated_at->format('Y-m-d H:i'))
            ->action('عرض تفاصيل الموعد', url('/appointments/' . $this->payment->appointment_id))
            ->line('شكراً لاستخدامك VetVision! 🐾');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'payment_confirmed',
            'title'          => 'تم تأكيد الدفع ✅',
            'body'           => 'تم استلام ' . $this->payment->amount . ' ' . $this->payment->currency . ' بنجاح',
            'payment_id'     => $this->payment->id,
            'appointment_id' => $this->payment->appointment_id,
            'amount'         => $this->payment->amount,
            'currency'       => $this->payment->currency,
            'transaction_id' => $this->payment->transaction_id,
        ];
    }
}