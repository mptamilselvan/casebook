<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtCase;
use App\Models\District;
use App\Models\State;
use App\Models\User;
use App\Support\Casebook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CasebookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
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

    public function test_a_typed_court_is_added_for_that_district(): void
    {
        $state = State::query()->create(['name' => 'Tamil Nadu']);
        $district = District::query()->create([
            'state_id' => $state->id,
            'name' => 'Chennai',
        ]);
        Court::query()->create([
            'state_id' => $state->id,
            'name' => 'Madras High Court',
        ]);

        $payload = [
            'state' => 'Tamil Nadu',
            'district' => 'Chennai',
            'case_number' => 'O.S. 11',
            'case_year' => '2026',
            'party1_role' => 'Plaintiff',
            'party2_role' => 'Defendant',
            'petitioners' => ['Ramesh Kumar'],
            'respondents' => ['State of TN'],
        ];

        $this->post(route('cases.store'), [...$payload, 'court' => 'City Civil Court'])
            ->assertRedirect();

        $this->assertDatabaseHas('courts', [
            'state_id' => $state->id,
            'district_id' => $district->id,
            'name' => 'City Civil Court',
        ]);

        $this->post(route('cases.store'), [...$payload, 'court' => 'city civil court'])
            ->assertRedirect();

        $this->assertSame(1, Court::query()->where('district_id', $district->id)->where('name', 'City Civil Court')->count());

        $this->post(route('cases.store'), [...$payload, 'court' => 'Madras High Court'])
            ->assertRedirect();

        $this->assertSame(1, Court::query()->where('name', 'Madras High Court')->count());
    }

    public function test_web_and_api_uploads_are_stored_on_the_hugging_face_disk(): void
    {
        Storage::fake(Casebook::FILE_DISK);

        $case = CourtCase::query()->create([
            'title' => 'O.S. 1/2026',
            'court' => 'Madras High Court',
        ]);

        $this->post(route('documents.store', $case), [
            'folder' => 'Pleadings',
            'name' => 'Plaint',
            'file' => UploadedFile::fake()->create('plaint.pdf', 12, 'application/pdf'),
        ])->assertRedirect();

        $document = $case->documents()->first();
        $this->assertNotNull($document?->file_path);
        Storage::disk(Casebook::FILE_DISK)->assertExists($document->file_path);
        $this->get(route('documents.file', $document))->assertOk();

        $this->post(route('events.store', $case), [
            'event_date' => '2026-11-23',
            'title' => 'Hearing',
            'file' => UploadedFile::fake()->image('order.jpg'),
        ])->assertRedirect();

        $event = $case->events()->first();
        $this->assertNotNull($event?->file_path);
        Storage::disk(Casebook::FILE_DISK)->assertExists($event->file_path);

        Sanctum::actingAs($this->user);

        $this->post('/api/cases/'.$case->id.'/documents', [
            'folder' => 'Exhibits',
            'name' => 'Photo',
            'file' => UploadedFile::fake()->image('exhibit.png'),
        ])->assertCreated();

        $apiDocument = $case->documents()->where('folder', 'Exhibits')->first();
        $this->assertNotNull($apiDocument?->file_path);
        Storage::disk(Casebook::FILE_DISK)->assertExists($apiDocument->file_path);
        $this->get('/api/documents/'.$apiDocument->id.'/file')->assertOk();
    }

    public function test_saving_a_party_name_stores_it(): void
    {
        $case = CourtCase::query()->create([
            'user_id' => $this->user->id,
            'title' => 'O.S. 2/2026',
            'court' => 'Madras High Court',
            'party1_role' => 'Petitioner',
            'party2_role' => 'Respondent',
        ]);

        $this->post(route('parties.sync', $case), [
            'party1_role' => 'Petitioner',
            'party2_role' => 'Respondent',
            'side1' => ['Lakshmi'],
            'side2' => [''],
            'tab' => 'Parties',
            'case' => $case->id,
        ])->assertRedirect(route('cases.index', [
            'filter' => 'All Cases',
            'tab' => 'Parties',
            'case' => $case->id,
        ]));

        $this->assertSame('Lakshmi', $case->caseParties()->where('side', 1)->value('name'));
    }

    public function test_priority_is_saved_and_delete_stays_on_the_case(): void
    {
        $case = CourtCase::query()->create([
            'user_id' => $this->user->id,
            'title' => 'O.S. 3/2026',
            'court' => 'Madras High Court',
        ]);

        $this->assertSame('medium', $case->fresh()->priority);

        $this->post(route('cases.priority', $case), [
            'priority' => 'high',
            'case' => $case->id,
        ])->assertRedirect();

        $this->assertSame('high', $case->fresh()->priority);

        $this->get('/?case='.$case->id)
            ->assertOk()
            ->assertSee('Delete this case?')
            ->assertDontSee('Delete this case and its files?');

        Sanctum::actingAs($this->user);
        $this->putJson('/api/cases/'.$case->id.'/priority', ['priority' => 'low'])
            ->assertOk()
            ->assertJsonPath('priority', 'low');
    }

    public function test_starred_cases_and_future_hearings_open_on_their_lists(): void
    {
        $past = CourtCase::query()->create([
            'user_id' => $this->user->id,
            'title' => 'Past hearing',
            'court' => 'Madras High Court',
            'next_hearing' => today()->subDay()->toDateString(),
        ]);
        $future = CourtCase::query()->create([
            'user_id' => $this->user->id,
            'title' => 'Future hearing',
            'court' => 'Madras High Court',
            'next_hearing' => today()->addDays(5)->toDateString(),
        ]);
        $starred = CourtCase::query()->create([
            'user_id' => $this->user->id,
            'title' => 'Starred hearing',
            'court' => 'Madras High Court',
            'is_pinned' => true,
            'next_hearing' => today()->subDays(3)->toDateString(),
        ]);

        $this->post(route('cases.pin', $past), ['case' => $past->id])->assertRedirect();
        $this->assertTrue($past->fresh()->is_pinned);

        $this->get(route('cases.index', ['filter' => 'Starred', 'case' => $future->id]))
            ->assertOk()
            ->assertSee('Starred hearing')
            ->assertSee('Past hearing')
            ->assertDontSee('Future hearing');

        $this->get(route('cases.index', ['filter' => 'Upcoming', 'case' => $past->id]))
            ->assertOk()
            ->assertSee('Future hearing')
            ->assertDontSee('Past hearing')
            ->assertDontSee('Starred hearing');

        $this->assertTrue($starred->fresh()->is_pinned);
    }
}
