<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SendAppointmentReminders;

class SendReminders extends Command
{
    protected $signature = 'reminders:send';
    protected $description = 'Send appointment reminders automatically';

    public function handle()
    {
        SendAppointmentReminders::dispatchSync(); // ينفذ الـ Job مباشرة
        $this->info('Reminders job executed successfully!');
    }
}