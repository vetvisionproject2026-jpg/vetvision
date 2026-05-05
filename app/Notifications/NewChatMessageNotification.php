<?php

namespace App\Notifications;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewChatMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $session;
    protected $message;

    public function __construct(ChatSession $session, ChatMessage $message)
    {
        $this->session = $session;
        $this->message = $message;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('رسالة جديدة — VetVision')
            ->greeting('مرحباً ' . $notifiable->name . '!')
            ->line('لديك رسالة جديدة في المحادثة.')
            ->line('الرسالة: ' . \Str::limit($this->message->message, 100))
            ->action('عرض المحادثة', url('/chat/' . $this->session->id))
            ->line('شكراً لاستخدامك VetVision!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'new_chat_message',
            'title'      => 'رسالة جديدة',
            'body'       => \Str::limit($this->message->message, 100),
            'session_id' => $this->session->id,
            'sender'     => $this->message->sender,
        ];
    }
}