@if (count($filas) === 0)
    <div class="vacio">No hay transacciones que mostrar.</div>
@else
    <div class="tabla-scroll">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th class="num">Monto</th>
                    <th>Cuenta origen</th>
                    <th>Cuenta destino</th>
                    <th>Nodo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $t)
                    <tr>
                        <td class="mono">{{ $t['id'] }}</td>
                        <td>@fecha($t['timestamp'])</td>
                        <td><span class="etq {{ $t['tipo'] }}">{{ ['retiro' => 'Retiro', 'deposito' => 'Depósito', 'transferencia' => 'Transferencia'][$t['tipo']] ?? $t['tipo'] }}</span></td>
                        <td class="num">@dinero($t['monto'])</td>
                        <td class="mono">{{ $t['cuenta_origen'] ?? '—' }}</td>
                        <td class="mono">{{ $t['cuenta_destino'] ?? '—' }}</td>
                        <td>{{ $t['nodo_nombre'] }} <span class="etq">{{ $t['nodo_tipo'] }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
