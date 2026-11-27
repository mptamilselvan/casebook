<?php

namespace App\Providers;

use App\Support\RoleAccess;
use App\View\Composers\CasebookSidebarComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
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
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        View::composer('casebook.partials.sidebar', CasebookSidebarComposer::class);
        View::composer([
            'casebook.index',
            'casebook.partials.sidebar',
            'casebook.partials.main',
            'casebook.partials.modals',
        ], function ($view) {
            $view->with('can', RoleAccess::map(auth()->user()));
        });
    }
}
