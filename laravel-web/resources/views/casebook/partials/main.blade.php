<div :class="draftFullscreen ? 'absolute inset-0 z-50' : 'flex-1'" class="flex flex-col min-w-0 bg-white relative">
    @if ($active)
        <header x-show="!draftFullscreen" class="h-16 border-b border-gray-200 dark:border-[#262833] flex items-center justify-between px-6 gap-4">
            <a href="{{ route('cases.index', ['filter' => $filter]) }}" class="text-sm font-semibold text-blue-600 shrink-0">Cases</a>
            <div class="flex-1 max-w-xl relative">
                <x-icon name="search" class="w-4 h-4 absolute left-3 top-2.5 text-gray-500 dark:text-gray-400" />
                <input type="text" x-model="searchMain" placeholder="Search documents, drafting, hearings..." class="w-full bg-white dark:bg-[#14151B] border border-gray-300 dark:border-[#2A2D39] rounded-md py-1.5 pl-9 pr-3 text-sm outline-none focus:border-blue-500">
            </div>
            <div class="flex items-center gap-4">
                @include('casebook.partials.notifications')
                @if ($can['STAR_CASE']['edit'] || $can['PIN_CASE']['edit'])
                <form method="POST" action="{{ route('cases.pin', $active) }}">
                    @csrf
                    @include('casebook.partials.return-fields')
                    <button type="submit" class="p-1.5 rounded-md border {{ $active->is_pinned ? 'border-yellow-500/50 bg-yellow-500/10' : 'border-gray-200 dark:border-[#262833] hover:bg-gray-100 dark:hover:bg-[#20222B]' }}">
                        <x-icon name="star" class="w-5 h-5 {{ $active->is_pinned ? 'text-yellow-500 fill-current' : 'text-gray-500 dark:text-gray-400' }}" />
                    </button>
                </form>
                @endif
                @if ($can['CASE']['delete'])
                <button type="button" aria-label="Delete case" @click.stop="deleteCaseOpen = true; centerMenuOpen = false; topMenuOpen = false; sidebarMenu = null; notificationsOpen = false" class="p-1.5 rounded-md border border-red-200 text-red-600 hover:bg-red-50">
                    <x-icon name="trash" class="w-4 h-4" />
                </button>
                @endif
                    @if ($can['CASE']['edit'])
                    <div class="relative">
                    <button type="button" @click.stop="centerMenuOpen = !centerMenuOpen; topMenuOpen = false; sidebarMenu = null; notificationsOpen = false; deleteCaseOpen = false" class="p-1.5 rounded-md border border-gray-200 dark:border-[#262833] hover:bg-gray-100 dark:hover:bg-[#20222B]">
                        <x-icon name="ellipsis" class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                    </button>
                    <div x-show="centerMenuOpen" x-cloak @click.stop class="absolute right-0 top-10 w-44 {{ $menuClass }}">
                        <button type="button" @click="openEdit(@js(['id' => $active->id, 'title' => $active->title, 'court' => $active->court, 'mode' => $active->mode]))" class="{{ $menuItemClass }} text-sm">
                            <x-icon name="square-pen" class="w-4 h-4" /> Edit Case Info
                        </button>
                    </div>
                </div>
                    @endif
            </div>
        </header>

        <div class="flex-1 overflow-y-auto relative pb-20">
            <div :class="draftFullscreen ? 'p-0 h-full' : 'p-6 max-w-6xl'">
                <div x-show="!draftFullscreen" class="flex items-start justify-between mb-6 gap-6">
                    <div class="min-w-0">
                        <h2 class="text-2xl font-bold mb-1">{{ $active->title }}</h2>
                        <div class="flex items-center gap-2 text-sm font-semibold mb-3">
                            <x-icon name="calendar-days" class="w-4 h-4 {{ $active->next_hearing?->isToday() ? 'text-red-500' : 'text-blue-500' }}" />
                            <span class="{{ $active->next_hearing?->isToday() ? 'text-red-500' : 'text-blue-500' }}">
                                Hearing: {{ $active->next_hearing ? ($active->next_hearing->isToday() ? 'Today' : $active->next_hearing->toDateString()) : 'Not set' }}
                            </span>
                        </div>
                        <p class="text-lg mb-3">{{ $active->parties_label }}</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="bg-white dark:bg-[#1A1C23] border border-gray-200 dark:border-[#262833] text-xs px-2.5 py-1 rounded-full">{{ $active->court }}</span>
                            @php
                                $priorityTone = match ($active->priority ?? 'medium') {
                                    'high' => 'bg-red-100 text-red-700',
                                    'low' => 'bg-emerald-100 text-emerald-800',
                                    default => 'bg-amber-100 text-amber-800',
                                };
                            @endphp
                            @if ($can['STAR_CASE']['edit'] || $can['PIN_CASE']['edit'])
                            <form method="POST" action="{{ route('cases.pin', $active) }}" class="inline-flex">
                                @csrf
                                @include('casebook.partials.return-fields')
                                <button type="submit" class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-bold uppercase {{ $active->is_pinned ? 'border border-yellow-500/50 bg-yellow-500/10' : 'bg-gray-100 text-gray-600' }}" @if ($active->is_pinned) style="color: #ca8a04" @endif>
                                    <x-icon name="star" class="w-3 h-3 {{ $active->is_pinned ? 'fill-current' : '' }}" />
                                    {{ $active->is_pinned ? 'Starred' : 'Star' }}
                                </button>
                            </form>
                            @elseif ($active->is_pinned && ($can['STAR_CASE']['view'] || $can['PIN_CASE']['view']))
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-bold uppercase border border-yellow-500/50 bg-yellow-500/10" style="color: #ca8a04">
                                <x-icon name="star" class="w-3 h-3 fill-current" />
                                Starred
                            </span>
                            @endif
                            @if ($can['PROMOTE_CASE']['edit'])
                            <form method="POST" action="{{ route('cases.priority', $active) }}" class="inline-flex items-center gap-2">
                                @csrf
                                @include('casebook.partials.return-fields')
                                <label for="case-priority" class="text-xs font-semibold text-gray-500">Priority</label>
                                <select id="case-priority" name="priority" onchange="this.form.submit()" class="rounded-full border-0 px-3 py-1 text-xs font-bold uppercase outline-none {{ $priorityTone }}">
                                    @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $value => $label)
                                        <option value="{{ $value }}" @selected(($active->priority ?? 'medium') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>
                            @elseif ($can['PROMOTE_CASE']['view'])
                            <span class="rounded-full px-3 py-1 text-xs font-bold uppercase {{ $priorityTone }}">{{ ucfirst($active->priority ?? 'medium') }}</span>
                            @endif
                            @php
                                $caseStatus = \App\Support\Casebook::status($active->status);
                                [$statusBg, $statusFg] = \App\Support\Casebook::statusColor($caseStatus);
                            @endphp
                            @if ($can['CASE']['edit'])
                            <form method="POST" action="{{ route('cases.status', $active) }}" class="inline-flex items-center gap-2">
                                @csrf
                                @include('casebook.partials.return-fields')
                                <label for="case-status" class="text-xs font-semibold text-gray-500">Status</label>
                                <select id="case-status" name="status" onchange="this.form.submit()" class="rounded-full border-0 px-3 py-1 text-xs font-bold uppercase outline-none" style="background: {{ $statusBg }}; color: {{ $statusFg }}">
                                    @foreach (\App\Support\Casebook::STATUSES as $value => $label)
                                        <option value="{{ $value }}" @selected($caseStatus === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>
                            @else
                            <span class="rounded-full px-3 py-1 text-xs font-bold uppercase" style="background: {{ $statusBg }}; color: {{ $statusFg }}">{{ \App\Support\Casebook::STATUSES[$caseStatus] }}</span>
                            @endif
                            @php
                                $caseMode = \App\Enums\CaseMode::tryFrom((string) $active->mode) ?? \App\Enums\CaseMode::Personal;
                            @endphp
                            @if ($can['CASE']['edit'])
                            <form method="POST" action="{{ route('cases.mode', $active) }}" class="inline-flex items-center gap-2">
                                @csrf
                                @include('casebook.partials.return-fields')
                                <label for="case-mode" class="text-xs font-semibold text-gray-500">Mode</label>
                                <select id="case-mode" name="mode" onchange="this.form.submit()" class="rounded-full border-0 px-3 py-1 text-xs font-bold uppercase outline-none bg-gray-100 text-gray-600">
                                    @foreach (\App\Enums\CaseMode::cases() as $mode)
                                        <option value="{{ $mode->value }}" @selected($caseMode === $mode)>{{ $mode->label() }}</option>
                                    @endforeach
                                </select>
                            </form>
                            @else
                            <span class="rounded-full px-3 py-1 text-xs font-bold uppercase bg-gray-100 text-gray-600">{{ $caseMode->label() }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="bg-white dark:bg-[#1A1C23] border border-gray-200 dark:border-[#262833] rounded-xl p-4 flex items-center gap-4 w-60 group shrink-0">
                        <div class="bg-red-500/10 p-2.5 rounded-lg">
                            <x-icon name="calendar-days" class="w-6 h-6 text-red-500" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold flex justify-between">
                                Next Hearing
                                @if ($can['CASE']['edit'])
                                <x-icon name="square-pen" class="w-3 h-3 opacity-0 group-hover:opacity-100" />
                                @endif
                            </p>
                            @if ($can['CASE']['edit'])
                            <form method="POST" action="{{ route('cases.hearing', $active) }}" x-show="editingHearing" x-cloak>
                                @csrf
                                @include('casebook.partials.return-fields')
                                <input x-ref="hearing" type="date" name="next_hearing" value="{{ $active->next_hearing?->format('Y-m-d') }}" onchange="this.form.submit()" class="w-full bg-gray-50 dark:bg-[#0E0F14] border border-gray-300 dark:border-[#2A2D39] rounded text-sm p-1 mt-1 outline-none">
                            </form>
                            <p x-show="!editingHearing" @click="editingHearing = true; $nextTick(() => $refs.hearing && $refs.hearing.focus())" class="font-bold text-lg cursor-text hover:text-blue-500 truncate">
                                {{ $active->next_hearing?->toDateString() ?: 'Set Date...' }}
                            </p>
                            @else
                            <p class="font-bold text-lg truncate">{{ $active->next_hearing?->toDateString() ?: 'Not set' }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div x-show="!draftFullscreen" class="flex items-center justify-between border-b border-gray-200 dark:border-[#262833] mb-6">
                    <div class="flex gap-6 overflow-x-auto no-scrollbar">
                        @foreach (['Timeline' => $can['TIMELINE']['view'], 'Documents' => $can['DOCUMENT']['view'], 'Templates' => true, 'Parties' => true, 'Tasks' => true] as $item => $visible)
                            @if ($visible)
                            <button type="button" @click="tab = '{{ $item }}'; draftFullscreen = false" class="pb-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap" :class="tab === '{{ $item }}' ? 'border-blue-500 text-blue-500' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-blue-400'">{{ $item }}</button>
                            @endif
                        @endforeach
                    </div>
                    @if ($can['EVENT']['create'])
                    <button type="button" x-show="tab === 'Timeline'" @click="openEvent()" class="mb-2 flex items-center gap-2 bg-blue-600/10 hover:bg-blue-600/20 text-blue-500 px-3 py-1.5 rounded-md text-xs font-semibold">
                        <x-icon name="plus" class="w-3.5 h-3.5" /> Add Event
                    </button>
                    @endif
                    @if ($can['DOCUMENT']['create'])
                    <button type="button" x-show="tab === 'Documents'" x-cloak @click="openDocument()" class="mb-2 flex items-center gap-2 bg-blue-600/10 hover:bg-blue-600/20 text-blue-500 px-3 py-1.5 rounded-md text-xs font-semibold">
                        <x-icon name="plus" class="w-3.5 h-3.5" /> Upload Document
                    </button>
                    @endif
                    <button type="button" x-show="tab === 'Templates'" x-cloak @click="templateOpen = true" class="mb-2 flex items-center gap-2 bg-blue-600/10 hover:bg-blue-600/20 text-blue-500 px-3 py-1.5 rounded-md text-xs font-semibold">
                        <x-icon name="plus" class="w-3.5 h-3.5" /> New Template
                    </button>
                </div>

                @if ($can['TIMELINE']['view'])
                <div x-show="tab === 'Timeline'" @if ($tab !== 'Timeline') x-cloak @endif>
                    <div class="relative pl-6 pt-4">
                    <div class="absolute top-0 bottom-0 left-7 w-px bg-gray-200 dark:bg-[#262833]"></div>
                    @forelse ($active->events as $index => $event)
                        @php
                            if ($event->is_hearing) {
                                $tone = 'text-red-500';
                                $badge = 'bg-red-500/10';
                                $dot = 'bg-red-500';
                            } elseif ($index % 2 === 0) {
                                $tone = 'text-blue-500';
                                $badge = 'bg-blue-500/10';
                                $dot = 'bg-blue-500';
                            } else {
                                $tone = 'text-emerald-500';
                                $badge = 'bg-emerald-500/10';
                                $dot = 'bg-emerald-500';
                            }
                        @endphp
                        <div class="relative mb-8" data-hay="{{ strtolower($event->title.' '.$event->description) }}" x-show="matchesMain($el.dataset.hay)">
                            <div class="absolute -left-[5px] top-1.5 w-2.5 h-2.5 {{ $dot }} rounded-full border-2 border-gray-50 dark:border-[#0E0F14]"></div>
                            <div class="flex items-center gap-3 mb-2 ml-6">
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $event->event_date->toDateString() }}</span>
                                @if ($event->is_hearing)
                                    <span class="text-[10px] bg-red-500/10 text-red-500 border border-red-500/20 px-2 py-0.5 rounded-full font-bold">Hearing</span>
                                @endif
                            </div>
                            <div class="ml-6 bg-gray-50 dark:bg-[#16181D] border border-gray-200 dark:border-[#262833] rounded-xl p-4 group relative hover:border-gray-400 transition-colors">
                                @if ($can['EVENT']['edit'] || $can['EVENT']['delete'])
                                <button type="button" @click.stop="eventMenu = eventMenu === {{ $event->id }} ? null : {{ $event->id }}; sidebarMenu = null; notificationsOpen = false" class="absolute right-3 top-3 opacity-0 group-hover:opacity-100 p-1.5 rounded text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#20222B]" :class="eventMenu === {{ $event->id }} ? 'opacity-100' : ''">
                                    <x-icon name="ellipsis-vertical" class="w-4 h-4" />
                                </button>
                                <div x-show="eventMenu === {{ $event->id }}" x-cloak @click.stop class="absolute right-10 top-3 w-32 {{ $menuClass }}">
                                    @if ($can['EVENT']['edit'])
                                    <button type="button" @click="openEvent(@js(['id' => $event->id, 'date' => $event->event_date->format('Y-m-d'), 'title' => $event->title, 'description' => $event->description, 'isHearing' => $event->is_hearing, 'fileName' => $event->file_name]))" class="{{ $menuItemClass }} text-xs">
                                        <x-icon name="square-pen" class="w-3 h-3" /> Edit
                                    </button>
                                    @endif
                                    @if ($can['EVENT']['delete'])
                                    <form method="POST" action="{{ route('events.destroy', [$active, $event]) }}" onsubmit="return confirm('Delete this event?')">
                                        @csrf
                                        @method('DELETE')
                                        @include('casebook.partials.return-fields')
                                        <button type="submit" class="{{ $menuItemClass }} text-xs text-red-500">
                                            <x-icon name="trash" class="w-3 h-3" /> Delete
                                        </button>
                                    </form>
                                    @endif
                                </div>
                                @endif
                                <div class="flex items-start pr-8">
                                    <div class="{{ $badge }} {{ $tone }} p-2 rounded-lg mt-0.5">
                                        <x-icon name="{{ $event->is_hearing ? 'calendar-days' : 'file-text' }}" class="w-5 h-5" />
                                    </div>
                                    <div class="ml-4">
                                        <h4 class="font-bold">{{ $event->title }}</h4>
                                        @if ($event->description)
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $event->description }}</p>
                                        @endif
                                    </div>
                                </div>
                                @if ($event->file_name)
                                    <div class="mt-4 flex items-center justify-between bg-white dark:bg-[#1A1C23] border border-gray-200 dark:border-[#262833] rounded-lg p-2.5 ml-14 gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="bg-red-500/10 text-red-500 p-1.5 rounded text-[10px] font-bold">PDF</div>
                                            <span class="text-sm font-medium truncate">{{ $event->file_name }}</span>
                                        </div>
                                        <div class="flex items-center gap-4 shrink-0">
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $event->file_size }}</span>
                                            <button type="button" @click="openViewer(@js($event->file_name), @js($event->file_path ? route('events.file', $event) : ''))" class="bg-gray-50 dark:bg-[#16181D] border border-gray-200 dark:border-[#262833] text-xs font-medium px-3 py-1 rounded flex items-center hover:bg-gray-100 dark:hover:bg-[#20222B]">
                                                <x-icon name="eye" class="w-3 h-3 mr-1" /> View
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                    @endforelse
                    <div x-show="!hasVisible(eventHaystacks)" class="ml-6 text-gray-500 dark:text-gray-400 py-10 italic">No events match your search, or none logged yet.</div>
                </div>
                </div>
                @endif

                @if ($can['DOCUMENT']['view'])
                <div x-show="tab === 'Documents'" @if ($tab !== 'Documents') x-cloak @endif>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @foreach ($active->documents as $document)
                            <div data-hay="{{ strtolower($document->name.' '.$document->folder) }}" x-show="matchesMain($el.dataset.hay)" @click="openViewer(@js($document->name), @js($document->file_path ? route('documents.file', $document) : ''))" class="bg-gray-50 dark:bg-[#16181D] border border-gray-200 dark:border-[#262833] rounded-xl p-4 hover:border-blue-500 transition-colors cursor-pointer group">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="bg-red-500/10 text-red-500 p-2 rounded-lg">
                                        <x-icon name="file-text" class="w-6 h-6" />
                                    </div>
                                    <span class="opacity-0 group-hover:opacity-100 bg-white dark:bg-[#1A1C23] p-1.5 rounded border border-gray-200 dark:border-[#262833]">
                                        <x-icon name="eye" class="w-4 h-4" />
                                    </span>
                                </div>
                                <h4 class="font-semibold text-sm truncate">{{ $document->name }}</h4>
                                <div class="flex justify-between items-center mt-2">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $document->folder }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $document->size }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p x-show="!hasVisible(documentHaystacks)" class="text-gray-500 dark:text-gray-400 py-10 text-center italic">No documents match your search, or none uploaded yet.</p>
                </div>
                @endif

                <div x-show="tab === 'Parties'" @if ($tab !== 'Parties') x-cloak @endif>
                    <form method="POST" action="{{ route('parties.sync', $active) }}" class="bg-gray-50 dark:bg-[#16181D] border border-gray-200 dark:border-[#262833] rounded-xl p-6">
                        @csrf
                        @include('casebook.partials.return-fields')
                        <div class="flex gap-8">
                            <div class="flex-1">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="font-bold flex items-center"><x-icon name="users" class="w-4 h-4 mr-2" /> Side 1</h3>
                                    <select name="party1_role" onchange="this.form.requestSubmit()" @disabled(! $can['CASE']['edit']) class="bg-transparent text-sm font-bold outline-none text-gray-500 dark:text-gray-400">
                                        @foreach ($party1Roles as $role => $label)
                                            <option value="{{ $role }}" @selected($active->party1_role === $role)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="space-y-3">
                                    @forelse ($sideOne as $party)
                                        <input type="text" name="side1[]" value="{{ $party->name }}" @readonly(! $can['CASE']['edit']) class="w-full bg-white dark:bg-[#1A1C23] border border-gray-300 dark:border-[#2A2D39] rounded-md p-2.5 text-sm outline-none focus:border-blue-500" placeholder="{{ $active->party1_role }} {{ $loop->iteration }}">
                                    @empty
                                        <input type="text" name="side1[]" value="" @readonly(! $can['CASE']['edit']) class="w-full bg-white dark:bg-[#1A1C23] border border-gray-300 dark:border-[#2A2D39] rounded-md p-2.5 text-sm outline-none focus:border-blue-500" placeholder="{{ $active->party1_role }} 1">
                                    @endforelse
                                    @if ($can['CASE']['edit'])
                                    <div class="flex items-center gap-4 mt-2">
                                        <button type="submit" class="px-3 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-sm font-semibold">Save</button>
                                        <button type="submit" name="add_side" value="1" class="text-blue-500 text-sm font-semibold flex items-center hover:text-blue-400">
                                            <x-icon name="plus" class="w-4 h-4 mr-1" /> Add Party
                                        </button>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            <div class="w-px bg-gray-200 dark:bg-[#262833]"></div>
                            <div class="flex-1">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="font-bold flex items-center"><x-icon name="users" class="w-4 h-4 mr-2" /> Side 2</h3>
                                    <select name="party2_role" onchange="this.form.requestSubmit()" @disabled(! $can['CASE']['edit']) class="bg-transparent text-sm font-bold outline-none text-gray-500 dark:text-gray-400">
                                        @foreach ($party2Roles as $role => $label)
                                            <option value="{{ $role }}" @selected($active->party2_role === $role)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="space-y-3">
                                    @forelse ($sideTwo as $party)
                                        <input type="text" name="side2[]" value="{{ $party->name }}" @readonly(! $can['CASE']['edit']) class="w-full bg-white dark:bg-[#1A1C23] border border-gray-300 dark:border-[#2A2D39] rounded-md p-2.5 text-sm outline-none focus:border-blue-500" placeholder="{{ $active->party2_role }} {{ $loop->iteration }}">
                                    @empty
                                        <input type="text" name="side2[]" value="" @readonly(! $can['CASE']['edit']) class="w-full bg-white dark:bg-[#1A1C23] border border-gray-300 dark:border-[#2A2D39] rounded-md p-2.5 text-sm outline-none focus:border-blue-500" placeholder="{{ $active->party2_role }} 1">
                                    @endforelse
                                    @if ($can['CASE']['edit'])
                                    <div class="flex items-center gap-4 mt-2">
                                        <button type="submit" class="px-3 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-sm font-semibold">Save</button>
                                        <button type="submit" name="add_side" value="2" class="text-red-500 text-sm font-semibold flex items-center hover:text-red-400">
                                            <x-icon name="plus" class="w-4 h-4 mr-1" /> Add Party
                                        </button>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div x-show="tab === 'Tasks'" @if ($tab !== 'Tasks') x-cloak @endif>
                    <form method="POST" action="{{ route('cases.tasks', $active) }}" class="bg-gray-50 dark:bg-[#16181D] border border-gray-200 dark:border-[#262833] rounded-xl overflow-hidden h-[500px] flex flex-col">
                        @csrf
                        @include('casebook.partials.return-fields')
                        <div class="p-4 border-b border-gray-200 dark:border-[#262833] bg-white dark:bg-[#1A1C23] flex items-center">
                            <x-icon name="list-todo" class="w-5 h-5 text-gray-500 dark:text-gray-400 mr-2" />
                            <h3 class="font-bold">Tasks & Preparation Notes</h3>
                        </div>
                        <textarea name="tasks" @readonly(! $can['CASE']['edit']) onblur="if (!this.form.contains(event.relatedTarget)) this.form.requestSubmit()" class="flex-1 w-full bg-transparent p-6 outline-none resize-none leading-relaxed" placeholder="Write down tasks to complete before the next hearing (e.g., Draft cross questions for PW1, Collect certified copy of sale deed).">{{ $active->tasks }}</textarea>
                    </form>
                </div>

                <div x-show="tab === 'Templates'" @if ($tab !== 'Templates') x-cloak @endif class="flex overflow-hidden bg-gray-50 dark:bg-[#0E0F14]" :class="draftFullscreen ? 'h-full border-0 rounded-none' : 'h-[500px] border border-gray-200 dark:border-[#262833] rounded-xl'">
                    <div x-show="!draftFullscreen" class="w-1/3 border-r border-gray-200 dark:border-[#262833] bg-white dark:bg-[#14151B] flex flex-col">
                        <div class="p-3 border-b border-gray-200 dark:border-[#262833]">
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Global Templates</p>
                        </div>
                        <div class="flex-1 overflow-y-auto p-2 space-y-1">
                            @forelse ($templates as $template)
                                <button type="button" @click="activeTemplateId = {{ $template->id }}" class="w-full text-left px-3 py-2 rounded-md text-sm truncate transition-colors" :class="activeTemplateId === {{ $template->id }} ? 'bg-blue-600/10 text-blue-500 font-bold' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#20222B]'">
                                    <x-icon name="file-text" class="w-4 h-4 inline mr-2 opacity-50" /> {{ $template->title }}
                                </button>
                            @empty
                                <p class="px-3 py-6 text-sm text-gray-500 dark:text-gray-400 italic">No templates yet.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="flex-1 flex flex-col bg-white dark:bg-[#16181D] min-w-0">
                        @forelse ($templates as $template)
                            <form method="POST" action="{{ route('templates.update', $template) }}" x-show="activeTemplateId === {{ $template->id }}" @if ($activeTemplate?->id !== $template->id) x-cloak @endif class="flex-1 flex flex-col min-h-0">
                                @csrf
                                @method('PUT')
                                @include('casebook.partials.return-fields', ['activeTemplate' => $template])
                                <div class="p-3 border-b border-gray-200 dark:border-[#262833] flex justify-between items-center bg-white dark:bg-[#1A1C23]">
                                    <button type="button" @click="draftFullscreen = !draftFullscreen" class="flex items-center gap-1 text-gray-500 dark:text-gray-400 hover:text-blue-500 px-2 py-1 rounded text-xs font-semibold">
                                        <span x-show="!draftFullscreen" class="flex items-center gap-1"><x-icon name="maximize-2" class="w-4 h-4" /> Expand Editor</span>
                                        <span x-show="draftFullscreen" x-cloak class="flex items-center gap-1"><x-icon name="minimize-2" class="w-4 h-4" /> Exit Fullscreen</span>
                                    </button>
                                    <div class="flex gap-2">
                                        <button type="submit" class="flex items-center gap-1 bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded text-xs font-bold shadow-sm">
                                            <x-icon name="database" class="w-3.5 h-3.5" /> Save Template
                                        </button>
                                        <a href="{{ route('templates.export', $template) }}" class="flex items-center gap-1 bg-blue-600 hover:bg-blue-500 text-white px-3 py-1.5 rounded text-xs font-semibold">
                                            <x-icon name="download" class="w-3.5 h-3.5" /> Export Word
                                        </a>
                                    </div>
                                </div>
                                <textarea name="content" class="flex-1 w-full p-8 text-base bg-transparent outline-none resize-none leading-relaxed" placeholder="Start drafting your template here. This will be available across all your cases...">{{ $template->content }}</textarea>
                            </form>
                        @empty
                            <div class="flex-1 flex items-center justify-center flex-col text-gray-500 dark:text-gray-400">
                                <x-icon name="file-text" class="w-12 h-12 mb-4 opacity-20" />
                                <p>Select a template or create a new one.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @else
        <header class="h-16 border-b border-gray-200 dark:border-[#262833] flex items-center justify-between px-6">
            <div>
                <h2 class="text-lg font-bold">Cases</h2>
                <p class="text-xs text-gray-500">{{ \App\Enums\CaseMode::current()?->label() }} mode</p>
            </div>
            @include('casebook.partials.notifications')
        </header>
        <div class="flex-1 overflow-y-auto p-6 max-w-6xl">
            <div class="flex gap-6 overflow-x-auto border-b border-gray-200 dark:border-[#262833] mb-4">
                @foreach (\App\Support\Casebook::FILTERS as $item)
                    <a :href="caseUrl(@js(route('cases.index', ['filter' => $item])))"
                       class="pb-2 text-sm whitespace-nowrap border-b-2 {{ $filter === $item ? 'border-blue-500 text-blue-500 font-semibold' : 'border-transparent text-gray-500' }}">{{ $item }}</a>
                @endforeach
            </div>
            <div class="mb-4 relative max-w-xl">
                <x-icon name="search" class="w-4 h-4 absolute left-3 top-2.5 text-gray-500 dark:text-gray-400" />
                <input type="text" x-model="searchSidebar" placeholder="Search cases..." class="w-full bg-white dark:bg-[#1A1C23] border border-gray-300 dark:border-[#2A2D39] rounded-md py-1.5 pl-9 pr-3 text-sm outline-none">
            </div>
            <div class="border border-gray-200 dark:border-[#262833] rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-[#16181D] text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Case</th>
                            <th class="px-4 py-3 font-semibold">Parties</th>
                            <th class="px-4 py-3 font-semibold">Court</th>
                            <th class="px-4 py-3 font-semibold">Hearing</th>
                            <th class="px-4 py-3 font-semibold">Priority</th>
                            <th class="px-4 py-3 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cases as $case)
                            @php
                                $haystack = strtolower($case->title.' '.$case->parties_label.' '.$case->court);
                                $priorityTone = match ($case->priority ?? 'medium') {
                                    'high' => 'bg-red-100 text-red-700',
                                    'low' => 'bg-emerald-100 text-emerald-800',
                                    default => 'bg-amber-100 text-amber-800',
                                };
                            @endphp
                            <tr data-case-row data-hay="{{ $haystack }}" x-show="matches($el.dataset.hay)"
                                @click="window.location = caseUrl(@js(route('cases.index', ['filter' => $filter, 'case' => $case->id])))"
                                class="border-t border-gray-200 dark:border-[#262833] cursor-pointer hover:bg-gray-100 dark:hover:bg-[#20222B]">
                                <td class="px-4 py-3">
                                    <span class="font-bold">{{ $case->title }}</span>
                                    @if ($case->is_pinned)
                                        <span class="ml-2 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase bg-yellow-500/10 text-yellow-500">Starred</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $case->parties_label }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $case->court }}</td>
                                <td class="px-4 py-3 {{ $case->next_hearing?->isToday() ? 'text-red-500 font-semibold' : 'text-gray-500' }}">
                                    {{ $case->next_hearing ? ($case->next_hearing->isToday() ? 'Today' : $case->next_hearing->toDateString()) : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $priorityTone }}">{{ ucfirst($case->priority ?? 'medium') }}</span>
                                </td>
                                <td class="px-4 py-3 relative" @click.stop>
                                    @if ($can['CASE']['edit'] || $can['STAR_CASE']['edit'] || $can['PIN_CASE']['edit'])
                                        <button type="button" @click.stop="sidebarMenu = sidebarMenu === {{ $case->id }} ? null : {{ $case->id }}; topMenuOpen = false; centerMenuOpen = false; eventMenu = null; notificationsOpen = false" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-[#20222B]">
                                            <x-icon name="ellipsis-vertical" class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                                        </button>
                                        <div x-show="sidebarMenu === {{ $case->id }}" x-cloak @click.stop class="absolute right-4 top-10 w-36 {{ $menuClass }}">
                                            @if ($can['STAR_CASE']['edit'] || $can['PIN_CASE']['edit'])
                                            <form method="POST" action="{{ route('cases.pin', $case) }}">
                                                @csrf
                                                @include('casebook.partials.return-fields')
                                                <button type="submit" class="{{ $menuItemClass }} text-xs">
                                                    <x-icon name="pin" class="w-3 h-3" /> {{ $case->is_pinned ? 'Unstar Case' : 'Star Case' }}
                                                </button>
                                            </form>
                                            @endif
                                            @if ($can['CASE']['edit'])
                                            <button type="button" @click="openEdit(@js(['id' => $case->id, 'title' => $case->title, 'court' => $case->court, 'mode' => $case->mode]))" class="{{ $menuItemClass }} text-xs">
                                                <x-icon name="square-pen" class="w-3 h-3" /> Edit Case Info
                                            </button>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="border-t border-gray-200 dark:border-[#262833]">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500 italic">No cases in this list.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
