<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SearchIndexSeeder extends Seeder
{
    public function run(): void
    {
        $indexes = [
            // Case list: owner scope, then starred first and recently updated.
            ['cases', 'cases_user_id_is_pinned_updated_at_index', ['user_id', 'is_pinned', 'updated_at']],
            // Today's hearings and upcoming hearings for one account.
            ['cases', 'cases_user_id_next_hearing_index', ['user_id', 'next_hearing']],
            // Case picker on People, sorted by title within an account.
            ['cases', 'cases_user_id_title_index', ['user_id', 'title']],
            // Parties on a case, ordered by side then the saved order.
            ['case_parties', 'case_parties_case_id_side_sort_order_index', ['case_id', 'side', 'sort_order']],
            // Documents on a case, newest first.
            ['documents', 'documents_case_id_created_at_index', ['case_id', 'created_at']],
            // Template list sorted by title.
            ['templates', 'templates_title_index', ['title']],
            // People list for an account, sorted by email.
            ['users', 'users_owner_id_email_index', ['owner_id', 'email']],
            // Admin user list sorted by when the account was created.
            ['users', 'users_created_at_index', ['created_at']],
            // Subscription webhooks look up a user by Razorpay subscription id.
            ['users', 'users_razorpay_subscription_id_index', ['razorpay_subscription_id']],
            // Payment webhooks look up a charge by Razorpay payment id.
            ['transactions', 'transactions_razorpay_payment_id_index', ['razorpay_payment_id']],
            // Pending and paid rows for one subscription.
            ['transactions', 'transactions_razorpay_subscription_id_status_index', ['razorpay_subscription_id', 'status']],
            // Plan catalog sorted by display order.
            ['plans', 'plans_sort_order_index', ['sort_order']],
            // Courts listed by name inside a state and district.
            ['courts', 'courts_state_id_district_id_name_index', ['state_id', 'district_id', 'name']],
        ];

        foreach ($indexes as [$table, $name, $columns]) {
            $this->addIndex($table, $name, $columns);
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndex(string $table, string $name, array $columns): void
    {
        if (! Schema::hasTable($table) || Schema::hasIndex($table, $name) || Schema::hasIndex($table, $columns)) {
            return;
        }

        $grammar = DB::connection()->getQueryGrammar();
        $wrappedColumns = implode(', ', array_map(
            fn (string $column) => $grammar->wrap($column),
            $columns,
        ));

        DB::statement(sprintf(
            'CREATE INDEX %s ON %s (%s)',
            $grammar->wrap($name),
            $grammar->wrapTable($table),
            $wrappedColumns,
        ));
    }
}
