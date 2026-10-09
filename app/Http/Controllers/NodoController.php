<?php

namespace App\Http\Controllers;

use App\Services\Supabase;
use Illuminate\Http\Request;

class NodoController extends Controller
{
    public function __construct(private readonly Supabase $supabase)
    {
    }

    public function index()
    {
        $nodos = collect($this->supabase->rpc('admin_nodos'));

        return view('nodos.index', [
            'sucursales' => $nodos->where('tipo', 'sucursal')->values(),
            'cajeros' => $nodos->where('tipo', 'cajero')->values(),
        ]);
    }

    public function create(Request $request)
    {
        return view('nodos.create', ['tipo' => $request->query('tipo') === 'cajero' ? 'cajero' : 'sucursal']);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'tipo' => ['required', 'in:sucursal,cajero'],
            'nombre' => ['required', 'string', 'max:80'],
            'responsable' => ['nullable', 'string', 'max:80'],
            'efectivo' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        $nodo = $this->supabase->rpc('admin_crear_nodo', [
            'p_tipo' => $datos['tipo'],
            'p_nombre' => $datos['nombre'],
            'p_responsable' => $datos['responsable'] ?? null,
            'p_efectivo_inicial' => $datos['efectivo'],
        ]);

        // El API Key solo existe en claro en esta respuesta: se muestra una vez
        return redirect()->route('nodos.index')->with('api_key', [
            'nombre' => $nodo['nombre'],
            'tipo' => $nodo['tipo'],
            'key' => $nodo['api_key'],
            'nueva' => true,
        ]);
    }

    public function edit(string $id)
    {
        return view('nodos.edit', ['nodo' => $this->buscar($id)]);
    }

    public function update(Request $request, string $id)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'responsable' => ['nullable', 'string', 'max:80'],
            'efectivo' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'estado' => ['required', 'in:activo,inactivo'],
        ]);

        $this->supabase->rpc('admin_actualizar_nodo', [
            'p_nodo_id' => $id,
            'p_nombre' => $datos['nombre'],
            'p_responsable' => $datos['responsable'] ?? '',
            'p_efectivo_disponible' => $datos['efectivo'],
            'p_estado' => $datos['estado'],
        ]);

        return redirect()->route('nodos.index')->with('ok', "Se actualizó «{$datos['nombre']}».");
    }

    public function regenerarApiKey(string $id)
    {
        $nodo = $this->buscar($id);
        $resultado = $this->supabase->rpc('admin_regenerar_api_key', ['p_nodo_id' => $id]);

        return redirect()->route('nodos.index')->with('api_key', [
            'nombre' => $nodo['nombre'],
            'tipo' => $nodo['tipo'],
            'key' => $resultado['api_key'],
            'nueva' => false,
        ]);
    }

    public function cambiarEstado(Request $request, string $id)
    {
        $datos = $request->validate(['estado' => ['required', 'in:activo,inactivo']]);

        $nodo = $this->supabase->rpc('admin_cambiar_estado', ['p_nodo_id' => $id, 'p_estado' => $datos['estado']]);

        return redirect()->route('nodos.index')->with('ok', "«{$nodo['nombre']}» ahora está {$nodo['estado']}.");
    }

    private function buscar(string $id): array
    {
        $nodo = collect($this->supabase->rpc('admin_nodos'))->firstWhere('id', $id);

        abort_if($nodo === null, 404, 'La sucursal o cajero no existe.');

        return $nodo;
    }
}
