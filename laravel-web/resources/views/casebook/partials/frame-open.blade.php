<div
    x-data="{
        topMenuOpen: false,
        sidebarMenu: null,
        centerMenuOpen: false,
        notificationsOpen: false,
        eventMenu: null,
        settingsOpen: false,
        searchSidebar: '',
        tab: 'Timeline',
        matches(haystack) {
            const query = this.searchSidebar.trim().toLowerCase();
            return query === '' || String(haystack).includes(query);
        },
        caseUrl(base) {
            return base;
        },
        openEdit(data) {
            const url = new URL(@js(route('cases.index')), window.location.origin);
            url.searchParams.set('case', data.id);
            window.location = url.toString();
        }
    }"
    @click="topMenuOpen = false; sidebarMenu = null"
    class="flex h-screen w-full overflow-hidden bg-white text-gray-800"
>
    @include('casebook.partials.sidebar')
    <main class="flex-1 min-w-0 h-screen overflow-y-auto">
