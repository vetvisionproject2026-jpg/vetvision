<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Animal;
use App\Models\Doctor;
use App\Notifications\AppointmentBookedNotification;
use App\Notifications\AppointmentConfirmedNotification;
use App\Notifications\AppointmentCancelledNotification;
use App\Notifications\RatingReceivedNotification;
use App\Http\Resources\AppointmentResource;
use App\Notifications\AppointmentPendingNotification;
use App\Notifications\AppointmentCompletedNotification;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    use ApiResponseTrait;

   public function store(StoreAppointmentRequest $request)
{
    $user = auth()->user();

    // 1. التأكد إن الحيوان يخص المستخدم
    $animal = Animal::where('id', $request->animal_id)
        ->where('owner_id', $user->id)
        ->first();

    if (!$animal) {
        return $this->apiResponse(false, 'هذا الحيوان غير تابع لك', null, 403);
    }

    // 2. التأكد من شرط الـ 48 ساعة
    $appointmentTime = Carbon::parse($request->date_time);
    if ($appointmentTime->lt(Carbon::now()->addHours(48))) {
        return $this->apiResponse(false, 'لازم تحجز قبل 48 ساعة من الموعد', null, 400);
    }

    // 3. التأكد من عدم وجود تعارض
    if (Appointment::hasConflict($request->doctor_id, $user->id, $request->date_time)) {
        return $this->apiResponse(false, 'عفواً، يوجد تعارض في هذا الموعد', null, 422);
    }

    // 4. إنشاء الموعد في قاعدة البيانات
    $appointment = Appointment::create([
        'user_id'    => $user->id,
        'doctor_id'  => $request->doctor_id,
        'animal_id'  => $request->animal_id,
        'date_time'  => $request->date_time,
        'type'       => $request->type,
        'location'   => $request->location,
        'latitude'   => $request->latitude,
        'longitude'  => $request->longitude,
        'reason'     => $request->reason,
        'notes'      => $request->notes,
        'status'     => 'pending',
    ]);

    // 5. تحميل العلاقات (عشان النوتيفيكيشن تاخد البيانات صح)
    $appointment->load(['doctor.user', 'animal', 'user']);

    // 6. ✅ إرسال إشعار للدكتور (عشان يعرف إن فيه حجز جديد)
    if ($appointment->doctor && $appointment->doctor->user) {
        $appointment->doctor->user->notify(new AppointmentBookedNotification($appointment));
    }

    // 7. ✅ الجزء الجديد: إرسال إشعار للمستخدم (عشان يطمن إن طلبه "قيد الانتظار")
    $user->notify(new AppointmentPendingNotification($appointment));

    return $this->apiResponse(true, 'تم الحجز بنجاح، وتم إرسال إشعارات لك وللطبيب', $appointment);
}

    public function myAppointments()
    {
        $appointments = auth()->user()->appointments()
            ->with(['doctor.user', 'animal'])
            ->latest()
            ->paginate(10);

        return $this->apiResponse(
            true,
            'قائمة مواعيدك الحالية',
            AppointmentResource::collection($appointments)->response()->getData(true)
        );
    }

    public function doctorAppointments()
    {
        $user = auth()->user();

        if (!$user->doctor) {
            return $this->apiResponse(false, 'عفواً، هذا الحساب ليس له ملف طبيب', null, 404);
        }

        $appointments = Appointment::where('doctor_id', $user->doctor->id)
            ->with(['user', 'animal'])
            ->latest()
            ->paginate(10);

        return $this->apiResponse(
            true,
            'قائمة المواعيد المحجوزة عندك',
            AppointmentResource::collection($appointments)->response()->getData(true)
        );
    }

    public function updateStatus(Request $request, $id)
{
    $user = auth()->user();

    if ($user->role !== 'doctor' || !$user->doctor) {
        return $this->apiResponse(false, 'غير مسموح لك بتغيير حالة الموعد', null, 403);
    }

    $validator = Validator::make($request->all(), [
        'status' => 'required|in:confirmed,cancelled,completed',
    ]);

    if ($validator->fails()) {
        return $this->apiResponse(false, 'خطأ في البيانات', $validator->errors(), 422);
    }

    $appointment = Appointment::with(['user', 'animal'])->find($id);

    if (!$appointment) {
        return $this->apiResponse(false, 'عذراً، هذا الموعد غير موجود.', null, 404);
    }

    if ($appointment->doctor_id !== $user->doctor->id) {
        return $this->apiResponse(false, 'مش عندك صلاحية تعدل الموعد ده!', null, 403);
    }

    if (in_array($appointment->status, ['completed', 'cancelled'])) {
        return $this->apiResponse(false, 'لا يمكن تعديل حالة موعد منتهي أو ملغي بالفعل.', null, 422);
    }

    try {
        $appointment->update(['status' => $request->status]);

        // ✅ التحقق من أن المستخدم موجود قبل الإرسال
        // ✅ التحقق من أن المستخدم موجود قبل الإرسال
if ($appointment->user) {
    if ($request->status === 'confirmed') {
        $appointment->user->notify(new AppointmentConfirmedNotification($appointment));
    } elseif ($request->status === 'cancelled') {
        $appointment->user->notify(new AppointmentCancelledNotification($appointment));
    } elseif ($request->status === 'completed') {
        // هنا ناقص إرسال إشعار الإتمام
        // لو عندك Notification جاهزة لإتمام الموعد استدعيها هنا
        $appointment->user->notify(new AppointmentCompletedNotification($appointment));
    }
}

        return $this->apiResponse(
            true,
            'تم تحديث حالة الموعد بنجاح وإرسال إشعار للعميل.',
            new AppointmentResource($appointment->load(['user', 'animal'])),
            200
        );
    } catch (\Exception $e) {
        return $this->apiResponse(
            false,
            'حدث خطأ أثناء تحديث الموعد: ' . $e->getMessage(),
            null,
            500
        );
    }
}
    public function cancelByUser($id)
    {
        $appointment = Appointment::find($id);

        if (!$appointment) {
            return $this->apiResponse(false, 'هذا الموعد غير موجود.', null, 404);
        }

        if ($appointment->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'مش عندك صلاحية تلغي الموعد ده!', null, 403);
        }

        if (in_array($appointment->status, ['completed', 'cancelled'])) {
            return $this->apiResponse(false, 'لا يمكن إلغاء هذا الموعد.', null, 400);
        }

        $appointment->update(['status' => 'cancelled']);

        $appointment->load('doctor.user');
        if ($appointment->doctor && $appointment->doctor->user) {
            $appointment->doctor->user->notify(new AppointmentCancelledNotification($appointment));
        }

        return $this->apiResponse(true, 'تم إلغاء الموعد بنجاح.', $appointment);
    }

    public function reschedule(Request $request, $id)
    {
        $appointment = Appointment::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$appointment) {
            return $this->apiResponse(false, 'الموعد غير موجود أو لا تملك صلاحية تعديله.', null, 404);
        }

        $request->validate([
            'date_time' => 'required|date|after:now',
        ]);

        $conflict = Appointment::where('doctor_id', $appointment->doctor_id)
            ->where('date_time', $request->date_time)
            ->where('id', '!=', $id)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($conflict) {
            return $this->apiResponse(false, 'عفواً، الدكتور لديه موعد آخر في هذا التوقيت.', null, 400);
        }

        $appointment->update([
            'date_time' => $request->date_time,
            'status'    => 'pending',
        ]);

        return $this->apiResponse(
            true,
            'تم تعديل ميعاد الموعد بنجاح، وفي انتظار تأكيد الطبيب.',
            new AppointmentResource($appointment)
        );
    }

    public function show($id)
    {
        $appointment = Appointment::with(['user', 'doctor.user', 'animal'])->find($id);

        if (!$appointment) {
            return $this->apiResponse(false, 'الموعد غير موجود', null, 404);
        }

        $user = auth()->user();
        if ($appointment->user_id !== $user->id &&
            (!$user->doctor || $appointment->doctor_id !== $user->doctor->id)) {
            return $this->apiResponse(false, 'مش عندك صلاحية تشوف الموعد ده!', null, 403);
        }

        return $this->apiResponse(
            true,
            'تم جلب بيانات الموعد بنجاح',
            new AppointmentResource($appointment)
        );
    }

    public function rate(Request $request, $id)
{
    $appointment = Appointment::find($id);

    if (!$appointment) {
        return $this->apiResponse(false, 'الموعد غير موجود', null, 404);
    }

    // 1. ✅ التأكد إن الموعد لم يتم تقييمه من قبل
    if (!is_null($appointment->rating)) {
        return $this->apiResponse(false, 'عذراً، لقد قمت بتقييم هذا الموعد مسبقاً', null, 400);
    }

    if (auth()->id() !== $appointment->user_id) {
        return $this->apiResponse(false, 'عذراً، التقييم متاح فقط للعميل صاحب الموعد', null, 403);
    }

    if ($appointment->status !== 'completed') {
        return $this->apiResponse(false, 'لا يمكنك التقييم إلا بعد انتهاء الموعد', null, 400);
    }

    $request->validate([
        'rating' => 'required|integer|min:1|max:5',
        'review' => 'nullable|string|max:500',
    ]);

    $appointment->update([
        'rating' => $request->rating,
        'review' => $request->review,
    ]);

    // تحديث المتوسط في جدول الدكاترة
    $appointment->doctor->updateRating(); 

    // إرسال إشعار للدكتور
    $appointment->load('doctor.user');
    if ($appointment->doctor && $appointment->doctor->user) {
        $appointment->doctor->user->notify(new RatingReceivedNotification($appointment));
    }

    return $this->apiResponse(true, 'شكراً لك! تم حفظ تقييمك بنجاح', $appointment);
}
    public function generateDoctorToken($doctorId)
    {
        $doctor = \App\Models\Doctor::with('user')->find($doctorId);

        if (!$doctor) {
            return response()->json(['message' => 'Doctor ID not found in doctors table'], 404);
        }

        $user = $doctor->user;

        if (!$user) {
            return response()->json(['message' => 'This doctor has no linked user account'], 404);
        }

        $token = $user->createToken('AutoToken')->plainTextToken;

        return response()->json([
            'status'  => true,
            'message' => 'تم استخراج التوكن بنجاح لدكتور: ' . $user->name,
            'user_id' => $user->id,
            'token'   => $token,
        ]);
    }

    public function generateUserToken($userId)
    {
        $user = \App\Models\User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'User ID not found in users table'], 404);
        }

        $token = $user->createToken('ManualUserToken')->plainTextToken;

        return response()->json([
            'status'  => true,
            'message' => 'تم استخراج التوكن بنجاح لليوزر: ' . $user->name,
            'user_id' => $user->id,
            'token'   => $token,
        ]);
    }

    public function getAvailableSlots(Request $request, $doctorId)
    {
        $date = $request->query('date');

        if (!$date) {
            return $this->apiResponse(false, 'التاريخ مطلوب', null, 400);
        }

        try {
            $selectedDate = Carbon::parse($date);
            if ($selectedDate->lt(Carbon::today())) {
                return $this->apiResponse(false, 'التاريخ يجب أن يكون اليوم أو بعده', null, 400);
            }
        } catch (\Exception $e) {
            return $this->apiResponse(false, 'التاريخ غير صالح', null, 400);
        }

        $doctor = Doctor::with('availabilities')->find($doctorId);
        if (!$doctor) {
            return $this->apiResponse(false, 'الطبيب غير موجود', null, 404);
        }

        $dayName       = $selectedDate->format('l');
        $availabilities = $doctor->availabilities()->where('day', $dayName)->get();

        if ($availabilities->isEmpty()) {
            return $this->apiResponse(true, 'لا يوجد مواعيد متاحة', ['available_slots' => []]);
        }

        $slots = [];
        foreach ($availabilities as $availability) {
            $start = Carbon::parse($availability->start_time);
            $end   = Carbon::parse($availability->end_time);

            while ($start->copy()->addMinutes(30)->lte($end)) {
                $slotDateTime = $selectedDate->copy()->setTimeFromTimeString($start->format('H:i'));

                $isBooked = Appointment::where('doctor_id', $doctorId)
                    ->where('date_time', $slotDateTime)
                    ->where('status', '!=', 'cancelled')
                    ->exists();

                if (!$isBooked) {
                    $slots[] = [
                        'time'      => $start->format('H:i'),
                        'available' => true,
                    ];
                }

                $start->addMinutes(30);
            }
        }

        return $this->apiResponse(true, 'الـ slots المتاحة', [
            'date'            => $selectedDate->format('Y-m-d'),
            'available_slots' => $slots,
        ]);
    }

    public function checkAvailability(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'date_time' => 'required|date_format:Y-m-d H:i|after:now',
        ]);

        $exists = Appointment::where('doctor_id', $request->doctor_id)
            ->where('date_time', $request->date_time)
            ->where('status', '!=', 'cancelled')
            ->exists();

        return $this->apiResponse(
            !$exists,
            $exists ? 'غير متاح' : 'متاح',
            ['available' => !$exists]
        );
    }

    public function getAvailabilityCalendar($doctorId)
    {
        $doctor = Doctor::with('availabilities')->find($doctorId);

        if (!$doctor) {
            return $this->apiResponse(false, 'الطبيب غير موجود', null, 404);
        }

        $days = [];

        for ($i = 0; $i < 30; $i++) {
            $date    = Carbon::now()->addDays($i);
            $dayName = $date->format('l');

            $has = $doctor->availabilities()->where('day', $dayName)->exists();

            if ($has) {
                $days[] = [
                    'date' => $date->format('Y-m-d'),
                    'day'  => $dayName,
                ];
            }
        }

        return $this->apiResponse(true, 'الأيام المتاحة', $days);
    }
}