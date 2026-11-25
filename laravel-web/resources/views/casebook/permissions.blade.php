@extends('layouts.casebook', ['title' => 'Roles & Permissions'])

@section('content')
@include('casebook.partials.frame-open')
<div class="bg-white text-gray-800">
    <div class="p-6 max-w-6xl">
        <div>
            <h1 class="text-2xl font-bold">Roles &amp; Permissions</h1>
            <p class="mt-1 text-sm text-gray-500">
                @if ($can_edit)
                    Choose a role and set what your people with that role can do.
                @else
                    These permissions are set by the account that added you.
                @endif
            </p>
        </div>

        @if (session('status'))
            <p class="mt-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3">{{ session('status') }}</p>
        @endif

        <div class="mt-6 flex items-start gap-6">
            <nav class="shrink-0 border border-gray-200 rounded-2xl overflow-hidden" style="width: 11rem">
                @foreach ($roles as $role)
                    <a href="{{ route('team.permissions.index', ['role' => $role->slug]) }}" class="block px-4 py-3 text-sm font-semibold border-b border-gray-200 {{ $selected->is($role) ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">
                        {{ $role->name }}
                    </a>
                @endforeach
            </nav>

            <form method="POST" action="{{ route('team.permissions.update') }}" class="min-w-0 flex-1 border border-gray-200 rounded-2xl overflow-x-auto">
                @csrf
                @method('PUT')
                <input type="hidden" name="role_id" value="{{ $selected->id }}">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <h2 class="font-bold">{{ $selected->name }}</h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Area</th>
                            @foreach ($actions as $action)
                                <th class="px-4 py-3 font-semibold capitalize">{{ $action }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $group => $label)
                            <tr class="border-t border-gray-200">
                                <td class="px-4 py-3 font-semibold text-gray-800">{{ $label }}</td>
                                @foreach ($actions as $action)
                                    @php
                                        $permission = ($permissions[$group] ?? collect())->firstWhere('action', $action);
                                    @endphp
                                    <td class="px-4 py-3">
                                        @if ($permission)
                                            <input type="checkbox" name="grants[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $selected->grant_ids ?? [], true)) @disabled(! $can_edit) aria-label="{{ $selected->name }} {{ $label }} {{ $action }}">
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($can_edit)
                    <div class="px-4 py-4 border-t border-gray-200">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white rounded-lg px-4 py-2.5 font-semibold text-sm">Save permissions</button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@include('casebook.partials.frame-close')
@endsection
