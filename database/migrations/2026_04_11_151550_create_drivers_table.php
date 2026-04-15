<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('license_number', 64)->unique();
            $table->enum('status', ['pending', 'approved', 'suspended'])->default('pending');

            $table->boolean('is_online')->default(false);
            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
            $table->unsignedInteger('current_heading')->nullable(); // 0-359 degrees

            $table->decimal('rating_avg', 3, 2)->default(5.00);
            $table->unsignedInteger('total_rides')->default(0);

            $table->timestamp('last_location_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_online']);
            $table->index(['current_lat', 'current_lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
