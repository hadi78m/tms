<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->text('body');
            $table->foreignId('parent_comment_id')->nullable()->constrained('comments')->onDelete('set null');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletesTz();

            $table->index('task_id');
            $table->index('user_id');
            $table->index('parent_comment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
