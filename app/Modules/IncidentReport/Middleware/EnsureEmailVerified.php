<?php

namespace App\Modules\IncidentReport\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PRD 8.1 / CORE-02: fitur insiden hanya untuk akun aktif dengan email terverifikasi.
 * Dipasang sebelum Idempotency-Key agar respons 403 tidak tersimpan sebagai cache.
 */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('Autentikasi diperlukan.', 401, 'UNAUTHENTICATED');
        }

        if ($user->is_active === false) {
            return ApiResponse::error('Akun dinonaktifkan.', 403, 'FORBIDDEN');
        }

        if ($user->email_verified_at === null) {
            return ApiResponse::error('Verifikasi email terlebih dahulu untuk menggunakan fitur laporan insiden.', 403, 'EMAIL_NOT_VERIFIED');
        }

        return $next($request);
    }
}
