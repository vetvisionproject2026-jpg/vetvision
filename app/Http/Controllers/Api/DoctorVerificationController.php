<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Notifications\DoctorApprovedNotification;
use App\Notifications\DoctorDataSubmittedNotification;
use App\Notifications\DoctorRejectedNotification;

class DoctorVerificationController extends Controller
{
    use ApiResponseTrait;

    // =====================================================================
    // POST /api/doctor/verify
    // الدكتور يرفع صورة الكارنيه + صورة سيلفي
    // =====================================================================
    public function submit(Request $request)
    {
        $request->validate([
            'license_number' => 'required|string|max:50',
            'license_expiry' => 'required|date|after:today',
            'license_image'  => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'selfie_image'   => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $doctor = auth()->user()->doctor;

        if (!$doctor) {
            return $this->apiResponse(false, 'لم يتم العثور على ملف الطبيب', null, 404);
        }

        $licensePath = $request->file('license_image')->store('verifications/licenses', 'public');
        $selfiePath  = $request->file('selfie_image')->store('verifications/selfies', 'public');

        $similarityResult = $this->simulateFaceVerification(
            $request->file('license_image'),
            $request->file('selfie_image')
        );

        if (!$similarityResult['passed']) {
            Storage::disk('public')->delete($licensePath);
            Storage::disk('public')->delete($selfiePath);

            return $this->apiResponse(false, 'فشل التحقق من الهوية', [
                'similarity_score' => $similarityResult['score'],
            ], 422);
        }

        $doctor->update([
            'license_number'      => $request->license_number,
            'license_expiry'      => $request->license_expiry,
            'license_image'       => $licensePath,
            'selfie_image'        => $selfiePath,
            'verification_status' => 'pending',
            'is_verified'         => false,
        ]);

        auth()->user()->notify(new DoctorDataSubmittedNotification());

        return $this->apiResponse(true, 'تم رفع بيانات التوثيق بنجاح، في انتظار مراجعة الأدمن', [
            'similarity_score' => $similarityResult['score'],
            'status'           => 'pending',
        ]);
    }

    // =====================================================================
    // GET /api/admin/doctors/pending
    // الأدمن يشوف الطلبات المعلقة
    // =====================================================================
    public function pending()
    {
        $doctors = Doctor::where('verification_status', 'pending')
            ->with('user:id,name,email')
            ->get()
            ->map(fn($d) => [
                'id'             => $d->id,
                'name'           => $d->user->name ?? '',
                'email'          => $d->user->email ?? '',
                'license_number' => $d->license_number,
                'license_expiry' => $d->license_expiry,
                'license_image'  => $d->license_image ? Storage::disk('public')->url($d->license_image) : null,
                'selfie_image'   => $d->selfie_image  ? Storage::disk('public')->url($d->selfie_image)  : null,
                'submitted_at'   => $d->updated_at,
            ]);

        return $this->apiResponse(true, 'طلبات التوثيق المعلقة', [
            'total'   => $doctors->count(),
            'doctors' => $doctors,
        ]);
    }

    // =====================================================================
    // POST /api/admin/doctors/{id}/approve
    // الأدمن يقبل الطلب
    // =====================================================================
   // =====================================================================
    // الموافقة على توثيق الطبيب وتفعيل حسابه
    // =====================================================================
    public function approve($id)
    {
        // 1. البحث عن الطبيب في قاعدة البيانات
        $doctor = Doctor::find($id);

        // 2. التأكد إن الطبيب موجود أصلاً
        if (!$doctor) {
            return $this->apiResponse(false, 'الطبيب غير موجود', null, 404);
        }

        // 3. ✨ القفل الاحترافي ✨
        // التأكد إن الدكتور رفع صور الكارنيه والسيلفي (عشان ميتوافقش عليه بالخطأ)
        if (empty($doctor->license_image) || empty($doctor->selfie_image)) {
            return $this->apiResponse(false, 'عذراً، لا يمكن الموافقة على هذا الطبيب لأنه لم يرفع بيانات التوثيق (الصور) بعد', null, 400);
        }

        // 4. التأكد إن الطلب لسه "تحت المراجعة" ومش متوافق عليه قبل كدة
        if ($doctor->verification_status !== 'pending') {
            return $this->apiResponse(false, 'هذا الطلب تمت معالجته مسبقاً', null, 400);
        }

        // 5. تحديث بيانات الطبيب وتفعيل حسابه
        $doctor->update([
            'is_verified'         => true,
            'verification_status' => 'approved',
            'rejection_reason'    => null, // بنمسح أي سبب رفض قديم لو كان موجود
        ]);

        // 6. إرسال إشعار (Email) للدكتور بمجرد القبول
        if ($doctor->user) {
            $doctor->user->notify(new \App\Notifications\DoctorApprovedNotification());
        }

        return $this->apiResponse(true, 'تم قبول الطبيب بنجاح وتفعيل حسابه وإرسال إيميل التفعيل');
    }
    // =====================================================================
    // POST /api/admin/doctors/{id}/reject
    // الأدمن يرفض الطلب مع سبب
    // =====================================================================
    // =====================================================================
    // رفض طلب التوثيق (مؤمنة بوجود بيانات)
    // =====================================================================
    public function reject(Request $request, $id)
    {
        // 1. التأكد إن الأدمن كتب سبب الرفض
        $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'سبب الرفض مطلوب لإبلاغ الدكتور',
        ]);

        // 2. البحث عن الطبيب
        $doctor = Doctor::find($id);

        if (!$doctor) {
            return $this->apiResponse(false, 'الطبيب غير موجود', null, 404);
        }

        // 3. ✨ القفل الاحترافي ✨
        // مينفعش نرفض بيانات مش موجودة أصلاً!
        if (empty($doctor->license_image) || empty($doctor->selfie_image)) {
            return $this->apiResponse(false, 'عذراً، لا يمكن رفض الطلب لأن الطبيب لم يرفع بيانات التوثيق بعد', null, 400);
        }

        // 4. التأكد إن الطلب لسه تحت المراجعة
        if ($doctor->verification_status !== 'pending') {
            return $this->apiResponse(false, 'هذا الطلب تمت معالجته مسبقاً (مقبول أو مرفوض بالفعل)', null, 400);
        }

        // 5. تحديث الحالة لـ rejected وحفظ السبب
        $doctor->update([
            'is_verified'         => false,
            'verification_status' => 'rejected',
            'rejection_reason'    => $request->reason,
        ]);

        // 6. إرسال الإشعار للدكتور مع تمرير السبب (عشان يظهر في الإيميل)
        if ($doctor->user) {
            $doctor->user->notify(new \App\Notifications\DoctorRejectedNotification($request->reason));
        }

        return $this->apiResponse(true, 'تم رفض الطلب وإرسال سبب الرفض للدكتور عبر البريد الإلكتروني', [
            'reason' => $request->reason,
        ]);
    }
    // =====================================================================
    // 🤖 SIMULATION — استبدلها بـ AI model حقيقي
    // =====================================================================
    private function simulateFaceVerification($licenseImage, $selfieImage): array
    {
        $score     = round(mt_rand(55, 99) / 100, 2);
        $threshold = 0.60;

        return [
            'score'     => $score,
            'threshold' => $threshold,
            'passed'    => $score >= $threshold,
            'note'      => 'simulation mode — replace with real AI model',
        ];
    }
}