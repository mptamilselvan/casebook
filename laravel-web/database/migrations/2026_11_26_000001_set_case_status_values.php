<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $allowed = [
            'active' => 'ongoing',
            'ongoing' => 'ongoing',
            'pending' => 'pending',
            'stayed' => 'stayed',
            'disposed' => 'disposed',
            'dismissed' => 'dismissed',
            'withdrawn' => 'withdrawn',
            'settled' => 'settled',
        ];

        foreach (DB::table('cases')->select('id', 'status')->get() as $case) {
            $next = $allowed[strtolower(trim((string) $case->status))] ?? 'pending';
            if ($next !== $case->status) {
                DB::table('cases')->where('id', $case->id)->update(['status' => $next]);
            }
        }

        Schema::table('cases', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->string('status')->default('Active')->change();
        });
    }
};
