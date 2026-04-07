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
        // Covers game session lookup queries that filter by campaign_id + account + segment + finished_at (for active sessions)
        Schema::table('games', function (Blueprint $table) {
            $table->index(
                ['campaign_id', 'account', 'segment', 'finished_at'],
                'games_session_lookup'
            );
        });

        // Covers prize availability queries that filter by campaign_id + segment
        Schema::table('prizes', function (Blueprint $table) {
            $table->index(['campaign_id', 'segment'], 'prizes_campaign_segment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prizes', function (Blueprint $table) {
            $table->dropIndex('prizes_campaign_segment');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex('games_session_lookup');
        });
    }
};
