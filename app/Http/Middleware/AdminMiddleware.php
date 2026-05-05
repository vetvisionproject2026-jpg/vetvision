<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
{
    // بنتشيك هل اليوزر عامل Login وهل هو admin فعلاً؟
    if (auth()->check() && auth()->user()->role === 'admin') {
        return $next($request);
    }

    // لو مش admin بنرجع رسالة خطأ
    return response()->json([
        'status'  => false,
        'message' => 'عفواً، هذه الصلاحية للأدمن فقط!'
    ], 403);
}
}
