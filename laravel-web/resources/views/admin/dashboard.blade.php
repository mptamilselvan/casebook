@extends('admin.layout', ['title' => 'Admin'])

@section('admin')
<h2 class="text-2xl font-bold">Hello, {{ auth()->user()->name }}</h2>
<p class="mt-1 text-sm text-gray-500">{{ auth()->user()->email }}</p>

<div class="mt-8 grid gap-4 sm:grid-cols-2">
    <section class="border border-gray-200 rounded-2xl p-6">
        <p class="text-sm text-gray-500">Users</p>
        <p class="mt-2 text-3xl font-bold">{{ $userCount }}</p>
    </section>
    <section class="border border-gray-200 rounded-2xl p-6">
        <p class="text-sm text-gray-500">Cases</p>
        <p class="mt-2 text-3xl font-bold">{{ $caseCount }}</p>
    </section>
</div>
@endsection
