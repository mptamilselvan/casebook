<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hearing_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->date('hearing_on');
            $table->unsignedSmallInteger('days_before');
            $table->timestamp('sent_at');

            $table->unique(['case_id', 'hearing_on', 'days_before']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hearing_reminders');
    }
};
