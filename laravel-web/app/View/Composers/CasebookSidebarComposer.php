<?php

namespace App\View\Composers;

use App\Models\CourtCase;
use Illuminate\View\View;

class CasebookSidebarComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        if (! array_key_exists('menuClass', $data)) {
            $view->with([
                'menuClass' => 'bg-white dark:bg-[#1A1C23] border border-gray-200 dark:border-[#262833] rounded-md shadow-xl z-50 py-1',
                'menuItemClass' => 'w-full text-left px-4 py-2 flex items-center gap-2 hover:bg-gray-100 dark:hover:bg-[#20222B]',
            ]);
        }

        if (array_key_exists('counts', $data)) {
            return;
        }

        $today = today();

        $view->with([
            'filter' => 'All Cases',
            'active' => null,
            'activeTemplate' => null,
            'cases' => CourtCase::query()->orderByDesc('is_pinned')->orderByDesc('updated_at')->get(),
            'counts' => [
                'All Cases' => CourtCase::query()->count(),
                "Today's Hearings" => CourtCase::query()->whereDate('next_hearing', $today)->count(),
                'Upcoming' => CourtCase::query()->whereDate('next_hearing', '>', $today)->count(),
                'Starred' => CourtCase::query()->where('is_pinned', true)->count(),
            ],
        ]);
    }
}
