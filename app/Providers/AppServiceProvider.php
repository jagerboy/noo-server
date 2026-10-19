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
        if (!$this->app->runningInConsole()) {
            $host = request()->getHost();
            $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_starts_with($host, '192.168.') || str_starts_with($host, '10.');

            if ($isLocalHost) {
                URL::forceScheme('http');
            } else {
                URL::forceScheme('https');
                URL::forceRootUrl('https://' . request()->getHttpHost());
                config(['app.url' => 'https://' . request()->getHttpHost()]);
                config(['app.asset_url' => 'https://' . request()->getHttpHost()]);
            }
        }

        Vite::prefetch(concurrency: 3);

        try {
            \Illuminate\Support\Facades\DB::statement("DELETE FROM master_branches WHERE (branch_id IS NULL OR branch_id = '') AND region_code = 'SUMATERA'");
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
