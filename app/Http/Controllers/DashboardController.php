<?php

namespace App\Http\Controllers;

use App\Services\Supabase;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(Supabase $supabase)
    {
        $hoy = Carbon::now(config('app.timezone'))->startOfDay();

        $reporte = $supabase->rpc('admin_reporte', [
            'p_desde' => $hoy->toIso8601String(),
            'p_hasta' => $hoy->copy()->addDay()->toIso8601String(),
            'p_zona' => config('app.timezone'),
        ]);

        $ultimas = $supabase->rpc('admin_historial', ['p_limite' => 8]);

        return view('dashboard', [
            'sistema' => $reporte['sistema'],
            'hoy' => $reporte['resumen'],
            'ultimas' => $ultimas['filas'],
        ]);
    }
}
