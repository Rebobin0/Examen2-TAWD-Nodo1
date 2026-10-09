<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;

abstract class Controller
{
    /**
     * Valida filtros que llegan por GET. Si son inválidos NO redirige (en una
     * página de consulta eso provoca un bucle): muestra el error y los ignora.
     */
    protected function filtrosValidos(Request $request, array $reglas): array
    {
        $validador = Validator::make($request->query(), $reglas);

        if ($validador->fails()) {
            view()->share('errors', (new ViewErrorBag)->put('default', $validador->errors()));

            return [];
        }

        return $validador->validated();
    }
}
