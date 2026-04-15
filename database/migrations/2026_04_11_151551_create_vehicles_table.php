<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();

            $table->string('brand', 64);
            $table->string('model', 64);
            $table->unsignedSmallInteger('year');
            $table->string('color', 32);
            $table->string('plate_number', 32)->unique();
            $table->enum('category', ['standard', 'comfort'])->default('standard');
            $table->string('photo_url')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['driver_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
