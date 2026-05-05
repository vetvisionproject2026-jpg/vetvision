<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Prescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'animal_id',
        'doctor_id',
        'medicine_name',
        'dosage',
        'frequency',
        'duration_days',
        'instructions',
        'prescribed_at',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'prescribed_at' => 'datetime',
        'completed_at'  => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}