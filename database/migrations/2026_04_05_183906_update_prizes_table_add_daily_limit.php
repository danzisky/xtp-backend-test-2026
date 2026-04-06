<?php

use App\Enums\PrizeSegment;
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
        Schema::table('prizes', function (Blueprint $table) {
            $table->unsignedInteger('daily_limit')->default(0)->after('weight');
            $table->enum('segment', array_column(PrizeSegment::cases(), 'value'))->change();
            $table->index(['campaign_id', 'segment'], 'prizes_campaign_segment_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prizes', function (Blueprint $table) {
            $table->dropIndex('prizes_campaign_segment_idx');
            $table->dropColumn('daily_limit');
        });
    }
};
