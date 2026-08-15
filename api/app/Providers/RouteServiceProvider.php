<?php

namespace App\Providers;

use App\Http\Controllers\ImportController;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->routes(
            function () {
                Route::get(
                    '/health',
                    function (Request $request) {
                        return response()->json(
                            [
                                'status' => 'ok',
                                'time' => microtime(true) - $request->attributes->get('request_start_time')
                            ]
                        );
                    }
                );

                Route::middleware('web')->group(
                    function () {
                        Route::get('/import', [ImportController::class, 'index'])->name('import.index');
                    }
                );

                Route::prefix('api/import')->middleware('api')->group(
                    function () {
                        Route::get('/config', [ImportController::class, 'config'])->name('import.config');
                        Route::post('/preview', [ImportController::class, 'preview'])->name('import.preview');
                        Route::post('/run', [ImportController::class, 'import'])->name('import.run');
                    }
                );
            }
        );
    }
}
