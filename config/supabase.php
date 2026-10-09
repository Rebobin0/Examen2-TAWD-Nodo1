<?php

return [
    // URL del proyecto, sin diagonal final: https://xxxx.supabase.co
    'url' => rtrim((string) env('SUPABASE_URL', ''), '/'),

    // Clave publica (anon / publishable). Los permisos reales los da el JWT del administrador.
    'anon_key' => (string) env('SUPABASE_ANON_KEY', ''),
];
