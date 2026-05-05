<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
    return [
        'id' => $this->id,
        'name' => $this->name,
        'email' => $this->email,
        'phone' => $this->phone,
        'address' => $this->address,
        'role' => $this->role,
'avatar_url' => $this->avatar 
    ? (str_starts_with($this->avatar, 'http') ? $this->avatar : asset('storage/' . $this->avatar))
    : null,    ];
}
}
