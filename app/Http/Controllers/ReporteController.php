<?php

namespace App\Http\Controllers;

use App\Services\Supabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReporteController extends Controller
{
    public function __construct(private readonly Supabase $supabase)
    {
    }

    public function index(Request $request)
    {
        [$desde, $hasta] = $this->periodo($request);

        return view('reportes.index', [
            'desde' => $desde,
            'hasta' => $hasta,
            'reporte' => $this->consultar($desde, $hasta),
            'generado' => Carbon::now(config('app.timezone')),
        ]);
    }

    public function csv(Request $request)
    {
        [$desde, $hasta] = $this->periodo($request);
        $reporte = $this->consultar($desde, $hasta);

        return response()->streamDownload(function () use ($reporte, $desde, $hasta) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Reporte de operaciones', "Del {$desde} al {$hasta}"]);
            fputcsv($salida, []);
            fputcsv($salida, ['Nodo', 'Tipo', 'Responsable', 'Estado', 'Efectivo disponible', 'Operaciones', 'Retiros', 'Monto retiros', 'Depósitos', 'Monto depósitos', 'Transferencias', 'Monto transferencias']);

            foreach ($reporte['por_nodo'] as $n) {
                fputcsv($salida, [
                    $n['nombre'], $n['tipo'], $n['responsable'], $n['estado'],
                    number_format((float) $n['efectivo_disponible'], 2, '.', ''),
                    $n['operaciones'],
                    $n['retiros'], number_format((float) $n['monto_retiros'], 2, '.', ''),
                    $n['depositos'], number_format((float) $n['monto_depositos'], 2, '.', ''),
                    $n['transferencias'], number_format((float) $n['monto_transferencias'], 2, '.', ''),
                ]);
            }

            $r = $reporte['resumen'];
            fputcsv($salida, [
                'TOTAL', '', '', '', '', $r['operaciones'],
                $r['retiros'], number_format((float) $r['monto_retiros'], 2, '.', ''),
                $r['depositos'], number_format((float) $r['monto_depositos'], 2, '.', ''),
                $r['transferencias'], number_format((float) $r['monto_transferencias'], 2, '.', ''),
            ]);

            fclose($salida);
        }, "reporte_operaciones_{$desde}_{$hasta}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Por defecto: del día 1 del mes actual a hoy. */
    private function periodo(Request $request): array
    {
        $datos = $this->filtrosValidos($request, [
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $hoy = Carbon::now(config('app.timezone'));

        return [
            $datos['desde'] ?? $hoy->copy()->startOfMonth()->toDateString(),
            $datos['hasta'] ?? $hoy->toDateString(),
        ];
    }

    private function consultar(string $desde, string $hasta): array
    {
        $zona = config('app.timezone');

        return $this->supabase->rpc('admin_reporte', [
            'p_desde' => Carbon::parse($desde, $zona)->startOfDay()->toIso8601String(),
            'p_hasta' => Carbon::parse($hasta, $zona)->addDay()->startOfDay()->toIso8601String(),
            'p_zona' => $zona,
        ]);
    }
}
