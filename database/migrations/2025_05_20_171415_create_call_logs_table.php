<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Quién hizo la llamada
            $table->string('destination_number'); // Número al que se llamó
            $table->string('twilio_call_sid')->unique()->nullable(); // SID de la llamada en Twilio
            $table->string('status')->default('initiated'); // Estado (queued, ringing, answered, completed, failed, etc.)
            $table->timestamp('start_time')->nullable(); // Hora de inicio real (según callback de Twilio)
            $table->timestamp('end_time')->nullable();   // Hora de fin (según callback de Twilio)
            $table->integer('duration')->nullable(); // Duración en segundos (según callback)
            $table->decimal('price', 8, 5)->nullable(); // Costo (según callback)
            $table->text('error_message')->nullable(); // Mensaje de error si falla
            $table->timestamps(); // created_at (cuando se inició en el backend), updated_at
            $table->foreignId('phone_number_id')->nullable()->constrained()->onDelete('cascade'); // Número desde el que se llamó
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
