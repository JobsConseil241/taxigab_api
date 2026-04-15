<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->id();

            $table->foreignId('passenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();

            $table->enum('status', [
                'requested',
                'accepted',
                'arriving',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('requested');

            // Pickup
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->string('pickup_address')->nullable();

            // Dropoff
            $table->decimal('dropoff_lat', 10, 7);
            $table->decimal('dropoff_lng', 10, 7);
            $table->string('dropoff_address')->nullable();

            // Metrics
            $table->decimal('distance_km', 6, 2)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();

            // Pricing (FCFA, integers)
            $table->unsignedInteger('price_estimated');
            $table->unsignedInteger('price_final')->nullable();
            $table->string('currency', 3)->default('XAF');

            // Payment
            $table->enum('payment_method', ['cash', 'mobile_money'])->default('cash');
            $table->enum('payment_status', ['pending', 'paid'])->default('pending');

            // Feedback
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('review')->nullable();

            // Timeline
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('arriving_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index(['driver_id', 'status']);
            $table->index(['passenger_id', 'status']);
            $table->index('requested_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
