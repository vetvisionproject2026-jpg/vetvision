<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Diagnosis extends Model
{
    public function animal()
{
    return $this->belongsTo(Animal::class);
}

public function user()
{
    return $this->belongsTo(User::class);
}
    use HasFactory;

    protected $fillable = [
        'animal_id',
        'result',
        'confidence',
        'recommendations',
        'image_path',
        'user_id'
    ];
}