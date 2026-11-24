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
}
