@echo off
chcp 65001 >nul
cd /d E:\Graduation_project\VetVision-API
echo.
echo ================================
echo 🌐 Starting Laravel Server...
echo ================================
echo.
php artisan serve
if %errorlevel% neq 0 (
    echo.
    echo ❌ حصل خطأ في Server!
) else (
    echo.
    echo ✅ Server انتهى.
)
pause