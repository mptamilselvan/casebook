<div class="w-72 h-screen bg-white dark:bg-[#14151B] border-r border-gray-200 dark:border-[#262833] flex flex-col shrink-0 z-20">
    <div class="p-4 flex items-center justify-between relative">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/casebook-logo.png') }}" alt="" class="w-9 h-9 object-contain">
            <h1 class="font-bold text-lg leading-tight">Case Book</h1>
        </div>
        <button type="button" @click.stop="topMenuOpen = !topMenuOpen; sidebarMenu = null; centerMenuOpen = false; notificationsOpen = false" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-[#20222B]">
            <x-icon name="ellipsis-vertical" class="w-5 h-5" />
        </button>
        <div x-show="topMenuOpen" x-cloak @click.stop class="absolute right-4 top-12 w-40 {{ $menuClass }}">
            <a href="{{ route('plans.index') }}" class="{{ $menuItemClass }} text-sm">
                <x-icon name="indian-rupee" class="w-4 h-4" /> Prices
            </a>
            <a href="{{ route('people.index') }}" class="{{ $menuItemClass }} text-sm">
                <x-icon name="users" class="w-4 h-4" /> People Management
            </a>
            <a href="{{ route('team.permissions.index') }}" class="{{ $menuItemClass }} text-sm">
                <x-icon name="list-todo" class="w-4 h-4" /> Roles &amp; Permissions
            </a>
            @if (request()->routeIs('cases.index'))
                <button type="button" @click="settingsOpen = true; topMenuOpen = false" class="{{ $menuItemClass }} text-sm">
                    <x-icon name="settings" class="w-4 h-4" /> Settings
                </button>
            @else
                <a href="{{ route('cases.index', ['settings' => 1]) }}" class="{{ $menuItemClass }} text-sm">
                    <x-icon name="settings" class="w-4 h-4" /> Settings
                </a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="{{ $menuItemClass }} text-sm">Sign out</button>
            </form>
        </div>
    </div>

    <div class="px-4 pb-3">
        @php
            $usage = \App\Support\Usage::summary(auth()->user());
            $storageLine = $usage['storage_limit_label']
                ? $usage['storage_label'].' of '.$usage['storage_limit_label']
                : $usage['storage_label'];
            $casesLine = $usage['cases_limit'] === null
                ? $usage['cases_count'].' cases'
                : $usage['cases_count'].' of '.$usage['cases_limit'].' cases';
        @endphp
        <p class="text-xs text-gray-500 break-all">{{ auth()->user()->email }}</p>
        <p class="text-sm font-semibold mt-0.5">{{ auth()->user()->plan?->name ?? auth()->user()->plan_name ?? 'Free' }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ $storageLine }}</p>
        <p class="text-xs text-gray-500">{{ $casesLine }}</p>
        @if ($mode = \App\Enums\CaseMode::current())
            <p class="text-sm font-semibold mt-2">{{ $mode->label() }} mode</p>
        @endif
    </div>

    <div class="px-4 pb-4 mt-2">
        @if ($can['CASE']['create'])
            @if (request()->routeIs('cases.index'))
            <button type="button" @click="openNewCase()" class="w-full bg-blue-600 hover:bg-blue-500 text-white rounded-lg py-2.5 flex items-center justify-center gap-2 shadow-sm">
                <x-icon name="plus" class="w-4 h-4" />
                <span class="font-semibold text-sm">New Case</span>
            </button>
            @else
            <a href="{{ route('cases.index', ['new' => 1]) }}" class="w-full bg-blue-600 hover:bg-blue-500 text-white rounded-lg py-2.5 flex items-center justify-center gap-2 shadow-sm">
                <x-icon name="plus" class="w-4 h-4" />
                <span class="font-semibold text-sm">New Case</span>
            </a>
            @endif
        @endif
    </div>

    <nav class="flex-1 overflow-y-auto pb-4">
        <div class="px-3 space-y-0.5 mt-2">
            @php
                $menuOff = 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#20222B] hover:text-gray-800 dark:hover:text-gray-200';
                $menuOn = 'bg-blue-600/20 text-blue-500 font-bold';
            @endphp
            <a href="{{ route('cases.index', ['filter' => $filter ?? 'All']) }}" class="flex items-center gap-3 px-3 py-2 rounded-md {{ request()->routeIs('cases.index') ? $menuOn : $menuOff }}">
                <x-icon name="briefcase" class="w-4 h-4" style="color:#2563EB" />
                <span class="text-sm">Cases</span>
            </a>
            <a href="{{ route('plans.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-md {{ request()->routeIs('plans.index') ? $menuOn : $menuOff }}">
                <x-icon name="indian-rupee" class="w-4 h-4" style="color:#CA8A04" />
                <span class="text-sm">Prices</span>
            </a>
            <a href="{{ route('people.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-md {{ request()->routeIs('people.*') ? $menuOn : $menuOff }}">
                <x-icon name="users" class="w-4 h-4" style="color:#059669" />
                <span class="text-sm">People Management</span>
            </a>
            <a href="{{ route('team.permissions.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-md {{ request()->routeIs('team.permissions.*') ? $menuOn : $menuOff }}">
                <x-icon name="list-todo" class="w-4 h-4" style="color:#7C3AED" />
                <span class="text-sm">Roles &amp; Permissions</span>
            </a>
        </div>

        @if ($switchMode = \App\Enums\CaseMode::current())
            @php
                $switchTo = $switchMode === \App\Enums\CaseMode::Office ? \App\Enums\CaseMode::Personal : \App\Enums\CaseMode::Office;
            @endphp
            <div class="px-3 mt-4">
                <a href="{{ route('mode.create') }}" class="flex items-center gap-3 px-3 py-3 rounded-lg border" style="background:#EFF6FF;border-color:#BFDBFE">
                    <span class="flex items-center justify-center rounded-lg shrink-0" style="width:2rem;height:2rem;background:#2563EB;color:#fff">
                        <x-icon name="arrow-left-right" class="w-4 h-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs text-gray-500">{{ $switchMode->label() }} mode</span>
                        <span class="block text-sm font-semibold" style="color:#1D4ED8">Switch to {{ $switchTo->label() }}</span>
                    </span>
                    <x-icon name="chevron-right" class="w-4 h-4 shrink-0" style="color:#2563EB" />
                </a>
            </div>
        @endif

    </nav>
</div>
