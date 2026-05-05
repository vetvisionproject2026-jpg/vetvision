<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Animal extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
        'species',
        'breed',
        'age',
        'gender',
        'weight',
        'image_path',
    ];

    /**
     * العلاقة مع المالك (User)
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * العلاقة مع التشخيصات
     */
    public function diagnoses()
    {
        return $this->hasMany(Diagnose::class);
    }
}