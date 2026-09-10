<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
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
        if (request()->getHost() !== 'localhost' && request()->getHost() !== '127.0.0.1') {
            URL::forceScheme('https');
            URL::forceRootUrl('https://' . request()->getHost());
            config(['app.url' => 'https://' . request()->getHost()]);
            config(['app.asset_url' => 'https://' . request()->getHost()]);
        }

        Vite::prefetch(concurrency: 3);

        try {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE noo_submissions ADD COLUMN IF NOT EXISTS is_ktp_revised boolean DEFAULT false');
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE noo_submissions ADD COLUMN IF NOT EXISTS ktp_revised_at timestamp null');
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE noo_submissions ADD COLUMN IF NOT EXISTS ktp_revised_by varchar(100) null');
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE noo_submissions ADD COLUMN IF NOT EXISTS ktp_unlocked_at timestamp null');
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE noo_submissions ADD COLUMN IF NOT EXISTS ktp_unlocked_by varchar(100) null');
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE noo_submissions ALTER COLUMN flags TYPE text');
        } catch (\Throwable $e) {
            // Silently ignore
        }
    }
}
