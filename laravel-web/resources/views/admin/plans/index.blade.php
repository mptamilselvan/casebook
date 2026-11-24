@extends('admin.layout', ['title' => 'Plans & prices'])

@section('admin')
<div>
    <h2 class="text-2xl font-bold">Plans &amp; prices</h2>
    <p class="mt-1 text-sm text-gray-500">Prices are in Indian rupees (INR). Leave cases blank for unlimited.</p>
</div>

@if (session('status'))
    <p class="mt-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3">{{ session('status') }}</p>
@endif

<div class="mt-6 grid gap-4 lg:grid-cols-3">
    @foreach ($plans as $plan)
        @php
            $editing = (int) session('editing_plan') === $plan->id;
            $price = $plan->inrPrice();
            $field = function (string $key, mixed $default) use ($editing) {
                return $editing ? old($key, $default) : $default;
            };
        @endphp
        <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="border border-gray-200 rounded-2xl p-5">
            @csrf
            @method('PUT')
            <p class="text-xs font-semibold uppercase text-gray-500">{{ $plan->code }}</p>
            <label class="block mt-4 text-xs font-semibold text-gray-500" for="plan-name-{{ $plan->id }}">Plan name</label>
            <input id="plan-name-{{ $plan->id }}" type="text" name="name" value="{{ $field('name', $plan->name) }}" class="mt-1 w-full bg-white border border-gray-300 rounded-lg p-3 text-sm outline-none focus:border-blue-500">
            @if ($editing)
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            @endif

            <label class="block mt-4 text-xs font-semibold text-gray-500" for="plan-space-{{ $plan->id }}">Storage space (GB)</label>
            <input id="plan-space-{{ $plan->id }}" type="number" min="0" name="space_limit" value="{{ $field('space_limit', $plan->space_limit) }}" class="mt-1 w-full bg-white border border-gray-300 rounded-lg p-3 text-sm outline-none focus:border-blue-500">
            @if ($editing)
                @error('space_limit')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            @endif

            <label class="block mt-4 text-xs font-semibold text-gray-500" for="plan-cases-{{ $plan->id }}">Cases limit</label>
            <input id="plan-cases-{{ $plan->id }}" type="number" min="0" name="cases_limit" value="{{ $field('cases_limit', $plan->cases_limit) }}" placeholder="Unlimited" class="mt-1 w-full bg-white border border-gray-300 rounded-lg p-3 text-sm outline-none focus:border-blue-500">
            @if ($editing)
                @error('cases_limit')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            @endif

            <label class="block mt-4 text-xs font-semibold text-gray-500" for="plan-price-{{ $plan->id }}">Price (INR)</label>
            <input id="plan-price-{{ $plan->id }}" type="number" min="0" name="amount" value="{{ $field('amount', $price?->amount ?? 0) }}" class="mt-1 w-full bg-white border border-gray-300 rounded-lg p-3 text-sm outline-none focus:border-blue-500">
            @if ($editing)
                @error('amount')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            @endif

            <button type="submit" class="mt-5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg px-4 py-2.5 font-semibold text-sm">Save</button>
        </form>
    @endforeach
</div>
@endsection
