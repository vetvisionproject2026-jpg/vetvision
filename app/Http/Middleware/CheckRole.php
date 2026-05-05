<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // 1. Check if user is logged in
        // 2. Check if their role matches the required role
        if (!$request->user() || $request->user()->role !== $role) {
            return response()->json([
                'message' => 'Unauthorized! You do not have the required permissions.'
            ], 403);
        }

        return $next($request);
    }
}