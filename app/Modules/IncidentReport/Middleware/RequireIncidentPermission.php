<?php

namespace App\Modules\IncidentReport\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memeriksa semua permission Spatie yang disebut (bukan string role), dengan respons
 * 403 berformat PRD 8.1. Contoh: RequireIncidentPermission::class . ':incident.view,incident.dispatch'
 */
class RequireIncidentPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        foreach ($permissions as $permission) {
            if ($user === null || !$user->can($permission)) {
                return ApiResponse::error('Akses ditolak. Anda tidak memiliki izin untuk tindakan ini.', 403, 'FORBIDDEN');
            }
        }

        return $next($request);
    }
}
