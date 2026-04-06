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
        $defaultMatchesToWin = config('scratchgame.matchestowin', 3);
        $defaultMaxTries = config('scratchgame.maxtries', 10);
        Schema::table('campaigns', function (Blueprint $table) use ($defaultMatchesToWin, $defaultMaxTries) {
            $table->tinyInteger('matches_to_win')->default($defaultMatchesToWin)->after('ends_at');
            $table->unsignedInteger('max_tries')->default($defaultMaxTries)->after('matches_to_win');
        });

        Schema::table('games', function (Blueprint $table) use ($defaultMatchesToWin, $defaultMaxTries) {
            $table->tinyInteger('matches_to_win')->default($defaultMatchesToWin)->after('finished_at');
            $table->unsignedInteger('max_tries')->default($defaultMaxTries)->after('matches_to_win');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['matches_to_win', 'max_tries']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['board_size', 'matches_to_win', 'max_tries']);
        });
    }
};
