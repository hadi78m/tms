<?php

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
        Schema::table('documents', function (Blueprint $table) {
            $table->string('file_hash', 64)->nullable()->after('checksum');
            $table->timestamp('claimed_at')->nullable()->after('file_hash');
            $table->timestamp('verified_at')->nullable()->after('claimed_at');
            $table->timestamp('recorded_at')->nullable()->after('verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['file_hash', 'claimed_at', 'verified_at', 'recorded_at']);
        });
    }
};
