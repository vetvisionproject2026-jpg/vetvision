<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnimalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'species' => $this->species,
            'breed' => $this->breed,
            'age' => $this->age,
            'gender' => $this->gender,
            'weight' => $this->weight,
'image_url' => $this->image_path 
    ? asset('storage/' . $this->image_path) 
    : null,        ];
    }
}