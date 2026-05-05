<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Models\Doctor;
use App\Models\User;
use App\Notifications\NewChatMessageNotification;
use App\Traits\ApiResponseTrait;

class ChatController extends Controller
{
    use ApiResponseTrait;

    public function startSession(Request $request, $doctor_id)
    {
        $session = ChatSession::create([
            'user_id'   => auth()->id(),
            'doctor_id' => $doctor_id,
            'status'    => 'active',
        ]);

        return $this->apiResponse(true, 'تم بدء المحادثة بنجاح!', $session, 201);
    }

    public function sendMessage(Request $request, $sessionId)
    {
        $session = ChatSession::where('id', $sessionId)
                              ->where('user_id', auth()->id())
                              ->first();

        if (!$session) {
            return $this->apiResponse(false, 'المحادثة غير موجودة!', null, 404);
        }

        if ($session->status === 'closed') {
            return $this->apiResponse(false, 'هذه المحادثة مغلقة!', null, 403);
        }

        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        // حفظ رسالة المستخدم
        $userMessage = ChatMessage::create([
            'chat_session_id' => $sessionId,
            'sender'          => 'user',
            'message'         => $request->message,
        ]);

        // ✅ إرسال notification للدكتور
        $doctor = Doctor::with('user')->find($session->doctor_id);
        if ($doctor && $doctor->user) {
            $doctor->user->notify(new NewChatMessageNotification($session, $userMessage));
        }

        // رد الـ Bot
        $botReply = $this->getBotReply($request->message);

        $botMessage = ChatMessage::create([
            'chat_session_id' => $sessionId,
            'sender'          => 'bot',
            'message'         => $botReply,
        ]);

        return $this->apiResponse(true, 'تم إرسال الرسالة!', [
            'user_message' => $request->message,
            'bot_reply'    => $botReply,
        ]);
    }

    public function getMessages($sessionId)
    {
        $session = ChatSession::where('id', $sessionId)
                              ->where('user_id', auth()->id())
                              ->first();

        if (!$session) {
            return $this->apiResponse(false, 'المحادثة غير موجودة!', null, 404);
        }

        $messages = ChatMessage::where('chat_session_id', $sessionId)
                               ->orderBy('created_at', 'asc')
                               ->get();

        return $this->apiResponse(true, 'رسائل المحادثة', $messages);
    }

    public function getSessions()
    {
        $sessions = ChatSession::where('user_id', auth()->id())
                               ->latest()
                               ->get();

        return $this->apiResponse(true, 'محادثاتك', $sessions);
    }

    private function getBotReply($userMessage)
    {
        $replies = [
            'مرحبا'  => 'أهلاً! كيف يمكنني مساعدتك اليوم؟ 😊',
            'حيوان'  => 'يمكنك إضافة حيواناتك من قسم الحيوانات وتتبع صحتهم!',
            'تشخيص' => 'يمكنك رفع صورة حيوانك للحصول على تشخيص فوري بالذكاء الاصطناعي!',
            'موعد'   => 'يمكنك حجز موعد مع أقرب طبيب بيطري من قسم المواعيد!',
        ];

        foreach ($replies as $keyword => $reply) {
            if (str_contains($userMessage, $keyword)) {
                return $reply;
            }
        }

        return 'شكراً على تواصلك! سيتم الرد عليك قريباً من خلال الذكاء الاصطناعي. 🤖';
    }
}