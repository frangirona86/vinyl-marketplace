<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("discogs_analyses", function (Blueprint $table) {
            $table->json("styles")->nullable()->after("genres");
        });
    }

    public function down(): void
    {
        Schema::table("discogs_analyses", function (Blueprint $table) {
            $table->dropColumn("styles");
        });
    }
};
