<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_formulas', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('icon', 50)->default('car');
            $table->unsignedInteger('base_fare');
            $table->unsignedInteger('per_km');
            $table->unsignedInteger('per_minute_wait')->default(0);
            $table->unsignedInteger('min_fare');
            $table->unsignedInteger('rounding_step')->default(100);
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_formulas');
    }
};
