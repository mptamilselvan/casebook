<?php

namespace App\View\Composers;

use App\Models\CourtCase;
use App\Support\Casebook;
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

        $view->with([
            'filter' => 'All',
            'active' => null,
            'activeTemplate' => null,
            'cases' => Casebook::applyList(CourtCase::query(), 'All')->get(),
            'counts' => Casebook::caseCounts(),
        ]);
    }
}
