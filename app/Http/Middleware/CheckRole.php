<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        

        if (!Auth::check() || Auth::user()->role !== $role) {
        // redirect to a different page or return an error response
        if (Auth::check()){
            if(Auth::user()->role === 'carrier'){
                return redirect('/dashboard');
            }
            else if(Auth::user()->role === 'dispatcher'){
                return redirect('/dispatcher/dashboard');
            }
        }
 
        return redirect('/login');
        }
        return $next($request);
    }
}
