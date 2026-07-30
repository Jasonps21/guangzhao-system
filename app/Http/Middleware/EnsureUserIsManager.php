<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi route di luar panel Filament (mis. cetak PDF) hanya untuk Super Admin
 * dan Admin. Kolektor hanya berkepentingan pada halaman lapangan, sehingga tidak
 * boleh menarik daftar anggota atau nota lewat URL langsung.
 *
 * Syaratnya sama dengan akses panel: akun aktif dan berperan manajer.
 */
class EnsureUserIsManager
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->is_active && $user->isManager(), 403);

        return $next($request);
    }
}
