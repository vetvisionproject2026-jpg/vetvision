<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'avatar',
        'date_of_birth',
        'address',
        'status',
        'verification_code',
        'email_verified_at',
        'provider',      // ✅ جديد: اسم الـ Provider (google / facebook)
        'provider_id',   // ✅ جديد: الـ ID الخاص بالمستخدم عند Google/Facebook
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function doctor()
    {
        return $this->hasOne(Doctor::class, 'user_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    protected static function booted()
    {
        static::created(function ($user) {
            if ($user->role === 'doctor') {
                \App\Models\Doctor::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'specialization'  => 'General',
                        'experience_years' => 0,
                        'bio'             => 'يرجى تحديث البيانات الطبية',
                    ]
                );
            }
        });
    }
}