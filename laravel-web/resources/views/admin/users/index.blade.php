@extends('admin.layout', ['title' => 'Users Management'])

@section('admin')
<div class="flex items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold">Users Management</h2>
        <p class="mt-1 text-sm text-gray-500">
            @if ($users->total() === 0)
                No users
            @else
                Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }}
            @endif
        </p>
    </div>
</div>

<form method="GET" action="{{ route('admin.users.index') }}" class="mt-6 flex items-center gap-2">
    <input type="search" name="search" value="{{ $search }}" placeholder="Search by email" class="min-w-0 flex-1 max-w-sm bg-white border border-gray-300 rounded-lg p-3 text-sm text-gray-800 outline-none focus:border-blue-500">
    <button type="submit" class="shrink-0 bg-blue-600 hover:bg-blue-500 text-white rounded-lg px-4 py-2.5 font-semibold text-sm">Search</button>
    @if ($search !== '')
        <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">Clear</a>
    @endif
</form>

@if (session('status'))
    <p class="mt-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3">{{ session('status') }}</p>
@endif
@error('user')
    <p class="mt-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">{{ $message }}</p>
@enderror

<div class="mt-6 border border-gray-200 rounded-2xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-4 py-3 font-semibold">Email</th>
                <th class="px-4 py-3 font-semibold">Plan</th>
                <th class="px-4 py-3 font-semibold">Storage</th>
                <th class="px-4 py-3 font-semibold">Cases</th>
                <th class="px-4 py-3 font-semibold">Created</th>
                <th class="px-4 py-3 font-semibold">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr class="border-t border-gray-200">
                    <td class="px-4 py-3 text-gray-800 break-all">{{ $user->email }}</td>
                    <td class="px-4 py-3 text-gray-800 whitespace-nowrap">{{ $user->plan?->name ?? $user->plan_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ \App\Support\Casebook::sizeLabel((int) ($user->usage?->storage_bytes ?? 0)) }}</td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $user->usage?->cases_count ?? 0 }}</td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $user->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <form method="POST" action="{{ route('admin.users.status', $user) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="is_enabled" value="{{ $user->is_enabled ? '0' : '1' }}">
                            @if ($user->is_enabled)
                                <button type="submit" class="border border-red-200 text-red-700 rounded-lg px-3 py-1.5 font-semibold text-sm hover:bg-red-50">Disable</button>
                            @else
                                <button type="submit" class="border border-emerald-200 text-emerald-700 rounded-lg px-3 py-1.5 font-semibold text-sm hover:bg-emerald-50">Enable</button>
                            @endif
                        </form>
                    </td>
                </tr>
            @empty
                <tr class="border-t border-gray-200">
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No users match that search.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($users->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-3 text-sm">
        @if ($users->onFirstPage())
            <span class="text-gray-400 font-semibold">Previous</span>
        @else
            <a href="{{ $users->previousPageUrl() }}" class="font-semibold text-gray-700 hover:text-gray-900">Previous</a>
        @endif
        <span class="text-gray-500">Page {{ $users->currentPage() }} of {{ $users->lastPage() }}</span>
        @if ($users->hasMorePages())
            <a href="{{ $users->nextPageUrl() }}" class="font-semibold text-gray-700 hover:text-gray-900">Next</a>
        @else
            <span class="text-gray-400 font-semibold">Next</span>
        @endif
    </nav>
@endif
@endsection
