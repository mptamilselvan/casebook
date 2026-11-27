@extends('layouts.casebook', ['title' => 'Choose a mode'])

@section('content')
<div class="min-h-full bg-white text-gray-800 flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md bg-white border border-gray-200 rounded-2xl shadow-sm p-8">
        <div class="flex items-center gap-3 mb-6">
            <img src="{{ asset('images/casebook-logo.png') }}" alt="" class="w-10 h-10 object-contain">
            <div>
                <h1 class="text-xl font-bold leading-tight">Case Book</h1>
                <p class="text-sm text-gray-500">Choose personal or office</p>
            </div>
        </div>

        <div class="space-y-3">
            @foreach (\App\Enums\CaseMode::cases() as $mode)
                <form method="POST" action="{{ route('mode.store') }}">
                    @csrf
                    <input type="hidden" name="mode" value="{{ $mode->value }}">
                    <button type="submit" class="w-full rounded-lg border px-4 py-3 text-left {{ $current === $mode ? 'border-blue-500 bg-blue-600/20' : 'border-gray-200 hover:bg-gray-100' }}">
                        <span class="block font-semibold text-sm">{{ $mode->label() }}</span>
                        <span class="block mt-1 text-xs text-gray-500">Show {{ strtolower($mode->label()) }} cases only</span>
                    </button>
                </form>
            @endforeach
        </div>

        @if ($current)
            <a href="{{ route('cases.index') }}" class="mt-5 inline-flex text-sm font-semibold text-blue-600">Back to cases</a>
        @endif
    </div>
</div>
@endsection
