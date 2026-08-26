<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('target_ros', function (Blueprint $table) {
            $table->id();
            $table->integer('period_year');
            $table->integer('period_month');
            $table->string('branch_id', 50);
            $table->string('salesman_code', 50);
            $table->string('visit_type', 10)->default('F2'); // F2 atau F4
            $table->integer('target_ro')->default(0);
            $table->string('region_code', 50)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->unique(['period_year', 'period_month', 'branch_id', 'salesman_code'], 'target_ros_unique_period_branch_salesman');
            $table->index(['period_year', 'period_month']);
            $table->index(['branch_id', 'salesman_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('target_ros');
    }
};
