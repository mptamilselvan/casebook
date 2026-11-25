@extends('layouts.casebook', ['title' => 'People Management'])

@php
    $editingId = (int) old('person_id');
    $startMode = $errors->any() ? ($editingId ? 'edit' : 'add') : 'list';
@endphp

@section('content')
@include('casebook.partials.frame-open')
<div class="bg-white text-gray-800" x-data="{ mode: '{{ $startMode }}', editing: {{ $editingId ?: 'null' }} }">
    <div class="p-6 max-w-6xl">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">People Management</h1>
                <p class="mt-1 text-sm text-gray-500">People on your account.</p>
            </div>
            <button type="button" x-show="mode === 'list'" @click="mode = 'add'" class="bg-blue-600 hover:bg-blue-500 text-white rounded-lg px-4 py-3 font-semibold text-sm">Add person</button>
        </div>

        @if (session('status'))
            <p class="mt-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">{{ $errors->first() }}</p>
        @endif

        <div x-show="mode === 'list'" class="mt-6 border border-gray-200 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Email</th>
                        <th class="px-4 py-3 font-semibold">Role</th>
                        <th class="px-4 py-3 font-semibold">Cases</th>
                        <th class="px-4 py-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($people as $person)
                        <tr class="border-t border-gray-200">
                            <td class="px-4 py-3">{{ $person->email }}</td>
                            <td class="px-4 py-3">{{ $roles[$person->role] ?? $person->role }}</td>
                            <td class="px-4 py-3">
                                @if ($person->assignedCases->isEmpty())
                                    <span class="text-gray-500">No cases</span>
                                @else
                                    <span class="block truncate" style="max-width: 16rem;">{{ $person->assignedCases->pluck('title')->filter()->join(', ') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="inline-flex items-center gap-2">
                                    <button type="button" @click="mode = 'edit'; editing = {{ $person->id }}" class="rounded-lg bg-blue-600 hover:bg-blue-500 text-white px-3 py-2 text-sm font-semibold">Edit</button>
                                    <form method="POST" action="{{ route('people.destroy', $person) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-3 py-2 text-sm font-semibold">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="border-t border-gray-200">
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">No people yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('people.store') }}" x-show="mode === 'add'" x-cloak class="mt-6 space-y-4 rounded-2xl border border-gray-200 p-4">
            @csrf
            <h2 class="text-lg font-bold">Add person</h2>
            <div>
                <label for="person-email" class="block text-xs font-semibold text-gray-500 mb-1">Email</label>
                <input id="person-email" type="email" name="email" value="{{ old('person_id') ? '' : old('email') }}" placeholder="name@firm.com" class="w-full bg-white border border-gray-300 rounded-lg p-3 text-sm outline-none focus:border-blue-500" :required="mode === 'add'">
            </div>
            <div>
                <label for="person-role" class="block text-xs font-semibold text-gray-500 mb-1">Role</label>
                <select id="person-role" name="role" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-3 text-sm outline-none focus:border-blue-500">
                    @foreach ($roles as $slug => $label)
                        <option value="{{ $slug }}" @selected(old('role', 'member') === $slug)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @include('casebook.partials.case-picker', [
                'cases' => $cases,
                'selectedIds' => [],
                'rememberOld' => ! old('person_id'),
            ])
            <div class="flex items-center gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white rounded-lg px-4 py-3 font-semibold text-sm">Add person</button>
                <button type="button" @click="mode = 'list'" class="rounded-lg border border-gray-300 px-4 py-3 text-sm font-semibold text-gray-600">Cancel</button>
            </div>
        </form>

        @foreach ($people as $person)
            <form method="POST" action="{{ route('people.update', $person) }}" x-show="mode === 'edit' && editing === {{ $person->id }}" x-cloak class="mt-6 space-y-4 rounded-2xl border border-gray-200 p-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="person_id" value="{{ $person->id }}">
                <h2 class="text-lg font-bold">Edit person</h2>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1" for="email-{{ $person->id }}">Email</label>
                    <input id="email-{{ $person->id }}" type="email" name="email" value="{{ (int) old('person_id') === $person->id ? old('email', $person->email) : $person->email }}" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500" :required="mode === 'edit' && editing === {{ $person->id }}">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1" for="role-{{ $person->id }}">Role</label>
                    <select id="role-{{ $person->id }}" name="role" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500">
                        @foreach ($roles as $slug => $label)
                            <option value="{{ $slug }}" @selected(((int) old('person_id') === $person->id ? old('role', $person->role) : $person->role) === $slug)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @include('casebook.partials.case-picker', [
                    'cases' => $cases,
                    'selectedIds' => (int) old('person_id') === $person->id ? old('case_ids', $person->assignedCases->pluck('id')->all()) : $person->assignedCases->pluck('id')->all(),
                    'rememberOld' => false,
                ])
                <div class="flex items-center gap-3">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white rounded-lg px-4 py-3 font-semibold text-sm">Save changes</button>
                    <button type="button" @click="mode = 'list'" class="rounded-lg border border-gray-300 px-4 py-3 text-sm font-semibold text-gray-600">Cancel</button>
                </div>
            </form>
        @endforeach
    </div>
</div>
@include('casebook.partials.frame-close')
@endsection
