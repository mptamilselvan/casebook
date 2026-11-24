<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('space_limit');
            $table->unsignedInteger('cases_limit')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('INR');
            $table->timestamps();
            $table->unique(['plan_id', 'currency']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('price_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_name')->nullable();
            $table->date('plan_created_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropConstrainedForeignId('price_id');
            $table->dropColumn(['plan_name', 'plan_created_date']);
        });

        Schema::dropIfExists('prices');
        Schema::dropIfExists('plans');
    }
};
