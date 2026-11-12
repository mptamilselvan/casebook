@extends('layouts.casebook', ['title' => 'Sign in'])

@section('content')
<div class="min-h-full bg-white text-gray-800 flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md bg-white border border-gray-200 rounded-2xl shadow-sm p-8">
        <div class="flex items-center gap-3 mb-8">
            <x-icon name="scale" class="w-7 h-7 text-blue-600" />
            <div>
                <h1 class="text-xl font-bold leading-tight">Case Book</h1>
                <p class="text-sm text-gray-500">Sign in with your email</p>
            </div>
        </div>

        @if (session('status'))
            <p class="mb-5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3">
                {{ session('status') }}
            </p>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-xs font-semibold text-gray-500 mb-1">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="you@firm.com" pattern="^[^@\s]+@[^@\s]+\.[^@\s]+$" title="Enter an email with a dot after the @." oninput="this.setCustomValidity('')" oninvalid="this.setCustomValidity(this.validity.patternMismatch || this.validity.typeMismatch ? 'Enter an email with a dot after the @.' : '')" class="w-full bg-white border border-gray-300 rounded-lg p-3 text-sm text-gray-800 outline-none focus:border-blue-500">
                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white rounded-lg py-2.5 font-semibold text-sm">
                Continue
            </button>
        </form>
    </div>
</div>
@endsection
