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
        Schema::create('sage_chat_histories', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index(); // Identitas Sesi (User ID / Browser Session)
            $table->string('role'); // 'user' atau 'model'
            $table->text('content'); // Isi pesan
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sage_chat_histories');
    }
};
