<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('role')->constrained('users')->nullOnDelete();
        });

        Schema::create('owner_permission_sets', function (Blueprint $table) {
            $table->foreignId('owner_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('owner_role_permission', function (Blueprint $table) {
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();

            $table->primary(['owner_id', 'role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_role_permission');
        Schema::dropIfExists('owner_permission_sets');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
