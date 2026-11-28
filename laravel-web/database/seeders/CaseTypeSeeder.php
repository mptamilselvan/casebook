<?php

namespace Database\Seeders;

use App\Models\CaseType;
use Illuminate\Database\Seeder;

class CaseTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['O.S' => 'O.S.', 'O.P' => 'O.P.', 'A.S' => 'A.S.'] as $from => $to) {
            CaseType::query()->where('code', $from)->update(['code' => $to]);
        }

        $types = [
            ['code' => 'O.S.', 'name' => 'Original Suit', 'category' => 'civil', 'sort_order' => 1],
            ['code' => 'O.P.', 'name' => 'Original Petition', 'category' => 'civil', 'sort_order' => 2],
            ['code' => 'A.S.', 'name' => 'Appeal Suit', 'category' => 'civil', 'sort_order' => 3],
            ['code' => 'A.O.', 'name' => 'Appeal Original', 'category' => 'civil', 'sort_order' => 4],
            ['code' => 'E.P.', 'name' => 'Execution Petition', 'category' => 'civil', 'sort_order' => 5],
            ['code' => 'I.A.', 'name' => 'Interlocutory Application', 'category' => 'civil', 'sort_order' => 6],
            ['code' => 'R.C.O.P.', 'name' => 'Rent Control Original Petition', 'category' => 'civil', 'sort_order' => 7],
            ['code' => 'H.M.O.P.', 'name' => 'Hindu Marriage Original Petition', 'category' => 'civil', 'sort_order' => 8],
            ['code' => 'C.C.', 'name' => 'Calendar Case', 'category' => 'criminal', 'sort_order' => 9],
            ['code' => 'S.T.C.', 'name' => 'Summary Trial Case', 'category' => 'criminal', 'sort_order' => 10],
            ['code' => 'S.C.', 'name' => 'Sessions Case', 'category' => 'criminal', 'sort_order' => 11],
            ['code' => 'Crl.M.P.', 'name' => 'Criminal Miscellaneous Petition', 'category' => 'criminal', 'sort_order' => 12],
            ['code' => 'Crl.A.', 'name' => 'Criminal Appeal', 'category' => 'criminal', 'sort_order' => 13],
            ['code' => 'Crl.R.C.', 'name' => 'Criminal Revision Case', 'category' => 'criminal', 'sort_order' => 14],
            ['code' => 'P.R.C.', 'name' => 'Preliminary Register Case', 'category' => 'criminal', 'sort_order' => 15],
        ];

        foreach ($types as $type) {
            CaseType::query()->updateOrCreate(
                ['code' => $type['code']],
                $type,
            );
        }
    }
}
