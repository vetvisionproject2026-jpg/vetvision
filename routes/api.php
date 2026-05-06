<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AnimalController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DiagnosisController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\DoctorVerificationController;

// =====================================================================
// 1️⃣ PUBLIC ROUTES (بدون توثيق)
// =====================================================================

Route::post('/register',         [AuthController::class, 'register']);
Route::post('/verify-email',     [AuthController::class, 'verifyEmail']);
Route::post('/login',            [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/forgot-password',  [AuthController::class, 'forgotPassword']);
Route::post('/reset-password',   [AuthController::class, 'resetPassword']);

// ✅ Social Auth Routes - جديد
// الخطوة 1: المستخدم يضغط "سجل بـ Google" → الـ App يكال الـ URL ده
Route::get('/auth/{provider}/redirect',  [AuthController::class, 'socialRedirect']);
// الخطوة 2: بعد ما يسجل عند Google، بيرجع هنا تلقائياً
Route::get('/auth/{provider}/callback',  [AuthController::class, 'socialCallback']);

Route::get('get-token/{doctorId}',       [AppointmentController::class, 'generateDoctorToken']);
Route::get('get-user-token/{userId}',    [AppointmentController::class, 'generateUserToken']);

Route::post('/payments/callback',        [PaymentController::class, 'callback']);

// =====================================================================
// 2️⃣ PROTECTED ROUTES
// =====================================================================

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // ==================== عام للجميع ====================
    Route::post('/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout',         [AuthController::class, 'logout']);

    // ==================== Notifications ====================
    Route::get('/notifications',           [NotificationController::class, 'index']);
    Route::get('/notifications/unread',    [NotificationController::class, 'unread']);
    Route::get('/notifications/badge',     [NotificationController::class, 'badge']);
    Route::put('/notifications/read-all',  [NotificationController::class, 'markAllAsRead']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}',   [NotificationController::class, 'destroy']);

    // ==================== Appointment - عام ====================
    Route::post('/appointments/check-availability', [AppointmentController::class, 'checkAvailability']);
    Route::get('/appointments/{id}',                [AppointmentController::class, 'show']);

    // ==================== Doctor Availability - عام ====================
    Route::get('/doctors/{doctorId}/available-slots',       [AppointmentController::class, 'getAvailableSlots']);
    Route::get('/doctors/{doctorId}/availability-calendar', [AppointmentController::class, 'getAvailabilityCalendar']);

    // ==================== Reviews - عام ====================
    Route::get('/doctors/{id}/reviews', [ReviewController::class, 'doctorReviews']);

    // ==================== Doctors - عام ====================
    Route::get('/doctors/nearest', [DoctorController::class, 'nearestDoctors']);
    Route::get('/doctors/nearby',  [DoctorController::class, 'nearbyDoctors']);
    Route::get('/doctors',         [DoctorController::class, 'index']);
    Route::get('/doctors/{id}',    [DoctorController::class, 'show']);

    // ==================== Chat ====================
    Route::post('/chat/start/{doctor_id}',   [ChatController::class, 'startSession']);
    Route::post('/chat/{sessionId}/send',    [ChatController::class, 'sendMessage']);
    Route::get('/chat/{sessionId}/messages', [ChatController::class, 'getMessages']);
    Route::get('/chat/sessions',             [ChatController::class, 'getSessions']);

    // ==================== Payment ====================
    Route::post('/payments/{appointmentId}/pay',   [PaymentController::class, 'pay']);
    Route::get('/payments/{appointmentId}/status', [PaymentController::class, 'status']);

    // =====================================================================
    // 3️⃣ USER-SPECIFIC ROUTES
    // =====================================================================

    Route::middleware('role:user')->group(function () {

        Route::get('/user/dashboard', [DoctorController::class, 'userDashboard']);

        Route::get('/my-animals',           [AnimalController::class, 'index']);
        Route::post('/animals',             [AnimalController::class, 'store']);
        Route::get('/animals/{id}',         [AnimalController::class, 'show']);
        Route::post('/animals/update/{id}', [AnimalController::class, 'update']);
        Route::delete('/animals/{id}',      [AnimalController::class, 'destroy']);

        Route::post('/appointments',                [AppointmentController::class, 'store']);
        Route::get('/my-appointments',              [AppointmentController::class, 'myAppointments']);
        Route::put('/appointments/{id}/cancel',     [AppointmentController::class, 'cancelByUser']);
        Route::put('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule']);

        Route::post('/appointments/{id}/rate', [ReviewController::class, 'store']);
        Route::put('/reviews/{id}',            [ReviewController::class, 'update']);
        Route::delete('/reviews/{id}',         [ReviewController::class, 'destroy']);

        Route::post('/diagnose/{animalId}',         [DiagnosisController::class, 'diagnose']);
        Route::get('/diagnosis/{id}',               [DiagnosisController::class, 'show']);
        Route::get('/animals/{animalId}/diagnoses', [DiagnosisController::class, 'index']);
        Route::delete('/diagnosis/{id}',            [DiagnosisController::class, 'destroy']);

        Route::get('/animals/{id}/prescriptions',       [MedicalRecordController::class, 'getAnimalPrescriptions']);
        Route::get('/animals/{id}/medical-history',     [MedicalRecordController::class, 'getMedicalHistory']);
        Route::put('/prescriptions/{id}/mark-complete', [MedicalRecordController::class, 'markPrescriptionComplete']);
        Route::put('/prescriptions/{id}/discontinue',   [MedicalRecordController::class, 'discontinuePrescription']);
    });

    // =====================================================================
    // 4️⃣ DOCTOR-SPECIFIC ROUTES
    // =====================================================================

    Route::middleware('role:doctor')->group(function () {

        Route::get('/doctor/dashboard',         [DoctorController::class, 'dashboard']);
        Route::get('/doctor/analytics',         [DoctorController::class, 'analytics']);
        Route::post('/doctor/update-profile',   [DoctorController::class, 'updateProfile']);
        Route::post('/doctor/complete-profile', [DoctorController::class, 'completeProfile']);
        Route::get('/doctor/reviews',           [DoctorController::class, 'myReviews']);

        Route::post('/doctor/verify', [DoctorVerificationController::class, 'submit']);

        Route::post('/doctor/availability', [DoctorController::class, 'setAvailability']);

        Route::get('/doctor/appointments',      [AppointmentController::class, 'doctorAppointments']);
        Route::put('/appointments/{id}/status', [AppointmentController::class, 'updateStatus']);

        Route::post('/appointments/{id}/prescription',     [MedicalRecordController::class, 'storePrescription']);
        Route::post('/appointments/{id}/treatment-record', [MedicalRecordController::class, 'storeTreatmentRecord']);
    });

    // =====================================================================
    // 5️⃣ ADMIN-SPECIFIC ROUTES
    // =====================================================================

   Route::middleware('role:admin')->group(function () {
        Route::get('/admin/doctors/pending',        [DoctorVerificationController::class, 'pending']);
        Route::post('/admin/doctors/{id}/approve',  [DoctorVerificationController::class, 'approve']);
        Route::post('/admin/doctors/{id}/reject',   [DoctorVerificationController::class, 'reject']);
    });

}); // ✅ قفل الـ group الكبير هنا بشكل صحيح

// ✅ Route خارج الـ middleware
Route::get('/test-facebook-config', function () {
    return config('services.facebook');
});
use Illuminate\Support\Facades\Artisan;

Route::get('/run-migrate', function () {
    try {
        // الأمر ده بيمسح كل الجداول القديمة (الفاضية) ويبنيها من جديد صح
        Artisan::call('migrate:fresh --force');
        return "<h1>Success!</h1><pre>" . Artisan::output() . "</pre>";
    } catch (\Exception $e) {
        return "<h1>Error:</h1><pre>" . $e->getMessage() . "</pre>";
    }
});