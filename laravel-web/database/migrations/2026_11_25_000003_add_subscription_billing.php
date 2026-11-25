<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $priceIndexes = collect(Schema::getIndexes('prices'))->pluck('name');

        if (! Schema::hasColumn('prices', 'interval')) {
            Schema::table('prices', function (Blueprint $table) {
                $table->string('interval', 16)->default('monthly');
                $table->string('razorpay_plan_id')->nullable();
            });
        }

        if (! $priceIndexes->contains('prices_plan_id_currency_interval_unique')) {
            Schema::table('prices', function (Blueprint $table) {
                $table->unique(['plan_id', 'currency', 'interval']);
            });
        }

        if ($priceIndexes->contains('prices_plan_id_currency_unique')) {
            Schema::table('prices', function (Blueprint $table) {
                $table->dropUnique(['plan_id', 'currency']);
            });
        }

        if (! Schema::hasColumn('transactions', 'razorpay_subscription_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('interval', 16)->nullable();
                $table->string('razorpay_subscription_id')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('users', 'billing_interval')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('billing_interval', 16)->nullable();
                $table->string('razorpay_subscription_id')->nullable();
                $table->string('subscription_status', 32)->nullable();
                $table->date('plan_renews_on')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'billing_interval',
                'razorpay_subscription_id',
                'subscription_status',
                'plan_renews_on',
            ]);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['razorpay_subscription_id']);
            $table->dropColumn(['interval', 'razorpay_subscription_id']);
        });

        Schema::table('prices', function (Blueprint $table) {
            $table->dropUnique(['plan_id', 'currency', 'interval']);
            $table->unique(['plan_id', 'currency']);
            $table->dropColumn(['interval', 'razorpay_plan_id']);
        });
    }
};
