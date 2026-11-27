<?php

namespace App\Support;

use App\Models\CourtCase;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class Casebook
{
    public const FILE_DISK = 'hf';

    public const FILTERS = ['All', 'Today', 'Tomorrow', 'Upcoming', 'Starred'];

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

    public const STATUSES = [
        'pending' => 'Pending',
        'ongoing' => 'Ongoing',
        'stayed' => 'Stayed',
        'disposed' => 'Disposed',
        'dismissed' => 'Dismissed',
        'withdrawn' => 'Withdrawn',
        'settled' => 'Settled',
    ];

    /**
     * @return array{0: string, 1: string}
     */
    public static function statusColor(string $status): array
    {
        return match ($status) {
            'ongoing' => ['#DBEAFE', '#1D4ED8'],
            'stayed', 'withdrawn' => ['#F3F4F6', '#4B5563'],
            'disposed', 'settled' => ['#D1FAE5', '#047857'],
            'dismissed' => ['#FEE2E2', '#B91C1C'],
            default => ['#FEF3C7', '#B45309'],
        };
    }

    public static function status(mixed $value): string
    {
        $key = strtolower(trim((string) $value));

        return match ($key) {
            'active', 'ongoing' => 'ongoing',
            'pending', 'stayed', 'disposed', 'dismissed', 'withdrawn', 'settled' => $key,
            default => 'pending',
        };
    }

    public const FILE_RULE = ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,jpg,jpeg,png,txt,rtf'];

    public static function files(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(self::FILE_DISK);

        return $disk;
    }

    public static function filter(mixed $value): string
    {
        return match ($value) {
            'All', 'All Cases' => 'All',
            'Today', "Today's Hearings" => 'Today',
            'Tomorrow' => 'Tomorrow',
            'Upcoming' => 'Upcoming',
            'Starred' => 'Starred',
            default => 'All',
        };
    }

    public static function applyList($query, string $filter)
    {
        $today = today();

        return match ($filter) {
            'Today' => $query->whereDate('next_hearing', $today),
            'Tomorrow' => $query->whereDate('next_hearing', $today->copy()->addDay()),
            'Upcoming' => $query->whereDate('next_hearing', '>', $today)->orderBy('next_hearing'),
            'Starred' => $query->where('is_pinned', true)->orderByDesc('updated_at'),
            default => $query->orderByDesc('is_pinned')->orderByDesc('updated_at'),
        };
    }

    /**
     * @return array<string, int>
     */
    public static function caseCounts(): array
    {
        $today = today();
        $tomorrow = $today->copy()->addDay();

        return [
            'All' => CourtCase::query()->count(),
            'Today' => CourtCase::query()->whereDate('next_hearing', $today)->count(),
            'Tomorrow' => CourtCase::query()->whereDate('next_hearing', $tomorrow)->count(),
            'Upcoming' => CourtCase::query()->whereDate('next_hearing', '>', $today)->count(),
            'Starred' => CourtCase::query()->where('is_pinned', true)->count(),
        ];
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
