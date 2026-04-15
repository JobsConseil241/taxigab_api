<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->unique()->nullable()->after('email');
            $table->enum('role', ['passenger', 'driver', 'admin'])->default('passenger')->after('password');
            $table->string('avatar_url')->nullable()->after('role');
            $table->string('language', 5)->default('fr')->after('avatar_url');
            $table->boolean('is_active')->default(true)->after('language');

            $table->index(['role', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'is_active']);
            $table->dropColumn(['phone', 'role', 'avatar_url', 'language', 'is_active']);
        });
    }
};
