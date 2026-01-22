<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("discogs_analyses", function (Blueprint $table) {
            $table->string("thumb", 500)->nullable()->after("cover_image");
        });
    }

    public function down(): void
    {
        Schema::table("discogs_analyses", function (Blueprint $table) {
            $table->dropColumn("thumb");
        });
    }
};
