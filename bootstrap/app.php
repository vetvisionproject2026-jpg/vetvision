<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 1. تعريف الأدوار (Roles)
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);

        // 2. تأمين الـ API ضد الـ CSRF وتفعيل الـ Statefulness (Security)
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        
        // أ. معالجة خطأ "العنصر غير موجود" (404)
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => 'العنصر المطلوب غير موجود في قاعدة البيانات.',
                    'data' => null
                ], 404);
            }
        });

        // ب. معالجة خطأ "عدم تسجيل الدخول" (401)
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => 'عفواً، يجب تسجيل الدخول أولاً للوصول لهذا الرابط.',
                    'data' => null
                ], 401);
            }
        });

        // ج. معالجة أخطاء الـ Validation (422) بنظام JSON موحد
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => 'خطأ في البيانات المدخلة.',
                    'errors' => $e->errors() // رجعنا الـ errors مباشرة هنا للوضوح
                ], 422);
            }
        });

        // د. لمسة احترافية: معالجة أي خطأ غير متوقع (500) منعاً لظهور كود السيرفر
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => 'حدث خطأ غير متوقع في الخادم، حاول لاحقاً.',
                    'debug' => config('app.debug') ? $e->getMessage() : null // يظهر فقط في وضع التطوير
                ], 500);
            }
        });
    })->create();