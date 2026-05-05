<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
   public function toArray(Request $request): array
    {
        return [
            'session_id' => $this->id,
            'status'     => $this->status, // مفتوحة (active) أو مغلقة (closed)
            
            'client' => new UserResource($this->whenLoaded('user')), 
            'doctor' => new DoctorResource($this->whenLoaded('doctor')),
            
            'last_message' => new ChatMessageResource($this->whenLoaded('latestMessage')),
            
            'unread_count' => $this->unread_messages_count ?? 0,
            
            'created_at' => $this->created_at->format('Y-m-d H:i'),
            'updated_at' => $this->updated_at->diffForHumans(),
        ];
    }
}

