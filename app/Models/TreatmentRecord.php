<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TreatmentRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'animal_id',
        'doctor_id',
        'treatment_description',
        'outcome',
        'treatment_date',
        'notes',
    ];

    protected $casts = [
        'treatment_date' => 'datetime',
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