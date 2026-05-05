<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Review;
use App\Models\Doctor;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ApiResponseTrait;

    // =====================================================================
    // POST /api/appointments/{id}/rate
    // اليوزر يضيف تقييم على موعد مكتمل
    // =====================================================================
    public function store(Request $request, $id)
    {
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $appointment = Appointment::find($id);

        if (!$appointment) {
            return $this->apiResponse(false, 'الموعد غير موجود', null, 404);
        }

        // التأكد إن المستخدم صاحب الموعد
        if ($appointment->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'مش عندك صلاحية تقيّم الموعد ده', null, 403);
        }

        // التأكد إن الموعد مكتمل
        if ($appointment->status !== 'completed') {
            return $this->apiResponse(false, 'التقييم متاح فقط للمواعيد المكتملة', null, 400);
        }

        // التأكد إن الموعد ملوش تقييم قبل كده
        if (Review::where('appointment_id', $id)->exists()) {
            return $this->apiResponse(false, 'قمت بتقييم هذا الموعد مسبقاً', null, 400);
        }

        $review = Review::create([
            'appointment_id' => $appointment->id,
            'user_id'        => auth()->id(),
            'doctor_id'      => $appointment->doctor_id,
            'rating'         => $request->rating,
            'comment'        => $request->comment,
            'reviewed_at'    => now(),
        ]);

        // تحديث متوسط تقييم الدكتور
        $appointment->doctor->updateAverageRating();

        return $this->apiResponse(true, 'شكراً! تم حفظ تقييمك بنجاح', $review->load('user:id,name'), 201);
    }

    // =====================================================================
    // PUT /api/reviews/{id}
    // اليوزر يعدّل تقييمه
    // =====================================================================
    public function update(Request $request, $id)
    {
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = Review::find($id);

        if (!$review) {
            return $this->apiResponse(false, 'التقييم غير موجود', null, 404);
        }

        if ($review->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'مش عندك صلاحية تعدّل التقييم ده', null, 403);
        }

        $review->update([
            'rating'  => $request->rating,
            'comment' => $request->comment,
        ]);

        // تحديث متوسط تقييم الدكتور
        $review->doctor->updateAverageRating();

        return $this->apiResponse(true, 'تم تعديل تقييمك بنجاح', $review->load('user:id,name'));
    }

    // =====================================================================
    // DELETE /api/reviews/{id}
    // اليوزر يحذف تقييمه
    // =====================================================================
    public function destroy($id)
    {
        $review = Review::find($id);

        if (!$review) {
            return $this->apiResponse(false, 'التقييم غير موجود', null, 404);
        }

        if ($review->user_id !== auth()->id()) {
            return $this->apiResponse(false, 'مش عندك صلاحية تحذف التقييم ده', null, 403);
        }

        $doctor = $review->doctor;
        $review->delete();

        // إعادة حساب متوسط التقييم بعد الحذف
        $doctor->updateAverageRating();

        return $this->apiResponse(true, 'تم حذف تقييمك بنجاح');
    }

    // =====================================================================
    // GET /api/doctors/{id}/reviews
    // شوف كل تقييمات دكتور معين
    // =====================================================================
    public function doctorReviews($id)
    {
        $doctor = Doctor::find($id);

        if (!$doctor) {
            return $this->apiResponse(false, 'الطبيب غير موجود', null, 404);
        }

        $reviews = Review::where('doctor_id', $id)
            ->with('user:id,name')
            ->latest('reviewed_at')
            ->get();

        return $this->apiResponse(true, 'تقييمات الطبيب', [
            'doctor_name'    => $doctor->user->name ?? '',
            'average_rating' => $doctor->average_rating,
            'total_reviews'  => $reviews->count(),
            'reviews'        => $reviews,
        ]);
    }
}