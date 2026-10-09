@extends('layouts.app')

@section('titulo', 'Reportes')

@section('contenido')
    @php($r = $reporte['resumen'])

    <div class="encabezado">
        <div>
            <h1>Reporte de operaciones</h1>
            <p>Del {{ \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y') }}</p>
            <p class="solo-impresion">Banco Central · Generado el {{ $generado->format('d/m/Y H:i') }} por {{ session('sb.email') }}</p>
        </div>
        <div class="acciones no-imprimir">
            <a class="btn sec" href="{{ route('reportes.csv', ['desde' => $desde, 'hasta' => $hasta]) }}">Descargar CSV</a>
            <button class="btn" type="button" onclick="window.print()">Imprimir o guardar PDF</button>
        </div>
    </div>

    <form class="panel no-imprimir" method="GET" action="{{ route('reportes.index') }}">
        <div class="campos">
            <div class="campo">
                <label for="desde">Desde</label>
                <input id="desde" type="date" name="desde" value="{{ $desde }}" required>
            </div>
            <div class="campo">
                <label for="hasta">Hasta</label>
                <input id="hasta" type="date" name="hasta" value="{{ $hasta }}" required>
            </div>
        </div>
        <div class="pie-form">
            <button class="btn" type="submit">Generar reporte</button>
        </div>
    </form>

    <div class="tarjetas">
        <div class="tarjeta">
            <div class="etiqueta">Operaciones</div>
            <div class="valor">{{ $r['operaciones'] }}</div>
            <div class="nota">@dinero($r['monto_total']) movidos</div>
        </div>
        <div class="tarjeta">
            <div class="etiqueta">Retiros</div>
            <div class="valor">@dinero($r['monto_retiros'])</div>
            <div class="nota">{{ $r['retiros'] }} {{ $r['retiros'] == 1 ? 'operación' : 'operaciones' }}</div>
        </div>
        <div class="tarjeta">
            <div class="etiqueta">Depósitos</div>
            <div class="valor">@dinero($r['monto_depositos'])</div>
            <div class="nota">{{ $r['depositos'] }} {{ $r['depositos'] == 1 ? 'operación' : 'operaciones' }}</div>
        </div>
        <div class="tarjeta">
            <div class="etiqueta">Transferencias</div>
            <div class="valor">@dinero($r['monto_transferencias'])</div>
            <div class="nota">{{ $r['transferencias'] }} {{ $r['transferencias'] == 1 ? 'operación' : 'operaciones' }}</div>
        </div>
    </div>

    <div class="panel sin-relleno">
        <h2>Operaciones por sucursal y cajero</h2>
        @if (count($reporte['por_nodo']) === 0)
            <div class="vacio">Aún no hay sucursales ni cajeros.</div>
        @else
            <div class="tabla-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Nodo</th>
                            <th>Responsable</th>
                            <th class="num">Efectivo actual</th>
                            <th class="num">Operaciones</th>
                            <th class="num">Retiros</th>
                            <th class="num">Depósitos</th>
                            <th class="num">Transferencias</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reporte['por_nodo'] as $n)
                            <tr>
                                <td>{{ $n['nombre'] }} <span class="etq">{{ $n['tipo'] }}</span></td>
                                <td>{{ $n['responsable'] ?? 'Sin asignar' }}</td>
                                <td class="num">@dinero($n['efectivo_disponible'])</td>
                                <td class="num">{{ $n['operaciones'] }}</td>
                                <td class="num">@dinero($n['monto_retiros']) ({{ $n['retiros'] }})</td>
                                <td class="num">@dinero($n['monto_depositos']) ({{ $n['depositos'] }})</td>
                                <td class="num">@dinero($n['monto_transferencias']) ({{ $n['transferencias'] }})</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2">Total del periodo</td>
                            <td class="num">@dinero($reporte['sistema']['efectivo_total'])</td>
                            <td class="num">{{ $r['operaciones'] }}</td>
                            <td class="num">@dinero($r['monto_retiros']) ({{ $r['retiros'] }})</td>
                            <td class="num">@dinero($r['monto_depositos']) ({{ $r['depositos'] }})</td>
                            <td class="num">@dinero($r['monto_transferencias']) ({{ $r['transferencias'] }})</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

    <div class="panel sin-relleno">
        <h2>Operaciones por día</h2>
        @if (count($reporte['por_dia']) === 0)
            <div class="vacio">No hubo operaciones en el periodo.</div>
        @else
            <div class="tabla-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Día</th>
                            <th class="num">Operaciones</th>
                            <th class="num">Retiros</th>
                            <th class="num">Depósitos</th>
                            <th class="num">Transferencias</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reporte['por_dia'] as $d)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($d['dia'])->format('d/m/Y') }}</td>
                                <td class="num">{{ $d['operaciones'] }}</td>
                                <td class="num">@dinero($d['monto_retiros'])</td>
                                <td class="num">@dinero($d['monto_depositos'])</td>
                                <td class="num">@dinero($d['monto_transferencias'])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
