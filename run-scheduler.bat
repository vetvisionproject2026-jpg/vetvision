@echo off
chcp 65001 >nul
cd /d E:\Graduation_project\VetVision-API
echo.
echo ================================
echo 📅 Starting Scheduler...
echo ================================
echo.
php artisan schedule:work
if %errorlevel% neq 0 (
    echo.
    echo ❌ حصل خطأ في Scheduler!
) else (
    echo.
    echo ✅ Scheduler انتهى.
)
pause