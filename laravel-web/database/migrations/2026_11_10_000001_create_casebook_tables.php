<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('parties_label')->default('');
            $table->string('state')->nullable();
            $table->string('district')->nullable();
            $table->string('court');
            $table->string('case_number')->nullable();
            $table->string('case_year', 10)->nullable();
            $table->string('type')->default('New Filing');
            $table->string('status')->default('Active');
            $table->date('next_hearing')->nullable();
            $table->string('judge')->nullable();
            $table->string('stage')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->text('tasks')->nullable();
            $table->string('party1_role')->default('Petitioner');
            $table->string('party2_role')->default('Respondent');
            $table->timestamps();

            $table->index('next_hearing');
            $table->index('is_pinned');
            $table->index('status');
        });

        Schema::create('case_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->unsignedTinyInteger('side');
            $table->string('name')->default('');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['case_id', 'side']);
        });

        Schema::create('case_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->date('event_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_size')->nullable();
            $table->string('file_path')->nullable();
            $table->boolean('is_hearing')->default(false);
            $table->timestamps();

            $table->index(['case_id', 'event_date']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('name');
            $table->string('folder');
            $table->string('size')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->index(['case_id', 'folder']);
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('case_events');
        Schema::dropIfExists('case_parties');
        Schema::dropIfExists('cases');
    }
};
