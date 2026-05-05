<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Doctor;
use App\Models\Appointment;
use App\Models\Animal;
use App\Models\Diagnosis;
use App\Models\Payment;
use App\Traits\ApiResponseTrait;
use App\Http\Resources\DoctorResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class DoctorController extends Controller
{
    use ApiResponseTrait;

    // =====================================================================
    // 🔍 ADVANCED SEARCH & FILTERS
    // GET /api/doctors?name=&specialization=&min_rating=&experience_min=
    //                 &available=true&location_lat=&location_lng=&distance=
    //                 &price_min=&price_max=&sort_by=rating|experience|price|distance
    // =====================================================================
    public function index(Request $request)
    {
        $query = Doctor::with(['user', 'availabilities']);

        if ($request->filled('name')) {
            $query->whereHas('user', fn($q) =>
                $q->where('name', 'like', '%' . $request->name . '%')
            );
        }

        if ($request->filled('specialization')) {
            $query->where('specialization', 'like', '%' . $request->specialization . '%');
        }

        if ($request->filled('min_rating')) {
            $query->where('average_rating', '>=', (float) $request->min_rating);
        }

        if ($request->filled('experience_min')) {
            $query->where('experience_years', '>=', (int) $request->experience_min);
        }

        if ($request->boolean('available')) {
            $query->whereHas('availabilities');
        }

        if ($request->filled('price_min')) {
            $query->where('consultation_fee', '>=', (int) $request->price_min);
        }
        if ($request->filled('price_max')) {
            $query->where('consultation_fee', '<=', (int) $request->price_max);
        }

        $userLat = $request->filled('location_lat') ? (float) $request->location_lat : null;
        $userLng = $request->filled('location_lng') ? (float) $request->location_lng : null;
        $maxDist = $request->filled('distance')     ? (float) $request->distance     : null;
        $sortBy  = $request->input('sort_by', 'rating');

        if ($userLat && $userLng) {
            if ($maxDist) {
                $query->withinDistance($userLat, $userLng, $maxDist);
            }

            match($sortBy) {
                'distance'             => $query->closest($userLat, $userLng),
                'rating_and_distance'  => $query->rankedByRatingAndDistance($userLat, $userLng),
                'experience'           => $query->orderByDesc('experience_years'),
                'price'                => $query->orderBy('consultation_fee'),
                default                => $query->orderByDesc('average_rating'),
            };
        } else {
            match($sortBy) {
                'experience' => $query->orderByDesc('experience_years'),
                'price'      => $query->orderBy('consultation_fee'),
                default      => $query->orderByDesc('average_rating'),
            };
        }

        $doctors = $query->paginate(10);

        $result = $doctors->through(function ($doctor) use ($userLat, $userLng) {
            $arr = (new DoctorResource($doctor))->toArray(request());

            $arr['distance_km'] = ($userLat && $userLng && $doctor->latitude && $doctor->longitude)
                ? $this->haversine($userLat, $userLng, (float) $doctor->latitude, (float) $doctor->longitude)
                : null;

            return $arr;
        });

        return $this->apiResponse(true, 'تم جلب قائمة الأطباء', $result->toArray());
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $latDiff     = deg2rad($lat2 - $lat1);
        $lngDiff     = deg2rad($lng2 - $lng1);

        $a = sin($latDiff / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lngDiff / 2) ** 2;

        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    // =====================================================================
    // GET /api/doctors/nearest
    // =====================================================================
    public function nearestDoctors(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat'   => 'required|numeric|between:-90,90',
            'lng'   => 'numeric|between:-180,180',
            'limit' => 'integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'بيانات اللوكيشن غير صحيحة', $validator->errors(), 422);
        }

        $lat   = (float) $request->lat;
        $lng   = (float) $request->lng;
        $limit = $request->input('limit', 10);

        $doctors = Doctor::closest($lat, $lng)
            ->with(['user', 'availabilities'])
            ->limit($limit)
            ->get();

        $result = $doctors->map(function ($doctor) use ($lat, $lng) {
            return array_merge(
                (new DoctorResource($doctor))->toArray(request()),
                ['distance_km' => $this->haversine($lat, $lng, (float) $doctor->latitude, (float) $doctor->longitude)]
            );
        });

        return $this->apiResponse(true, 'أقرب الأطباء من موقعك', $result);
    }

    // =====================================================================
    // GET /api/doctors/nearby
    // =====================================================================
    public function nearbyDoctors(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat'      => 'required|numeric|between:-90,90',
            'lng'      => 'required|numeric|between:-180,180',
            'distance' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->apiResponse(false, 'بيانات البحث غير صحيحة', $validator->errors(), 422);
        }

        $lat      = (float) $request->lat;
        $lng      = (float) $request->lng;
        $distance = $request->input('distance', 10);

        $doctors = Doctor::withinDistance($lat, $lng, $distance)
            ->with(['user', 'availabilities'])
            ->orderByDesc('average_rating')
            ->paginate(10);

        $result = $doctors->through(function ($doctor) use ($lat, $lng) {
            return array_merge(
                (new DoctorResource($doctor))->toArray(request()),
                ['distance_km' => $this->haversine($lat, $lng, (float) $doctor->latitude, (float) $doctor->longitude)]
            );
        });

        return $this->apiResponse(true, "الأطباء ضمن {$distance} كم من موقعك", $result->toArray());
    }

    // =====================================================================
    // USER DASHBOARD — GET /api/user/dashboard
    // =====================================================================
    public function userDashboard()
    {
        $user            = auth()->user();
        $allAppointments = Appointment::where('user_id', $user->id);

        $totalAppointments     = (clone $allAppointments)->count();
        $upcomingAppointments  = (clone $allAppointments)->where('status', '!=', 'cancelled')->where('date_time', '>', now())->count();
        $completedAppointments = (clone $allAppointments)->where('status', 'completed')->count();
        $cancelledAppointments = (clone $allAppointments)->where('status', 'cancelled')->count();
        $pendingAppointments   = (clone $allAppointments)->where('status', 'pending')->count();

        $nextAppointment = Appointment::where('user_id', $user->id)
            ->where('date_time', '>', now())->where('status', '!=', 'cancelled')
            ->with(['doctor.user', 'animal'])->orderBy('date_time')->first();

        $totalAnimals = Animal::where('owner_id', $user->id)->count();
        $animals      = Animal::where('owner_id', $user->id)->latest()->take(5)
                            ->get(['id', 'name', 'species', 'breed', 'age', 'gender', 'image_path']);

        $recentDiagnoses = Diagnosis::where('user_id', $user->id)
            ->with('animal:id,name,species')->latest()->take(5)
            ->get(['id', 'animal_id', 'result', 'confidence', 'recommendations', 'created_at']);

        $favoriteDoctors = Appointment::where('user_id', $user->id)
            ->where('status', 'completed')
            ->selectRaw('doctor_id, COUNT(*) as visits_count, AVG(rating) as avg_rating')
            ->groupBy('doctor_id')->orderByDesc('visits_count')->take(3)
            ->with('doctor.user')->get()
            ->map(fn($a) => [
                'doctor_id'      => $a->doctor_id,
                'name'           => $a->doctor->user->name ?? 'غير معروف',
                'specialization' => $a->doctor->specialization ?? '',
                'visits_count'   => $a->visits_count,
                'avg_rating'     => round($a->avg_rating ?? 0, 1),
            ]);

        return $this->apiResponse(true, 'لوحة تحكم المستخدم', [
            'total_appointments'     => $totalAppointments,
            'upcoming_appointments'  => $upcomingAppointments,
            'completed_appointments' => $completedAppointments,
            'cancelled_appointments' => $cancelledAppointments,
            'pending_appointments'   => $pendingAppointments,
            'next_appointment'       => $nextAppointment ? [
                'id'        => $nextAppointment->id,
                'date_time' => $nextAppointment->date_time->format('Y-m-d H:i'),
                'type'      => $nextAppointment->type,
                'status'    => $nextAppointment->status,
                'doctor'    => ['name' => $nextAppointment->doctor->user->name ?? '', 'specialization' => $nextAppointment->doctor->specialization ?? ''],
                'animal'    => ['name' => $nextAppointment->animal->name ?? '', 'species' => $nextAppointment->animal->species ?? ''],
            ] : null,
            'total_animals'    => $totalAnimals,
            'animals'          => $animals,
            'recent_diagnoses' => $recentDiagnoses,
            'favorite_doctors' => $favoriteDoctors,
            'medical_alerts'   => $this->getMedicalAlerts($user->id),
        ]);
    }

    private function getMedicalAlerts(int $userId): array
    {
        $alerts = [];

        $soon = Appointment::where('user_id', $userId)->where('status', '!=', 'cancelled')
            ->whereBetween('date_time', [now(), now()->addHours(24)])->with(['doctor.user', 'animal'])->first();
        if ($soon) {
            $alerts[] = ['type' => 'upcoming_appointment', 'message' => 'لديك موعد غداً مع د. ' . ($soon->doctor->user->name ?? '') . ' للحيوان ' . ($soon->animal->name ?? '')];
        }

        $oldPending = Appointment::where('user_id', $userId)->where('status', 'pending')->where('created_at', '<', now()->subHours(48))->count();
        if ($oldPending > 0) {
            $alerts[] = ['type' => 'pending_appointments', 'message' => "لديك {$oldPending} موعد في انتظار تأكيد الطبيب"];
        }

        $needsRating = Appointment::where('user_id', $userId)->where('status', 'completed')->whereNull('rating')->count();
        if ($needsRating > 0) {
            $alerts[] = ['type' => 'needs_rating', 'message' => "لديك {$needsRating} موعد بانتظار تقييمك"];
        }

        return $alerts;
    }

    // =====================================================================
    // DOCTOR DASHBOARD — GET /api/doctor/dashboard
    // =====================================================================
    public function dashboard()
    {
        $doctor = auth()->user()->doctor;
        if (!$doctor) return $this->apiResponse(false, 'أنت لست طبيباً مسجلاً', null, 403);

        $appointmentsCount = Appointment::where('doctor_id', $doctor->id)->count();
        $ratingsData       = Appointment::where('doctor_id', $doctor->id)->whereNotNull('rating')
                                ->selectRaw('COUNT(*) as total, AVG(rating) as average')->first();
        $busiestDay        = Appointment::where('doctor_id', $doctor->id)
                                ->selectRaw('DAYNAME(date_time) as day, COUNT(*) as count')
                                ->groupBy('day')->orderByDesc('count')->first();
        $latestReviews     = Appointment::where('doctor_id', $doctor->id)->whereNotNull('rating')
                                ->with('user:id,name')->latest()->take(3)
                                ->get(['id', 'user_id', 'rating', 'review', 'date_time']);

        return $this->apiResponse(true, 'تم جلب بيانات لوحة التحكم', [
            'appointments_count' => $appointmentsCount,
            'ratings_count'      => (int) ($ratingsData->total ?? 0),
            'ratings_average'    => (float) round($ratingsData->average ?? 0, 2),
            'busiest_day'        => $busiestDay->day ?? 'لا توجد بيانات',
            'latest_reviews'     => $latestReviews,
        ]);
    }

    // =====================================================================
    // 📊 DOCTOR ANALYTICS — GET /api/doctor/analytics
    // =====================================================================
    public function analytics()
    {
        $doctor = auth()->user()->doctor;
        if (!$doctor) {
            return $this->apiResponse(false, 'أنت لست طبيباً مسجلاً', null, 403);
        }

        // ===== إجمالي الاستشارات =====
        $totalConsultations     = Appointment::where('doctor_id', $doctor->id)->count();
        $completedConsultations = Appointment::where('doctor_id', $doctor->id)->where('status', 'completed')->count();
        $cancelledConsultations = Appointment::where('doctor_id', $doctor->id)->where('status', 'cancelled')->count();

        $completionRate = $totalConsultations > 0
            ? round(($completedConsultations / $totalConsultations) * 100, 1)
            : 0;

        // ===== الأرباح (من جدول payments) =====
        $earningsData = Payment::whereHas('appointment', fn($q) =>
            $q->where('doctor_id', $doctor->id)
        )->where('status', 'paid');

        $totalEarnings     = (clone $earningsData)->sum('amount');
        $thisMonthEarnings = (clone $earningsData)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('amount');
        $lastMonthEarnings = (clone $earningsData)
            ->whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('amount');

        // ===== الرسم البياني الشهري (آخر 6 شهور) =====
        $monthlyChart = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);

            $monthEarnings = Payment::whereHas('appointment', fn($q) =>
                $q->where('doctor_id', $doctor->id)
            )
            ->where('status', 'paid')
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->sum('amount');

            $monthConsultations = Appointment::where('doctor_id', $doctor->id)
                ->where('status', 'completed')
                ->whereYear('date_time', $month->year)
                ->whereMonth('date_time', $month->month)
                ->count();

            $monthlyChart[] = [
                'month'         => $month->translatedFormat('F Y'),
                'month_number'  => $month->format('Y-m'),
                'earnings'      => (float) $monthEarnings,
                'consultations' => $monthConsultations,
            ];
        }

        // ===== التقييمات =====
        $ratingsData = Appointment::where('doctor_id', $doctor->id)
            ->whereNotNull('rating')
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')
            ->first();

        // ===== توزيع نوع الاستشارات =====
        $appointmentTypes = Appointment::where('doctor_id', $doctor->id)
            ->where('status', 'completed')
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->mapWithKeys(fn($item) => [$item->type => $item->count]);

        // ===== مقارنة الشهر الحالي بالشهر السابق =====
        $earningsGrowth = $lastMonthEarnings > 0
            ? round((($thisMonthEarnings - $lastMonthEarnings) / $lastMonthEarnings) * 100, 1)
            : ($thisMonthEarnings > 0 ? 100 : 0);

        return $this->apiResponse(true, 'تحليلات الطبيب', [
            // إجمالي الاستشارات
            'total_consultations'     => $totalConsultations,
            'completed_consultations' => $completedConsultations,
            'cancelled_consultations' => $cancelledConsultations,
            'completion_rate'         => $completionRate . '%',

            // الأرباح
            'total_earnings'      => (float) $totalEarnings,
            'this_month_earnings' => (float) $thisMonthEarnings,
            'last_month_earnings' => (float) $lastMonthEarnings,
            'earnings_growth'     => $earningsGrowth . '%',

            // التقييمات
            'average_rating' => (float) round($ratingsData->average ?? 0, 2),
            'total_ratings'  => (int) ($ratingsData->total ?? 0),

            // توزيع أنواع الاستشارات
            'appointment_types' => [
                'online'     => $appointmentTypes['online']     ?? 0,
                'clinic'     => $appointmentTypes['clinic']     ?? 0,
                'home_visit' => $appointmentTypes['home_visit'] ?? 0,
            ],

            // الرسم البياني الشهري (آخر 6 شهور)
            'monthly_chart' => $monthlyChart,
        ]);
    }

    // =====================================================================
    // PROFILE
    // =====================================================================
    public function completeProfile(Request $request)
    {
        $user      = auth()->user();
        $validator = Validator::make($request->all(), [
            'specialization'   => 'required|string|max:255',
            'experience_years' => 'required|integer|min:0',
            'bio'              => 'nullable|string',
            'image'            => 'nullable|image|mimes:jpeg,png,jpg,jfif|max:2048',
            'consultation_fee' => 'nullable|integer|min:0',
            'clinic_address'   => 'nullable|string|max:500',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) return $this->apiResponse(false, 'خطأ في بيانات الملف الشخصي', $validator->errors(), 422);

        $imagePath = null;
        if ($request->hasFile('image')) {
            if ($user->doctor?->image) Storage::disk('public')->delete($user->doctor->image);
            $imagePath = $request->file('image')->store('doctors', 'public');
        }

        $doctor = Doctor::updateOrCreate(['user_id' => $user->id], [
            'specialization'   => $request->specialization,
            'experience_years' => $request->experience_years,
            'bio'              => $request->bio,
            'consultation_fee' => $request->consultation_fee,
            'clinic_address'   => $request->clinic_address,
            'latitude'         => $request->latitude,
            'longitude'        => $request->longitude,
            'image'            => $imagePath ?? ($user->doctor?->image),
        ]);

        if ($user->role !== 'doctor') $user->update(['role' => 'doctor']);

        return $this->apiResponse(true, 'تم اكمال ملفك كدكتور بنجاح!', new DoctorResource($doctor->load('user')), 200);
    }

    public function updateProfile(Request $request)
    {
        $doctor = auth()->user()->doctor;
        if (!$doctor) return $this->apiResponse(false, 'يرجى إكمال ملفك الشخصي أولاً', null, 404);

        $validator = Validator::make($request->all(), [
            'specialization'   => 'sometimes|string|max:255',
            'experience_years' => 'sometimes|integer|min:0',
            'bio'              => 'nullable|string',
            'image'            => 'nullable|image|mimes:jpeg,png,jpg,jfif|max:2048',
            'consultation_fee' => 'nullable|integer|min:0',
            'clinic_address'   => 'nullable|string|max:500',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) return $this->apiResponse(false, 'خطأ في البيانات', $validator->errors(), 422);

        $imagePath = $doctor->image;
        if ($request->hasFile('image')) {
            if ($doctor->image) Storage::disk('public')->delete($doctor->image);
            $imagePath = $request->file('image')->store('doctors', 'public');
        }

        $doctor->update([
            'specialization'   => $request->specialization   ?? $doctor->specialization,
            'experience_years' => $request->experience_years ?? $doctor->experience_years,
            'bio'              => $request->bio               ?? $doctor->bio,
            'consultation_fee' => $request->consultation_fee ?? $doctor->consultation_fee,
            'clinic_address'   => $request->clinic_address   ?? $doctor->clinic_address,
            'latitude'         => $request->latitude          ?? $doctor->latitude,
            'longitude'        => $request->longitude         ?? $doctor->longitude,
            'image'            => $imagePath,
        ]);

        return $this->apiResponse(true, 'تم تحديث ملفك الشخصي بنجاح', new DoctorResource($doctor->load('user')));
    }

    public function show($id)
    {
        $doctor = Doctor::with(['user', 'availabilities'])->find($id);
        if (!$doctor) return $this->apiResponse(false, 'عفواً، هذا الطبيب غير موجود', null, 404);
        return $this->apiResponse(true, 'تم جلب بيانات الطبيب بنجاح', new DoctorResource($doctor));
    }

    public function setAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'availabilities'              => 'required|array',
            'availabilities.*.day'        => 'required|in:Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'availabilities.*.start_time' => 'required|date_format:H:i',
            'availabilities.*.end_time'   => 'required|date_format:H:i|after:availabilities.*.start_time',
        ]);

        if ($validator->fails()) return $this->apiResponse(false, 'بيانات المواعيد غير صحيحة', $validator->errors(), 422);

        $doctor = auth()->user()->doctor;
        if (!$doctor) return $this->apiResponse(false, 'يرجى إكمال ملفك الشخصي أولاً', null, 403);

        $doctor->availabilities()->delete();
        $doctor->availabilities()->createMany($request->availabilities);

        return $this->apiResponse(true, 'تم تحديث جدول مواعيدك بنجاح', $doctor->load('availabilities'));
    }

    public function myReviews()
    {
        $doctor = auth()->user()->doctor;
        if (!$doctor) return $this->apiResponse(false, 'أنت لست طبيباً مسجلاً', null, 403);

        $reviews = Appointment::where('doctor_id', $doctor->id)
            ->whereNotNull('rating')->with('user:id,name')->latest()->paginate(10);

        return $this->apiResponse(true, 'تم جلب جميع التقييمات بنجاح', $reviews);
    }
}