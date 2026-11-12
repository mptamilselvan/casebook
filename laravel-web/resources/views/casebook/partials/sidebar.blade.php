@php
    $nav = [
        ['icon' => 'briefcase', 'label' => 'All Cases'],
        ['icon' => 'calendar', 'label' => "Today's Hearings"],
        ['icon' => 'clock', 'label' => 'Upcoming'],
        ['icon' => 'star', 'label' => 'Starred'],
    ];
@endphp

<div class="w-72 bg-white dark:bg-[#14151B] border-r border-gray-200 dark:border-[#262833] flex flex-col shrink-0 z-20">
    <div class="p-4 flex items-center justify-between relative">
        <div class="flex items-center gap-3">
            <x-icon name="scale" class="w-6 h-6 text-blue-500" />
            <h1 class="font-bold text-lg leading-tight">Case Book</h1>
        </div>
        <button type="button" @click.stop="topMenuOpen = !topMenuOpen; sidebarMenu = null; centerMenuOpen = false" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-[#20222B]">
            <x-icon name="ellipsis-vertical" class="w-5 h-5" />
        </button>
        <div x-show="topMenuOpen" x-cloak @click.stop class="absolute right-4 top-12 w-40 {{ $menuClass }}">
            <button type="button" @click="settingsOpen = true; topMenuOpen = false" class="{{ $menuItemClass }} text-sm">
                <x-icon name="settings" class="w-4 h-4" /> Settings
            </button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="{{ $menuItemClass }} text-sm">Sign out</button>
            </form>
        </div>
    </div>

    <div class="px-4 pb-4 mt-2">
        <button type="button" @click="openNewCase()" class="w-full bg-blue-600 hover:bg-blue-500 text-white rounded-lg py-2.5 flex items-center justify-center gap-2 shadow-sm">
            <x-icon name="plus" class="w-4 h-4" />
            <span class="font-semibold text-sm">New Case</span>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto pb-4">
        <div class="px-3 space-y-0.5 mt-2">
            @foreach ($nav as $item)
                <a :href="caseUrl(@js(route('cases.index', array_filter(['filter' => $item['label'], 'case' => $active?->id]))))"
                   class="flex items-center justify-between px-3 py-2 rounded-md cursor-pointer transition-colors {{ $filter === $item['label'] ? 'bg-blue-600/20 text-blue-500 font-bold' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#20222B] hover:text-gray-800 dark:hover:text-gray-200' }}">
                    <span class="flex items-center gap-3">
                        <x-icon name="{{ $item['icon'] }}" class="w-4 h-4" />
                        <span class="text-sm">{{ $item['label'] }}</span>
                    </span>
                    <span class="text-xs opacity-60">{{ $counts[$item['label']] }}</span>
                </a>
            @endforeach
        </div>

        <div class="px-4 mt-6 mb-2 relative">
            <x-icon name="search" class="w-4 h-4 absolute left-7 top-2.5 text-gray-500 dark:text-gray-400" />
            <input type="text" x-model="searchSidebar" placeholder="Search cases..." class="w-full bg-white dark:bg-[#1A1C23] border border-gray-300 dark:border-[#2A2D39] rounded-md py-1.5 pl-9 pr-3 text-sm outline-none">
        </div>

        <div class="px-4 mt-4">
            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">{{ $filter }}</h3>
            <div class="space-y-1 -mx-2">
                @forelse ($cases as $case)
                    @php
                        $haystack = strtolower($case->title.' '.$case->parties_label);
                        $isActive = $active && $active->id === $case->id;
                    @endphp
                    <div data-case-row data-hay="{{ $haystack }}" x-show="matches($el.dataset.hay)"
                         @click="window.location = caseUrl(@js(route('cases.index', ['filter' => $filter, 'case' => $case->id])))"
                         class="group relative p-3 cursor-pointer rounded-md transition-colors {{ $isActive ? 'bg-blue-50 dark:bg-[#202538] border-l-2 border-blue-500 rounded-l-none' : 'hover:bg-gray-100 dark:hover:bg-[#20222B]' }}">
                        <div class="flex items-center gap-2 pr-4">
                            @if ($case->is_pinned)
                                <x-icon name="star" class="w-3 h-3 text-yellow-500 fill-current shrink-0" />
                            @endif
                            <h4 class="font-bold text-sm truncate flex-1 {{ $isActive ? 'text-blue-700 dark:text-blue-100' : '' }}">{{ $case->title }}</h4>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 text-[11px] mt-1 truncate pr-4">{{ $case->parties_label }}</p>
                        <p class="text-gray-500 dark:text-gray-400 text-[10px] truncate pr-4 flex items-center mt-0.5">
                            <x-icon name="scale" class="w-3 h-3 mr-1" />{{ $case->court }}
                        </p>
                        @if ($case->next_hearing)
                            <div class="mt-2 inline-flex items-center bg-gray-50 dark:bg-[#0E0F14] border border-gray-200 dark:border-[#262833] rounded px-1.5 py-0.5">
                                <x-icon name="calendar-days" class="w-3 h-3 text-gray-500 dark:text-gray-400 mr-1" />
                                <span class="text-[10px] font-semibold {{ $case->next_hearing->isToday() ? 'text-red-500' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ $case->next_hearing->isToday() ? 'Today' : $case->next_hearing->toDateString() }}
                                </span>
                            </div>
                        @endif

                        <button type="button" @click.stop="sidebarMenu = sidebarMenu === {{ $case->id }} ? null : {{ $case->id }}; topMenuOpen = false; centerMenuOpen = false; eventMenu = null"
                                class="absolute right-2 top-3 p-1 rounded hover:bg-gray-100 dark:hover:bg-[#20222B] {{ $isActive ? 'opacity-100' : 'opacity-0 group-hover:opacity-100' }}"
                                :class="sidebarMenu === {{ $case->id }} ? 'opacity-100' : ''">
                            <x-icon name="ellipsis-vertical" class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                        </button>
                        <div x-show="sidebarMenu === {{ $case->id }}" x-cloak @click.stop class="absolute right-2 top-9 w-36 {{ $menuClass }}">
                            <form method="POST" action="{{ route('cases.pin', $case) }}">
                                @csrf
                                @include('casebook.partials.return-fields')
                                <button type="submit" class="{{ $menuItemClass }} text-xs">
                                    <x-icon name="pin" class="w-3 h-3" /> {{ $case->is_pinned ? 'Unstar Case' : 'Star Case' }}
                                </button>
                            </form>
                            <button type="button" @click="openEdit(@js(['id' => $case->id, 'title' => $case->title, 'court' => $case->court]))" class="{{ $menuItemClass }} text-xs">
                                <x-icon name="square-pen" class="w-3 h-3" /> Edit Case Info
                            </button>
                            <form method="POST" action="{{ route('cases.destroy', $case) }}" onsubmit="return confirm('Delete this case and its files?')">
                                @csrf
                                @method('DELETE')
                                @include('casebook.partials.return-fields')
                                <button type="submit" class="{{ $menuItemClass }} text-xs text-red-500">
                                    <x-icon name="trash" class="w-3 h-3" /> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="px-2 py-6 text-sm text-gray-500 dark:text-gray-400 italic">No cases in this list.</p>
                @endforelse
            </div>
        </div>
    </nav>
</div>
