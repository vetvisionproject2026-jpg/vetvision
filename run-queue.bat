@echo off
cd /d E:\Graduation_project\VetVision-API

echo 🚀 Starting Laravel queue worker...
php artisan queue:work --tries=3

if %errorlevel% neq 0 (
    echo ❌ حصل خطأ! اتأكد من PHP و المسار.
) else (
    echo ✅ Queue worker انتهى أو شغال.
)

pause