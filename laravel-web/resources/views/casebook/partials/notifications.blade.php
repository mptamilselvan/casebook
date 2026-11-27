<div class="relative" @click.outside.capture="notificationsOpen = false">
    <button type="button" @click.stop="notificationsOpen = !notificationsOpen; topMenuOpen = false; centerMenuOpen = false; sidebarMenu = null; eventMenu = null" class="relative p-1.5 rounded-md border border-gray-200 dark:border-[#262833] hover:bg-gray-100 dark:hover:bg-[#20222B]" aria-label="Notifications">
        <x-icon name="bell" class="w-5 h-5 text-gray-500 dark:text-gray-400" />
        @if (count($notifications) > 0)
            <span class="absolute bg-red-500 text-white text-[10px] font-bold rounded-full px-1 leading-4" style="top:-6px;right:-6px">{{ count($notifications) > 99 ? '99+' : count($notifications) }}</span>
        @endif
    </button>
    <div x-show="notificationsOpen" x-cloak @click.stop class="absolute right-0 top-11 overflow-y-auto {{ $menuClass }}" style="width:20rem;max-height:24rem">
        <p class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Notifications</p>
        @if (config('services.onesignal.app_id'))
            <button type="button" id="casebook-enable-push" onclick="window.casebookEnablePush && window.casebookEnablePush()" class="w-full text-left px-4 py-2 text-sm font-semibold text-blue-600 border-t border-gray-100">Enable browser notifications</button>
        @endif
        @forelse ($notifications as $notification)
            <div class="px-4 py-3 border-t border-gray-100 dark:border-[#262833]">
                <p class="text-xs text-gray-500">{{ $notification['date'] }}</p>
                <p class="mt-0.5 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $notification['title'] }}</p>
            </div>
        @empty
            <p class="px-4 py-3 text-sm text-gray-500">No notifications</p>
        @endforelse
    </div>
</div>
