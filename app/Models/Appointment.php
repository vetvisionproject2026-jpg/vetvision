<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 
        'doctor_id', 
        'animal_id', 
        'date_time', 
        'status', 
        'reason', 
        'location', 
        'duration', 
        'notes',
        'rating', 
        'review',
        'type',
        'latitude',
        'longitude',
        'consultation_fee',
        'reminder_sent',      // ضيف دي
    'reminder_sent_at',
    ];
 

    protected $casts = [
        'date_time' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ===================== Relationships =====================

    public function user() 
    { 
        return $this->belongsTo(User::class, 'user_id'); 
    }

    public function doctor() 
    { 
        return $this->belongsTo(Doctor::class, 'doctor_id'); 
    }

    public function animal() 
    { 
        return $this->belongsTo(Animal::class, 'animal_id'); 
    }

    // ===================== Scopes =====================

    /**
     * احصل على المواعيد القادمة
     */
    public function scopeUpcoming($query)
    {
        return $query->where('date_time', '>', Carbon::now())
                    ->where('status', '!=', 'cancelled')
                    ->orderBy('date_time', 'asc');
    }

    /**
     * احصل على المواعيد المكتملة
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed')
                    ->orderBy('date_time', 'desc');
    }

    /**
     * احصل على المواعيد الملغاة
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * احصل على المواعيد المعلقة (غير المؤكدة)
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * احصل على المواعيد المؤكدة
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    // ===================== Accessors / Mutators =====================

    /**
     * تحقق إذا كان الموعد قريب (خلال 24 ساعة)
     */
    public function getIsUpcomingAttribute()
    {
        $now = Carbon::now();
        $appointmentTime = $this->date_time;
        
        return $appointmentTime->isAfter($now) && 
               $appointmentTime->diffInHours($now) <= 24;
    }

    /**
     * احسب الوقت المتبقي قبل الموعد
     */
    public function getTimeRemainingAttribute()
    {
        $now = Carbon::now();
        
        if ($this->date_time->isPast()) {
            return 'انتهى الموعد';
        }

        return $this->date_time->diffForHumans($now);
    }

    /**
     * تحقق إذا كان بإمكان المستخدم إلغاء الموعد
     */
    public function getCanBeCancelledAttribute()
    {
        // لا يمكن إلغاء الموعد إذا مرت ساعة من الآن
        return $this->date_time->diffInHours(Carbon::now()) > 1 
               && $this->status !== 'cancelled' 
               && $this->status !== 'completed';
    }

    /**
     * تحقق إذا كان بإمكان المستخدم إعادة جدولة الموعد
     */
    public function getCanBeRescheduledAttribute()
    {
        return $this->can_be_cancelled;
    }

    /**
     * تحقق إذا كان الموعد يحتاج rating
     */
    public function getNeedsRatingAttribute()
    {
        return $this->status === 'completed' && is_null($this->rating);
    }

    // ===================== Methods =====================

    /**
     * احسب المسافة بين موقع العميل والعيادة (بـ Kilometers)
     * يستخدم Haversine Formula
     */
    public function calculateDistance($userLat, $userLng)
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }

        $earthRadius = 6371; // Kilometers

        $latDiff = deg2rad($this->latitude - $userLat);
        $lngDiff = deg2rad($this->longitude - $userLng);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad($userLat)) * cos(deg2rad($this->latitude)) *
             sin($lngDiff / 2) * sin($lngDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return round($distance, 2);
    }

    /**
     * تحقق إذا كان هناك تعارض مع مواعيد أخرى
     */
    public static function hasConflict($doctorId, $userId, $dateTime, $excludeAppointmentId = null)
    {
        $query = static::where(function ($q) use ($doctorId, $userId) {
            $q->where('doctor_id', $doctorId)
              ->orWhere('user_id', $userId);
        })
        ->where('date_time', $dateTime)
        ->where('status', '!=', 'cancelled');

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query->exists();
    }

    /**
     * احصل على المواعيد للدكتور في يوم معين
     */
    public static function getDoctorAppointmentsOnDay($doctorId, $date)
    {
        return static::where('doctor_id', $doctorId)
                    ->whereDate('date_time', $date)
                    ->where('status', '!=', 'cancelled')
                    ->orderBy('date_time')
                    ->get();
    }

    /**
     * 🔍 التحقق من صحة بيانات الموعد الجديد
     */
    public static function validateAppointmentData($data)
    {
        $errors = [];

        // تحقق من الدكتور
        if (empty($data['doctor_id'])) {
            $errors[] = 'يجب اختيار الدكتور';
        }

        // تحقق من الحيوان
        if (empty($data['animal_id'])) {
            $errors[] = 'يجب اختيار الحيوان';
        }

        // تحقق من التاريخ والوقت
        if (empty($data['date_time'])) {
            $errors[] = 'يجب تحديد التاريخ والوقت';
        } else {
            $appointmentTime = Carbon::parse($data['date_time']);
            if ($appointmentTime->isPast()) {
                $errors[] = 'يجب اختيار تاريخ ووقت في المستقبل';
            }
            if ($appointmentTime->lt(Carbon::now()->addHours(48))) {
                $errors[] = 'يجب حجز الموعد قبل 48 ساعة على الأقل';
            }
        }

        // تحقق من النوع
        if (!empty($data['type']) && !in_array($data['type'], ['online', 'clinic', 'home_visit'])) {
            $errors[] = 'نوع الموعد غير صحيح';
        }

        // تحقق من الموقع للزيارة المنزلية
        if (!empty($data['type']) && $data['type'] === 'home_visit' && empty($data['location'])) {
            $errors[] = 'يجب تحديد الموقع للزيارة المنزلية';
        }

        return $errors;
    }
    public function reviews()
{
    return $this->hasMany(Review::class);
}
    
}