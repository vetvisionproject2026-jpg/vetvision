<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'status'           => $this->status,
            'type'             => $this->type,
            'date_time'        => $this->date_time?->format('Y-m-d H:i'),
            'reason'           => $this->reason,
            'notes'            => $this->notes,
            'consultation_fee' => $this->consultation_fee,
            'rating'           => $this->rating,
            'review'           => $this->review,

            // Location (للـ home_visit)
            'location'         => $this->location,
            'latitude'         => $this->latitude,
            'longitude'        => $this->longitude,

            // Reminder info
            'reminder_sent'    => (bool) $this->reminder_sent,
            'reminder_sent_at' => $this->reminder_sent_at?->format('Y-m-d H:i'),

            // Helper attributes من الـ Model
            'time_remaining'   => $this->time_remaining,
            'can_be_cancelled' => $this->can_be_cancelled,
            'needs_rating'     => $this->needs_rating,

            // Relations
            'doctor' => $this->whenLoaded('doctor', fn() => [
                'id'             => $this->doctor->id,
                'name'           => $this->doctor->user?->name,
                'specialization' => $this->doctor->specialization,
                'phone'          => $this->doctor->user?->phone,
            ]),

            'client' => $this->whenLoaded('user', fn() => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'phone' => $this->user->phone,
            ]),

            'animal' => $this->whenLoaded('animal', fn() => [
                'id'      => $this->animal->id,
                'name'    => $this->animal->name,
                'species' => $this->animal->species,
                'breed'   => $this->animal->breed,
            ]),

            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}