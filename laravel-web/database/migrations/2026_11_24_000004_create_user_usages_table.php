<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedBigInteger('bytes')->nullable()->after('size');
        });

        Schema::table('case_events', function (Blueprint $table) {
            $table->unsignedBigInteger('bytes')->nullable()->after('file_size');
        });

        Schema::create('user_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('storage_bytes')->default(0);
            $table->unsignedInteger('cases_count')->default(0);
            $table->timestamps();
        });

        foreach (DB::table('documents')->select('id', 'size')->get() as $document) {
            DB::table('documents')->where('id', $document->id)->update([
                'bytes' => $this->labelToBytes($document->size),
            ]);
        }

        foreach (DB::table('case_events')->select('id', 'file_size')->whereNotNull('file_path')->get() as $event) {
            DB::table('case_events')->where('id', $event->id)->update([
                'bytes' => $this->labelToBytes($event->file_size),
            ]);
        }

        $now = now();
        foreach (DB::table('users')->pluck('id') as $userId) {
            $caseIds = DB::table('cases')->where('user_id', $userId)->pluck('id');
            $storage = 0;
            if ($caseIds->isNotEmpty()) {
                $storage = (int) DB::table('documents')->whereIn('case_id', $caseIds)->sum('bytes')
                    + (int) DB::table('case_events')->whereIn('case_id', $caseIds)->sum('bytes');
            }

            DB::table('user_usages')->insert([
                'user_id' => $userId,
                'storage_bytes' => $storage,
                'cases_count' => $caseIds->count(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_usages');

        Schema::table('case_events', function (Blueprint $table) {
            $table->dropColumn('bytes');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('bytes');
        });
    }

    private function labelToBytes(?string $label): int
    {
        if ($label === null || ! preg_match('/^([\d.]+)\s*(B|KB|MB|GB)$/i', trim($label), $match)) {
            return 0;
        }

        $unit = match (strtoupper($match[2])) {
            'GB' => 1073741824,
            'MB' => 1048576,
            'KB' => 1024,
            default => 1,
        };

        return (int) round(((float) $match[1]) * $unit);
    }
};
