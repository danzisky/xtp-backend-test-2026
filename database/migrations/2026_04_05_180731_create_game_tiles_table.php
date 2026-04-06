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
        Schema::create('game_tiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('tile_index');
            $table->foreignId('prize_id')->constrained()->cascadeOnDelete();
            $table->string('tile_image');
            $table->timestamp('revealed_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'tile_index'], 'game_tiles_unique_position');
            $table->index(['game_id', 'revealed_at'], 'game_tiles_reveal_lookup');
            $table->index(['game_id', 'prize_id'], 'game_tiles_prize_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_tiles');
    }
};
