<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Notifications\AppointmentReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // 🔍 البحث فقط عن المواعيد التي تاريخها "غداً" (بكرة) ولم يتم إرسال تذكير لها بعد
            // now()->addDay()->toDateString() تجلب تاريخ الغد بصيغة YYYY-MM-DD
            $appointments = Appointment::where('status', '!=', 'cancelled')
                ->where('reminder_sent', 0) 
                ->whereDate('date_time', now()->addDay()->toDateString())
                ->with(['user', 'doctor.user', 'animal'])
                ->get();

            Log::info("🔍 [Reminders] Found " . $appointments->count() . " appointments for tomorrow to process.");

            foreach ($appointments as $appointment) {
                try {
                    // 1. إرسال الإشعار للمستخدم (صاحب الحيوان)
                    if ($appointment->user) {
                        $appointment->user->notify(
                            new AppointmentReminderNotification($appointment, 'user')
                        );
                    }

                    // 2. إرسال الإشعار للدكتور (عن طريق اليوزر المرتبط به)
                    if ($appointment->doctor && $appointment->doctor->user) {
                        $appointment->doctor->user->notify(
                            new AppointmentReminderNotification($appointment, 'doctor')
                        );
                    }

                    // 3. تحديث حالة الموعد في قاعدة البيانات (تغيير 0 إلى 1)
                    $appointment->update([
                        'reminder_sent' => 1,
                        'reminder_sent_at' => now(),
                    ]);

                    Log::info("✅ [Reminders] Notification sent for Appointment ID: {$appointment->id}");

                } catch (\Exception $e) {
                    Log::error("❌ [Reminders] Failed for Appointment {$appointment->id}: {$e->getMessage()}");
                }
            }

            Log::info("🎯 [Reminders] Job execution finished.");

        } catch (\Exception $e) {
            Log::error("🔥 [Reminders] Critical Error: {$e->getMessage()}");
        }
    }
}