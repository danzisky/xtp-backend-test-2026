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
        Schema::table('game_tiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('display_index')->after('tile_index')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_tiles', function (Blueprint $table) {
            $table->dropColumn('display_index');
        });
    }
};
