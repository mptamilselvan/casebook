@extends('admin.layout', ['title' => 'Permissions'])

@section('admin')
<div>
    <h2 class="text-2xl font-bold">Permissions</h2>
    <p class="mt-1 text-sm text-gray-500">Each area can be viewed, created, edited, or deleted.</p>
</div>

<div class="mt-6 border border-gray-200 rounded-2xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
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
                        <td class="px-4 py-3 text-gray-600 capitalize">
                            @if (($permissions[$group] ?? collect())->firstWhere('action', $action))
                                {{ $action }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
