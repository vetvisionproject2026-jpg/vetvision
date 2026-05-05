@echo off
chcp 65001 >nul

cd /d E:\Graduation_project\VetVision-API

REM شغّل Queue Worker في الخلفية (بدون نافذة واضحة)
start /B php artisan queue:work --tries=3

REM شغّل Scheduler
start cmd /k "php artisan schedule:work"

REM شغّل Server
start cmd /k "php artisan serve"

echo ✅ جميع الخدمات بدأت!
exit