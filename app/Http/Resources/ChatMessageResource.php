<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
{
    return [
        'id' => $this->id,
        'sender_id' => $this->sender_id,
        'message' => $this->message_content,
        'sent_at' => $this->created_at->diffForHumans(), // هيظهر "منذ 5 دقائق" مثلاً
        'is_read' => $this->is_read,
    ];
}
}
