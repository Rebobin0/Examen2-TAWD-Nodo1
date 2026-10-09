<?php

namespace App\Providers;

use App\Services\Supabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Supabase::class, fn () => new Supabase(
            config('supabase.url'),
            config('supabase.anon_key'),
        ));
    }

    public function boot(): void
    {
        // @dinero(1234.5) -> $1,234.50
        Blade::directive('dinero', fn ($valor) => "<?php echo '$'.number_format((float) ($valor), 2); ?>");

        // @fecha('2026-10-09T17:30:00+00:00') -> 09/10/2026 11:30 (hora local)
        Blade::directive('fecha', fn ($valor) => "<?php echo \\Illuminate\\Support\\Carbon::parse($valor)->timezone(config('app.timezone'))->format('d/m/Y H:i'); ?>");
    }
}
