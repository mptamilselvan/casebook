<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('action');
            $table->timestamps();

            $table->unique(['group', 'action']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id']);
        });

        DB::table('users')->where('role', 'user')->update(['role' => 'member']);

        $account = DB::table('users')->where('email', 'admin@casebook.test')->where('role', 'admin')->first();
        if ($account) {
            DB::table('users')->where('id', $account->id)->update([
                'role' => 'superadmin',
                'name' => $account->name === 'admin' ? 'superadmin' : $account->name,
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->change();
        });
    }
};
