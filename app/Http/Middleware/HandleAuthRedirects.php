<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HandleAuthRedirects
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If user is not authenticated and trying to access a protected route
        if (!Auth::check() && !$request->is('login') && !$request->is('register') && !$request->is('/')) {
            // Store the intended URL for redirect after login
            session()->put('url.intended', $request->url());
            
            // If it's an Inertia request, return a redirect response
            if ($request->inertia()) {
                return redirect()->route('login')->with('error', 'You must be logged in to access this page.');
            }
            
            // For regular requests, redirect to login
            return redirect()->route('login')->with('error', 'You must be logged in to access this page.');
        }

        // If user is authenticated and trying to access login/register pages
        if (Auth::check() && ($request->is('login') || $request->is('register'))) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}








