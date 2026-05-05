<?php

namespace App\Console;

use App\Jobs\SendAppointmentReminders;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        /**
         * إرسال تذكيرات المواعيد (Job) كل ساعة
         */
        $schedule->job(new SendAppointmentReminders())
                 ->hourly()
                 ->name('send-appointment-reminders')
                 ->withoutOverlapping(10);

        /**
         * تشغيل Command لتذكيرات المواعيد يوميًا الساعة 8 صباحًا
         */
        $schedule->command('reminders:send')->dailyAt('08:00');

        /**
         * (اختياري) تنظيف المواعيد الملغاة القديمة يومياً الساعة 2 صباحًا
         */
        $schedule->call(function () {
            \App\Models\Appointment::where('status', 'cancelled')
                                  ->where('created_at', '<', now()->subDays(90))
                                  ->delete();
        })->dailyAt('02:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}