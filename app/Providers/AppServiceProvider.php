<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        RateLimiter::for('ai', function (Request $request) {
            $tooMany = fn (string $message) => fn () => response()->json(['message' => $message], 429);

            return [
                Limit::perMinute(10)->by($request->ip())->response($tooMany('Muitas chamadas à IA em pouco tempo. Aguarde um minuto e tente de novo.')),
                Limit::perDay(300)->by('everyone')->response($tooMany('O limite diário de uso da IA nesta demonstração foi atingido. Tente de novo amanhã.')),
            ];
        });
    }
}
