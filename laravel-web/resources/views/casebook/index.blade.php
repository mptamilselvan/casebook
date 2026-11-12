@extends('layouts.casebook')

@section('content')
@php
    $sideOne = $active ? $active->caseParties->where('side', 1)->values() : collect();
    $sideTwo = $active ? $active->caseParties->where('side', 2)->values() : collect();
    $inputClass = 'w-full bg-gray-50 dark:bg-[#16181D] border border-gray-300 dark:border-[#2A2D39] rounded-lg p-3 text-sm text-gray-800 dark:text-gray-200 outline-none focus:border-blue-500';
    $menuClass = 'bg-white dark:bg-[#1A1C23] border border-gray-200 dark:border-[#262833] rounded-md shadow-xl z-50 py-1';
    $menuItemClass = 'w-full text-left px-4 py-2 flex items-center gap-2 hover:bg-gray-100 dark:hover:bg-[#20222B]';
    $alpine = [
        'tab' => $tab,
        'today' => $today,
        'modal' => session('modal'),
        'modalStep' => (int) session('modal_step', 1),
        'templateId' => $activeTemplate?->id,
        'edit' => session('edit_case'),
        'eventHaystacks' => $active ? $active->events->map(fn ($event) => strtolower($event->title.' '.$event->description))->values() : [],
        'documentHaystacks' => $active ? $active->documents->map(fn ($document) => strtolower($document->name.' '.$document->folder))->values() : [],
        'event' => session('modal') === 'event' ? [
            'id' => old('event_id', ''),
            'date' => old('event_date', $today),
            'title' => old('title', ''),
            'description' => old('description', ''),
            'isHearing' => (bool) old('is_hearing'),
            'fileLabel' => 'No file',
        ] : null,
        'old' => [
            'state' => old('state', ''),
            'district' => old('district', ''),
            'court' => old('court', ''),
            'case_number' => old('case_number', ''),
            'case_year' => old('case_year', now()->year),
            'p1' => old('party1_role', 'Petitioner'),
            'p2' => old('party2_role', 'Respondent'),
            'petitioners' => array_values(is_array(old('petitioners')) ? old('petitioners') : ['']),
            'respondents' => array_values(is_array(old('respondents')) ? old('respondents') : ['']),
            'doc_name' => session('modal') === 'document' ? old('name', '') : '',
            'doc_folder' => session('modal') === 'document' ? old('folder', 'Pleadings') : 'Pleadings',
            'template_title' => session('modal') === 'template' ? old('title', '') : '',
        ],
    ];
@endphp

<div x-data="casebookPage(@js($alpine))" @click="topMenuOpen = false; centerMenuOpen = false; sidebarMenu = null; eventMenu = null" class="flex h-screen w-full bg-white text-gray-800 font-sans overflow-hidden">
    @include('casebook.partials.sidebar')
    @include('casebook.partials.main')
    @include('casebook.partials.modals')

    @if (session('status'))
        <div x-data x-init="setTimeout(() => $el.remove(), 2400)" class="fixed bottom-4 right-4 z-50 bg-emerald-600 text-white px-4 py-2 rounded-lg shadow-lg text-sm">
            {{ session('status') }}
        </div>
    @endif
</div>
@endsection
