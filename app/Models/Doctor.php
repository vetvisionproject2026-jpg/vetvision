<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    protected $fillable = [
        'user_id',
        'specialization',
        'experience_years',
        'clinic_address',
        'bio',
        'contact_number',
        'google_id',
        'image',
        'latitude',
        'longitude',
        'consultation_fee',
        'average_rating',
        'license_image',
        'selfie_image',
        'is_verified'
    ];

    protected $casts = [
        'latitude'        => 'decimal:8',
        'longitude'       => 'decimal:8',
        'average_rating'  => 'float',
        'consultation_fee'=> 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function availabilities()
    {
        return $this->hasMany(DoctorAvailability::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    // ✅ بيتحدث تلقائياً لما اليوزر يعمل rating
    public function updateAverageRating(): void
    {
        $average = $this->appointments()
                        ->whereNotNull('rating')
                        ->avg('rating');

        $this->update([
            'average_rating' => round($average ?? 0, 2)
        ]);
    }

    // =====================================================================
    // 🌍 SCOPE: Haversine Formula - حساب المسافة بين نقطتين
    // =====================================================================
    /**
     * Scope للبحث عن أقرب الأطباء من موقع معين
     * 
     * الاستخدام:
     * Doctor::closest($lat, $lng)->paginate(10);
     * 
     * @param $query
     * @param float $lat - خط العرض
     * @param float $lng - خط الطول
     * @return mixed
     */
    public function scopeClosest($query, float $lat, float $lng)
    {
        return $query
            ->selectRaw("*, 
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) * 
                    cos(radians(longitude) - radians(?)) + 
                    sin(radians(?)) * sin(radians(latitude))
                )) AS distance
            ", [$lat, $lng, $lat])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('distance', 'asc');
    }

    // =====================================================================
    // 🌍 SCOPE: البحث عن أطباء ضمن نطاق معين من المسافة
    // =====================================================================
    /**
     * Scope للبحث عن أطباء ضمن نطاق معين (مثلاً: 5 كم)
     * 
     * الاستخدام:
     * Doctor::withinDistance($lat, $lng, 5)->paginate(10);
     * 
     * @param $query
     * @param float $lat - خط العرض
     * @param float $lng - خط الطول
     * @param float $maxDistance - أقصى مسافة بالكيلومتر
     * @return mixed
     */
    public function scopeWithinDistance($query, float $lat, float $lng, float $maxDistance = 10)
    {
        return $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereRaw("
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) * 
                    cos(radians(longitude) - radians(?)) + 
                    sin(radians(?)) * sin(radians(latitude))
                )) <= ?
            ", [$lat, $lng, $lat, $maxDistance]);
    }

    // =====================================================================
    // 🌍 SCOPE: البحث مع الترتيب حسب التقييم والمسافة
    // =====================================================================
    /**
     * Scope للبحث مع الترتيب حسب التقييم والمسافة معاً
     * 
     * @param $query
     * @param float $lat - خط العرض
     * @param float $lng - خط الطول
     * @return mixed
     */
    public function scopeRankedByRatingAndDistance($query, float $lat, float $lng)
    {
        return $query
            ->selectRaw("*, 
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) * 
                    cos(radians(longitude) - radians(?)) + 
                    sin(radians(?)) * sin(radians(latitude))
                )) AS distance
            ", [$lat, $lng, $lat])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('average_rating')
            ->orderBy('distance', 'asc');
    }
    public function reviews()
{
    return $this->hasMany(Review::class);
}
}