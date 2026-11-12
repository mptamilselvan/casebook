<?php

namespace Tests\Feature;

use App\Models\CourtCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasebookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_casebook_page_loads(): void
    {
        $this->get('/')->assertOk()->assertSee('Case Book')->assertSee('New Case');
    }

    public function test_advocate_can_create_a_case_with_parties(): void
    {
        $response = $this->post(route('cases.store'), [
            'state' => 'Tamil Nadu',
            'district' => 'Chennai',
            'court' => 'Madras High Court',
            'case_number' => 'O.S. 10',
            'case_year' => '2026',
            'party1_role' => 'Plaintiff',
            'party2_role' => 'Defendant',
            'petitioners' => ['Ramesh Kumar'],
            'respondents' => ['State of TN'],
            'filter' => 'All Cases',
            'tab' => 'Timeline',
        ]);

        $case = CourtCase::query()->first();

        $response->assertRedirect(route('cases.index', [
            'filter' => 'All Cases',
            'tab' => 'Timeline',
            'case' => $case->id,
        ]));

        $this->assertDatabaseHas('cases', [
            'title' => 'O.S. 10/2026',
            'court' => 'Madras High Court',
            'parties_label' => 'Ramesh Kumar vs. State of TN',
            'state' => 'Tamil Nadu',
        ]);
        $this->assertDatabaseCount('case_parties', 2);
    }
}
