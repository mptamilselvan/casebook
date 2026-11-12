<div x-show="settingsOpen" x-cloak class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#14151B] border border-gray-200 dark:border-[#262833] rounded-2xl w-full max-w-sm shadow-2xl p-6" @click.stop>
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold">App Settings</h2>
            <button type="button" @click="settingsOpen = false" class="text-gray-500 dark:text-gray-400"><x-icon name="x" class="w-5 h-5" /></button>
        </div>
        <label class="block text-xs font-semibold text-gray-500 mb-2">Signed in</label>
        <p class="text-sm font-medium text-gray-800">{{ auth()->user()->email }}</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="w-full py-2 rounded-lg font-medium bg-gray-900 text-white">Sign out</button>
        </form>
    </div>
</div>

<div x-show="editOpen" x-cloak class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <form method="POST" :action="`/cases/${edit.id}`" class="bg-white dark:bg-[#1A1C23] border border-gray-200 dark:border-[#262833] rounded-2xl w-full max-w-md shadow-2xl" @click.stop>
        @csrf
        @method('PUT')
        @include('casebook.partials.return-fields')
        <div class="flex justify-between p-5 border-b border-gray-200 dark:border-[#262833]">
            <h2 class="text-lg font-bold">Edit Case Info</h2>
            <button type="button" @click="editOpen = false" class="text-gray-500 dark:text-gray-400"><x-icon name="x" class="w-5 h-5" /></button>
        </div>
        <div class="p-6 space-y-4">
            @if (session('modal') === 'edit-case' && $errors->any())
                <p class="text-sm text-red-500">{{ $errors->first() }}</p>
            @endif
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Case Title / Number</label>
                <input type="text" name="title" x-model="edit.title" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Court Name</label>
                <input type="text" name="court" x-model="edit.court" class="{{ $inputClass }}">
            </div>
            <button type="submit" class="w-full mt-4 py-3 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-bold" :disabled="!edit.title">Save Changes</button>
        </div>
    </form>
</div>

<div x-show="viewerOpen" x-cloak class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#1A1C23] border border-gray-200 dark:border-[#262833] rounded-xl w-full max-w-4xl h-[80vh] flex flex-col" @click.stop>
        <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-[#262833] gap-3">
            <h3 class="font-bold flex items-center min-w-0"><x-icon name="file-text" class="w-5 h-5 mr-2 text-red-500 shrink-0" /> <span class="truncate" x-text="viewer.name"></span></h3>
            <div class="flex items-center gap-3">
                <a x-show="viewer.url" :href="viewer.url + '?download=1'" class="text-xs font-semibold text-blue-500">Download</a>
                <button type="button" @click="viewerOpen = false" class="text-gray-500 dark:text-gray-400"><x-icon name="x" class="w-5 h-5" /></button>
            </div>
        </div>
        <div class="flex-1 min-h-0">
            <iframe x-show="viewer.url" :src="viewer.url" class="w-full h-full bg-white" title="Document preview"></iframe>
            <div x-show="!viewer.url" class="h-full flex items-center justify-center text-gray-500 dark:text-gray-400 p-6 text-center">
                <p>Document preview for <b x-text="viewer.name"></b> would load here.</p>
            </div>
        </div>
    </div>
</div>

<div x-show="newCaseOpen" x-cloak class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <form method="POST" action="{{ route('cases.store') }}" class="bg-white dark:bg-[#1A1C23] border border-gray-200 dark:border-[#262833] rounded-2xl w-full max-w-lg shadow-2xl flex flex-col max-h-[90vh]" @click.stop @submit="if (modalStep === 1) { $event.preventDefault(); if (court.trim() !== '') modalStep = 2 }">
        @csrf
        @include('casebook.partials.return-fields')
        <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-[#262833]">
            <div>
                <h2 class="text-xl font-bold">Create New Case</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Step <span x-text="modalStep"></span> of 2</p>
            </div>
            <button type="button" @click="newCaseOpen = false" class="text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-[#16181D] p-1.5 rounded-md"><x-icon name="x" class="w-5 h-5" /></button>
        </div>
        <div class="p-6 overflow-y-auto">
            @if (session('modal') === 'new-case' && $errors->any())
                <p class="text-sm text-red-500 mb-4">{{ $errors->first() }}</p>
            @endif
            <div x-show="modalStep === 1" class="space-y-5">
                <input type="text" name="state" x-model="state" placeholder="State (e.g., Tamil Nadu)" class="{{ $inputClass }}">
                <input type="text" name="district" x-model="district" placeholder="District" class="{{ $inputClass }}">
                <input type="text" name="court" x-model="court" placeholder="Court Name" class="{{ $inputClass }}">
                <button type="button" @click="modalStep = 2" :disabled="court.trim() === ''" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-bold disabled:opacity-50">Next Details <x-icon name="chevron-right" class="w-4 h-4 inline" /></button>
            </div>
            <div x-show="modalStep === 2" x-cloak class="space-y-5">
                <div class="flex gap-4">
                    <input type="text" name="case_number" x-model="caseNumber" placeholder="Case No (O.S. 124)" class="{{ $inputClass }} flex-1">
                    <input type="text" name="case_year" x-model="caseYear" class="{{ $inputClass }} w-1/3 text-center">
                </div>
                <div class="bg-white dark:bg-[#1A1C23] p-4 rounded-xl border border-gray-200 dark:border-[#262833]">
                    <div class="space-y-3">
                        <select name="party1_role" x-model="p1Role" class="bg-transparent text-sm font-bold outline-none">
                            @foreach ($party1Roles as $role => $label)
                                <option value="{{ $role }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <template x-for="(name, index) in petitioners" :key="'p' + index">
                            <div class="flex items-center gap-2">
                                <input type="text" :name="`petitioners[${index}]`" x-model="petitioners[index]" class="flex-1 bg-gray-50 dark:bg-[#16181D] border border-gray-300 dark:border-[#2A2D39] rounded-md p-2 text-sm outline-none" :placeholder="index === 0 ? `${p1Role} Name` : `Addl ${p1Role} ${index + 1}`">
                                <button type="button" x-show="index === 0" @click="petitioners.push('')" class="p-2 border border-gray-200 dark:border-[#262833] rounded-md hover:bg-gray-100 dark:hover:bg-[#20222B]"><x-icon name="plus" class="w-4 h-4" /></button>
                            </div>
                        </template>
                    </div>
                    <div class="text-center py-4 text-xs font-bold text-gray-500 dark:text-gray-400 italic">VS</div>
                    <div class="space-y-3">
                        <select name="party2_role" x-model="p2Role" class="bg-transparent text-sm font-bold outline-none">
                            @foreach ($party2Roles as $role => $label)
                                <option value="{{ $role }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <template x-for="(name, index) in respondents" :key="'r' + index">
                            <div class="flex items-center gap-2">
                                <input type="text" :name="`respondents[${index}]`" x-model="respondents[index]" class="flex-1 bg-gray-50 dark:bg-[#16181D] border border-gray-300 dark:border-[#2A2D39] rounded-md p-2 text-sm outline-none" :placeholder="index === 0 ? `${p2Role} Name` : `Addl ${p2Role} ${index + 1}`">
                                <button type="button" x-show="index === 0" @click="respondents.push('')" class="p-2 border border-gray-200 dark:border-[#262833] rounded-md hover:bg-gray-100 dark:hover:bg-[#20222B]"><x-icon name="plus" class="w-4 h-4" /></button>
                            </div>
                        </template>
                    </div>
                </div>
                <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg font-bold">Create Case File</button>
            </div>
        </div>
    </form>
</div>

@if ($active)
<div x-show="eventOpen" x-cloak class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <form method="POST" enctype="multipart/form-data" class="bg-white dark:bg-[#1A1C23] border border-gray-200 dark:border-[#262833] rounded-2xl w-full max-w-md shadow-2xl" :action="eventForm.id ? `/cases/{{ $active->id }}/events/${eventForm.id}` : @js(route('events.store', $active))" @click.stop>
        @csrf
        <template x-if="eventForm.id">
            <input type="hidden" name="_method" value="PUT">
        </template>
        <input type="hidden" name="event_id" :value="eventForm.id">
        @include('casebook.partials.return-fields')
        <div class="flex justify-between p-5 border-b border-gray-200 dark:border-[#262833]">
            <h2 class="text-lg font-bold" x-text="eventForm.id ? 'Edit Event' : 'Add Timeline Event'"></h2>
            <button type="button" @click="eventOpen = false" class="text-gray-500 dark:text-gray-400"><x-icon name="x" class="w-5 h-5" /></button>
        </div>
        <div class="p-6 space-y-4">
            @if (session('modal') === 'event' && $errors->any())
                <p class="text-sm text-red-500">{{ $errors->first() }}</p>
            @endif
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Date</label>
                <input type="date" name="event_date" x-model="eventForm.date" class="{{ $inputClass }}">
            </div>
            <div class="flex items-center gap-3">
                <input type="checkbox" id="isHearing" name="is_hearing" value="1" x-model="eventForm.isHearing" class="w-4 h-4">
                <label for="isHearing" class="text-sm">This is a Hearing Date</label>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Title</label>
                <input type="text" name="title" placeholder="e.g., Counter Filed" x-model="eventForm.title" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Notes</label>
                <textarea name="description" rows="2" x-model="eventForm.description" class="{{ $inputClass }} resize-none"></textarea>
            </div>
            <div x-show="!eventForm.isHearing">
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Attach File</label>
                <div class="flex items-center gap-3 bg-gray-50 dark:bg-[#16181D] border border-gray-300 dark:border-[#2A2D39] rounded-lg p-2">
                    <input x-ref="eventFile" type="file" name="file" id="file-up" class="hidden" @change="eventForm.fileLabel = $event.target.files[0]?.name || 'No file'">
                    <label for="file-up" class="bg-[#262833] text-white text-xs font-medium px-3 py-1.5 rounded cursor-pointer">Choose File</label>
                    <span class="text-xs text-gray-500 dark:text-gray-400 truncate flex-1" x-text="eventForm.fileLabel"></span>
                </div>
            </div>
            <button type="submit" :disabled="!eventForm.title" class="w-full mt-4 py-3 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-bold disabled:opacity-50">Save Event</button>
        </div>
    </form>
</div>

<div x-show="docOpen" x-cloak class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <form method="POST" action="{{ route('documents.store', $active) }}" enctype="multipart/form-data" class="bg-white dark:bg-[#1A1C23] border border-gray-200 dark:border-[#262833] rounded-2xl w-full max-w-sm shadow-2xl" @click.stop>
        @csrf
        @include('casebook.partials.return-fields')
        <div class="flex justify-between p-5 border-b border-gray-200 dark:border-[#262833]">
            <h2 class="text-lg font-bold">Upload Document</h2>
            <button type="button" @click="docOpen = false" class="text-gray-500 dark:text-gray-400"><x-icon name="x" class="w-5 h-5" /></button>
        </div>
        <div class="p-6 space-y-4">
            @if (session('modal') === 'document' && $errors->any())
                <p class="text-sm text-red-500">{{ $errors->first() }}</p>
            @endif
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Folder</label>
                <select name="folder" x-model="docFolder" class="{{ $inputClass }}">
                    @foreach ($folders as $folder)
                        <option>{{ $folder }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Document Name</label>
                <input type="text" name="name" x-model="docName" placeholder="e.g., Affidavit.pdf" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">File</label>
                <input type="file" name="file" class="block w-full text-sm text-gray-500 dark:text-gray-400">
            </div>
            <button type="submit" class="w-full mt-4 py-3 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-bold">Save Document</button>
        </div>
    </form>
</div>
@endif

<div x-show="templateOpen" x-cloak class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <form method="POST" action="{{ route('templates.store') }}" class="bg-white dark:bg-[#1A1C23] border border-gray-200 dark:border-[#262833] rounded-2xl w-full max-w-sm shadow-2xl" @click.stop>
        @csrf
        @include('casebook.partials.return-fields')
        <div class="flex justify-between p-5 border-b border-gray-200 dark:border-[#262833]">
            <h2 class="text-lg font-bold">New Template</h2>
            <button type="button" @click="templateOpen = false" class="text-gray-500 dark:text-gray-400"><x-icon name="x" class="w-5 h-5" /></button>
        </div>
        <div class="p-6 space-y-4">
            @if (session('modal') === 'template' && $errors->any())
                <p class="text-sm text-red-500">{{ $errors->first() }}</p>
            @endif
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Template Name</label>
                <input type="text" name="title" x-model="templateTitle" placeholder="e.g., Bail Petition" class="{{ $inputClass }}">
            </div>
            <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-bold">Create Template</button>
        </div>
    </form>
</div>
