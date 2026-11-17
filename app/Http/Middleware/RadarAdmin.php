<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RadarAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $email = $request->session()->get('radar_admin');
        if (! $email || ! in_array($email, config('radar.admins'), true)) {
            $request->session()->forget('radar_admin');

            return redirect('/admin/connexion');
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
