<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->where('group', 'MEMBERS')->delete();
    }

    public function down(): void
    {
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            DB::table('permissions')->updateOrInsert(
                ['group' => 'MEMBERS', 'action' => $action],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }
};
