@extends('layouts.casebook', ['title' => 'Prices'])

@section('content')
<div class="min-h-full bg-white text-gray-800">
    <div class="max-w-5xl mx-auto px-6 py-8">
        <a href="{{ route('cases.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-500">Back to cases</a>
        <div class="mt-6 flex items-center gap-3">
            <img src="{{ asset('images/casebook-logo.png') }}" alt="" class="w-9 h-9 object-contain">
            <div>
                <h1 class="text-2xl font-bold">Prices</h1>
                <p class="text-sm text-gray-500">Prices are in Indian rupees (INR).</p>
            </div>
        </div>

        @if (session('status'))
            <p class="mt-6 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3">{{ session('status') }}</p>
        @endif
        <p id="plan-payment-error" class="mt-6 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3" style="display:none"></p>

        <div class="mt-8 grid gap-4 md:grid-cols-3">
            @foreach ($plans as $plan)
                @php
                    $price = $plan->inrPrice();
                    $current = $currentPlanId === $plan->id;
                @endphp
                <article class="rounded-2xl border shadow-sm {{ $current ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white' }} p-6 flex flex-col">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold">{{ $plan->name }}</h2>
                        @if ($current)
                            <span class="text-xs font-semibold text-blue-600">Your plan</span>
                        @endif
                    </div>
                    <div class="mt-5 rounded-2xl border {{ $current ? 'bg-white border-blue-200' : 'bg-gray-50 border-gray-200' }} px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">INR</p>
                        <p class="mt-1 text-4xl font-bold tracking-tight text-gray-900"><span class="text-blue-600">₹</span>{{ number_format($price?->amount ?? 0) }}</p>
                    </div>
                    <ul class="mt-6 space-y-2 text-sm text-gray-600">
                        <li>{{ $plan->space_limit }} GB storage</li>
                        <li>{{ $plan->cases_limit === null ? 'Unlimited cases' : $plan->cases_limit.' cases' }}</li>
                    </ul>
                    @unless ($current)
                        <button type="button" data-checkout="{{ route('plans.checkout', $plan) }}" class="mt-6 w-full bg-blue-600 hover:bg-blue-500 text-white rounded-lg py-2.5 font-semibold text-sm" onclick="applyPlan(this)">Apply · {{ $price?->label() ?? '₹0' }}</button>
                    @endunless
                </article>
            @endforeach
        </div>
        <p class="mt-6 text-sm text-gray-500">Apply opens Razorpay. You can pay by UPI, QR code, net banking, or debit and credit card.</p>
    </div>
</div>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    function showPlanError(message) {
        const el = document.getElementById('plan-payment-error');
        if (!el) {
            return;
        }
        el.textContent = message;
        el.style.display = message ? 'block' : 'none';
    }

    async function applyPlan(button) {
        showPlanError('');
        button.disabled = true;
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const response = await fetch(button.dataset.checkout, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
            },
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            showPlanError(body.message || 'Payment could not be started.');
            button.disabled = false;
            return;
        }
        if (body.status === 'paid') {
            window.location.reload();
            return;
        }
        const checkout = new Razorpay({
            key: body.key,
            amount: body.amount,
            currency: body.currency,
            name: body.name,
            description: body.description,
            order_id: body.order_id,
            prefill: { email: body.email, name: body.customer_name },
            theme: { color: '#2563eb' },
            handler: async function (payment) {
                const verify = await fetch(@json(route('plans.verify')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify(payment),
                });
                const result = await verify.json().catch(() => ({}));
                if (!verify.ok) {
                    showPlanError(result.message || 'Payment could not be confirmed.');
                    button.disabled = false;
                    return;
                }
                window.location.reload();
            },
            modal: { ondismiss: function () { button.disabled = false; } },
        });
        checkout.on('payment.failed', function (failure) {
            showPlanError(failure.error && failure.error.description ? failure.error.description : 'Payment was not completed.');
            button.disabled = false;
        });
        checkout.open();
    }
</script>
@endsection
