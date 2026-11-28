<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['district_id', 'name']);
        });

        Schema::table('courts', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('district_id')->constrained()->nullOnDelete();
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->string('area')->nullable()->after('district');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('area');
        });

        Schema::table('courts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
        });

        Schema::dropIfExists('areas');
    }
};
