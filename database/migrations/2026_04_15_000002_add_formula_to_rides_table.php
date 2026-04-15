<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->foreignId('pricing_formula_id')
                ->nullable()
                ->after('vehicle_id')
                ->constrained('pricing_formulas')
                ->nullOnDelete();
            $table->string('formula_name')->nullable()->after('pricing_formula_id');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pricing_formula_id');
            $table->dropColumn('formula_name');
        });
    }
};
