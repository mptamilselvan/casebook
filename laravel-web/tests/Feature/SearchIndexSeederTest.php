<?php

namespace Tests\Feature;

use Database\Seeders\SearchIndexSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SearchIndexSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_and_sort_indexes_are_created_once(): void
    {
        $this->seed(SearchIndexSeeder::class);

        $this->assertTrue(Schema::hasIndex('cases', 'cases_user_id_is_pinned_updated_at_index'));
        $this->assertTrue(Schema::hasIndex('cases', 'cases_user_id_next_hearing_index'));
        $this->assertTrue(Schema::hasIndex('cases', 'cases_user_id_title_index'));
        $this->assertTrue(Schema::hasIndex('case_parties', 'case_parties_case_id_side_sort_order_index'));
        $this->assertTrue(Schema::hasIndex('documents', 'documents_case_id_created_at_index'));
        $this->assertTrue(Schema::hasIndex('templates', 'templates_title_index'));
        $this->assertTrue(Schema::hasIndex('users', 'users_owner_id_email_index'));
        $this->assertTrue(Schema::hasIndex('users', 'users_created_at_index'));
        $this->assertTrue(Schema::hasIndex('users', 'users_razorpay_subscription_id_index'));
        $this->assertTrue(Schema::hasIndex('transactions', 'transactions_razorpay_payment_id_index'));
        $this->assertTrue(Schema::hasIndex('transactions', 'transactions_razorpay_subscription_id_status_index'));
        $this->assertTrue(Schema::hasIndex('plans', 'plans_sort_order_index'));
        $this->assertTrue(Schema::hasIndex('courts', 'courts_state_id_district_id_name_index'));

        $this->seed(SearchIndexSeeder::class);

        $this->assertTrue(Schema::hasIndex('cases', ['user_id', 'is_pinned', 'updated_at']));
    }
}
