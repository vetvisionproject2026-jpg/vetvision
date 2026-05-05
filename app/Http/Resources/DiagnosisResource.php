<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiagnosisResource extends JsonResource
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
        'appointment_id' => $this->appointment_id,
        'diagnosis_detail' => $this->description,
        'treatment_plan' => $this->treatment,
        'doctor_notes' => $this->notes,
        'image_url' => $this->image_path ? asset('storage/' . $this->image_path) : null, // 👈 رابط الصورة
        'date' => $this->created_at->format('d M Y'),
    ];
}
}
