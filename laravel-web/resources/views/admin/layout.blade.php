@extends('layouts.casebook', ['title' => $title ?? 'Admin'])

@section('content')
<div class="min-h-full bg-white text-gray-800 flex">
    <aside class="w-72 bg-white border-r border-gray-200 flex flex-col shrink-0">
        <div class="p-4 flex items-center gap-3">
            <img src="{{ asset('images/casebook-logo.png') }}" alt="" class="w-9 h-9 object-contain">
            <div>
                <h1 class="font-bold text-lg leading-tight">Case Book</h1>
                <p class="text-sm text-gray-500">Admin</p>
            </div>
        </div>

        <nav class="flex-1 px-3 space-y-1">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold {{ request()->routeIs('admin.dashboard') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">
                <x-icon name="database" class="w-4 h-4" />
                Dashboard
            </a>
            <a href="{{ route('admin.users.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold {{ request()->routeIs('admin.users.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">
                <x-icon name="users" class="w-4 h-4" />
                Users Management
            </a>
            <a href="{{ route('admin.plans.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold {{ request()->routeIs('admin.plans.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">
                <x-icon name="indian-rupee" class="w-4 h-4" />
                Plans &amp; prices
            </a>
        </nav>

        <form method="POST" action="{{ route('admin.logout') }}" class="p-4 border-t border-gray-200">
            @csrf
            <button type="submit" class="w-full text-left px-3 py-2 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-50">Sign out</button>
        </form>
    </aside>

    <main class="flex-1 min-w-0 px-8 py-8">
        @yield('admin')
    </main>
</div>
@endsection
