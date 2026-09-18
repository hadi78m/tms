<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('national_code', 10)->unique();
            $table->string('username', 10)->unique();
            $table->string('name', 255);
            $table->string('mobile', 11);
            $table->string('email', 255)->nullable();
            $table->string('password', 255);
            $table->foreignId('contractor_id')->nullable()->constrained('synced_contractors')->onDelete('restrict');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz();
        });

        // Add CHECK constraints via raw SQL
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_national_code CHECK (national_code ~ '^[0-9]{10}$')");
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_mobile CHECK (mobile ~ '^[0-9]{11}$')");

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
