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
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('quantity_total');
            $table->unsignedInteger('quantity_available');
            $table->dateTime('sale_starts_at')->nullable();
            $table->dateTime('sale_ends_at')->nullable();
            $table->timestamps();

            $table->index('event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};