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
        Schema::create('daily_prize_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prize_id')->constrained()->cascadeOnDelete();
            $table->date('counter_date'); // in campaign timezone
            $table->unsignedInteger('daily_limit')->default(0);
            $table->unsignedInteger('reserved_count')->default(0);
            $table->unsignedInteger('awarded_count')->default(0);
            $table->timestamps();

            $table->unique(['prize_id', 'counter_date'], 'prize_daily_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_prize_counters');
    }
};
