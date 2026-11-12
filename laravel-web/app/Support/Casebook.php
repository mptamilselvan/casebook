<?php

namespace App\Support;

class Casebook
{
    public const FILTERS = ['All Cases', "Today's Hearings", 'Upcoming', 'Starred'];

    public const TABS = ['Timeline', 'Documents', 'Templates', 'Parties', 'Tasks'];

    public const PARTY_1 = [
        'Petitioner' => 'Petitioners',
        'Plaintiff' => 'Plaintiffs',
        'Appellant' => 'Appellants',
        'Applicant' => 'Applicants',
    ];

    public const PARTY_2 = [
        'Respondent' => 'Respondents',
        'Defendant' => 'Defendants',
        'Opposite Party' => 'Opposite Parties',
    ];

    public const FOLDERS = ['Pleadings', 'Court Orders', 'Exhibits'];

    public const FILE_RULE = ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,jpg,jpeg,png,txt,rtf'];

    public static function filter(mixed $value): string
    {
        return in_array($value, self::FILTERS, true) ? $value : 'All Cases';
    }

    public static function tab(mixed $value): string
    {
        return in_array($value, self::TABS, true) ? $value : 'Timeline';
    }

    public static function sizeLabel(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
