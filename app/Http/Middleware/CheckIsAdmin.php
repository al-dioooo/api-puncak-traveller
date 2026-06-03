<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === User::ROLE_ADMIN) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Unauthorized. Administrator access required.',
        ], 403);
    }
}
