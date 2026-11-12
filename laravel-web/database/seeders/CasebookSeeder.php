<?php

namespace Database\Seeders;

use App\Models\CourtCase;
use App\Models\Template;
use Illuminate\Database\Seeder;

class CasebookSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(20261110);

        Template::query()->create([
            'title' => 'Main Petition Format',
            'content' => <<<'TEXT'
IN THE HIGH COURT OF JUDICATURE AT MADRAS

[Case Number]

Between:
[Petitioner Name] ... Petitioner

And

[Respondent Name] ... Respondent

May it please your Lordship...
TEXT,
        ]);

        Template::query()->create([
            'title' => 'Counter Affidavit Template',
            'content' => <<<'TEXT'
Counter Affidavit filed on behalf of the Respondent...

1. I am the respondent herein and I am well acquainted with the facts of the case.
TEXT,
        ]);

        $featured = $this->createCase([
            'title' => 'O.S. No. 412/2024',
            'parties_label' => 'Ramesh Kumar vs. State of TN',
            'state' => 'Tamil Nadu',
            'district' => 'Chennai',
            'court' => 'Madras High Court',
            'case_number' => 'O.S. No. 412',
            'case_year' => '2024',
            'type' => 'O.S.',
            'status' => 'Active',
            'next_hearing' => today()->toDateString(),
            'judge' => "Hon'ble High Court Judge",
            'stage' => 'Arguments',
            'is_pinned' => true,
            'tasks' => 'Review documents for upcoming cross-examination.',
            'party1_role' => 'Plaintiff',
            'party2_role' => 'Defendant',
        ], ['Ramesh Kumar'], ['State of TN']);

        $featured->events()->createMany([
            [
                'event_date' => today()->toDateString(),
                'title' => 'Next Hearing Date',
                'description' => 'Court Hall No. 5',
                'is_hearing' => true,
            ],
            [
                'event_date' => '2024-11-28',
                'title' => 'Counter Filed',
                'description' => 'Counter affidavit filed.',
                'file_name' => 'Counter.pdf',
                'file_size' => '2.4 MB',
                'is_hearing' => false,
            ],
            [
                'event_date' => '2024-11-20',
                'title' => 'Notice Issued',
                'description' => 'Notice sent to respondents.',
                'is_hearing' => false,
            ],
        ]);

        $featured->documents()->createMany([
            ['name' => 'Plaint_Copy.pdf', 'size' => '3.2 MB', 'folder' => 'Pleadings'],
            ['name' => 'Exhibit_A.pdf', 'size' => '1.1 MB', 'folder' => 'Exhibits'],
        ]);

        $firstNames = ['Ramesh', 'Suresh', 'Priya', 'Lakshmi', 'Karthik', 'Meera', 'Arjun', 'Sundar', 'Rajesh', 'Anita'];
        $lastNames = ['Kumar', 'Sharma', 'Singh', 'Nair', 'State of TN', 'Inspector of Police', 'ABC Pvt Ltd'];
        $types = [
            ['code' => 'O.S.', 'p1' => 'Plaintiff', 'p2' => 'Defendant'],
            ['code' => 'C.R.P.', 'p1' => 'Petitioner', 'p2' => 'Respondent'],
            ['code' => 'W.P.', 'p1' => 'Petitioner', 'p2' => 'Respondent'],
            ['code' => 'C.M.A.', 'p1' => 'Appellant', 'p2' => 'Respondent'],
            ['code' => 'S.A.', 'p1' => 'Appellant', 'p2' => 'Respondent'],
            ['code' => 'Crl.O.P.', 'p1' => 'Petitioner', 'p2' => 'Respondent'],
        ];
        $districts = ['Chennai', 'Madurai', 'Coimbatore', 'Salem', 'Trichy'];
        $stages = ['Arguments', 'Cross Examination', 'Filing of Counter', 'Admission'];

        for ($i = 2; $i <= 32; $i++) {
            $type = $types[mt_rand(0, count($types) - 1)];
            $district = $districts[mt_rand(0, count($districts) - 1)];
            $isHighCourt = $i <= 8;
            $petitioner = $firstNames[mt_rand(0, count($firstNames) - 1)].' '.$lastNames[mt_rand(0, count($lastNames) - 1)];
            $respondent = $firstNames[mt_rand(0, count($firstNames) - 1)].' '.$lastNames[mt_rand(0, count($lastNames) - 1)];
            $number = mt_rand(100, 9000);
            $year = 2020 + mt_rand(0, 6);
            $hearing = $i % 7 === 0 ? today()->toDateString() : $this->upcomingDate(mt_rand(1, 60));

            $this->createCase([
                'title' => $type['code'].' No. '.$number.'/'.$year,
                'parties_label' => $petitioner.' vs. '.$respondent,
                'state' => 'Tamil Nadu',
                'district' => $isHighCourt ? 'Chennai' : $district,
                'court' => $isHighCourt ? 'Madras High Court' : 'District Court, '.$district,
                'case_number' => $type['code'].' No. '.$number,
                'case_year' => (string) $year,
                'type' => $type['code'],
                'status' => $i % 9 === 0 ? 'Disposed' : 'Active',
                'next_hearing' => $hearing,
                'judge' => $isHighCourt ? "Hon'ble High Court Judge" : 'Principal District Judge',
                'stage' => $stages[mt_rand(0, count($stages) - 1)],
                'is_pinned' => $i <= 4,
                'tasks' => '',
                'party1_role' => $type['p1'],
                'party2_role' => $type['p2'],
            ], [$petitioner], [$respondent]);
        }

        $featured->touch();
    }

    private function createCase(array $attributes, array $petitioners, array $respondents): CourtCase
    {
        $case = CourtCase::query()->create($attributes);

        foreach ($petitioners as $index => $name) {
            $case->caseParties()->create(['side' => 1, 'name' => $name, 'sort_order' => $index]);
        }

        foreach ($respondents as $index => $name) {
            $case->caseParties()->create(['side' => 2, 'name' => $name, 'sort_order' => $index]);
        }

        return $case;
    }

    private function upcomingDate(int $days): string
    {
        $date = today()->addDays($days);
        while ($date->isWeekend()) {
            $date->addDay();
        }

        return $date->toDateString();
    }
}
