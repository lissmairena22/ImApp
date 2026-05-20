<?php

namespace App\Http\Middleware;

use App\Models\CashRegister;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCashRegisterIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        $hasOpenRegister = CashRegister::where('user_id', auth()->id())
            ->where('status', 'Abierta')
            ->exists();

        if (!$hasOpenRegister) {
            return redirect()
                ->route('dashboard')
                ->with('cash_required', 'Debes aperturar caja antes de acceder a los modulos del sistema.');
        }

        return $next($request);
    }
}
