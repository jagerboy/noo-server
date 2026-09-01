<?php

namespace App\Providers;

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
