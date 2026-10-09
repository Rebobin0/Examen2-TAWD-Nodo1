<?php

namespace App\Http\Controllers;

use App\Services\Supabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransaccionController extends Controller
{
    private const POR_PAGINA = 25;

    public function __construct(private readonly Supabase $supabase)
    {
    }

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $pagina = max(1, (int) $request->query('pagina', 1));

        $resultado = $this->supabase->rpc('admin_historial', $this->parametros($filtros) + [
            'p_limite' => self::POR_PAGINA,
            'p_offset' => ($pagina - 1) * self::POR_PAGINA,
        ]);

        return view('transacciones.index', [
            'filtros' => $filtros,
            'nodos' => collect($this->supabase->rpc('admin_nodos')),
            'filas' => $resultado['filas'],
            'total' => $resultado['total'],
            'montoTotal' => $resultado['monto_total'],
            'pagina' => $pagina,
            'paginas' => max(1, (int) ceil($resultado['total'] / self::POR_PAGINA)),
        ]);
    }

    public function csv(Request $request)
    {
        $resultado = $this->supabase->rpc('admin_historial', $this->parametros($this->filtros($request)) + ['p_limite' => 5000]);
        $zona = config('app.timezone');

        return response()->streamDownload(function () use ($resultado, $zona) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel respete los acentos
            fputcsv($salida, ['ID', 'Fecha', 'Tipo', 'Monto', 'Cuenta origen', 'Cuenta destino', 'Nodo', 'Tipo de nodo']);

            foreach ($resultado['filas'] as $t) {
                fputcsv($salida, [
                    $t['id'],
                    Carbon::parse($t['timestamp'])->timezone($zona)->format('Y-m-d H:i:s'),
                    $t['tipo'],
                    number_format((float) $t['monto'], 2, '.', ''),
                    $t['cuenta_origen'],
                    $t['cuenta_destino'],
                    $t['nodo_nombre'],
                    $t['nodo_tipo'],
                ]);
            }

            fclose($salida);
        }, 'transacciones_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtros(Request $request): array
    {
        return $this->filtrosValidos($request, [
            'nodo' => ['nullable', 'uuid'],
            'tipo' => ['nullable', 'in:retiro,deposito,transferencia'],
            'cuenta' => ['nullable', 'string', 'max:20'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]) + ['nodo' => null, 'tipo' => null, 'cuenta' => null, 'desde' => null, 'hasta' => null];
    }

    /** Las fechas del formulario son días locales; "hasta" es inclusivo. */
    private function parametros(array $filtros): array
    {
        $zona = config('app.timezone');

        return [
            'p_nodo_id' => $filtros['nodo'],
            'p_tipo' => $filtros['tipo'],
            'p_cuenta' => $filtros['cuenta'],
            'p_desde' => $filtros['desde'] ? Carbon::parse($filtros['desde'], $zona)->startOfDay()->toIso8601String() : null,
            'p_hasta' => $filtros['hasta'] ? Carbon::parse($filtros['hasta'], $zona)->addDay()->startOfDay()->toIso8601String() : null,
        ];
    }
}
