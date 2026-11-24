import Alpine from 'alpinejs';

window.casebookPage = function (config) {
    const old = config.old || {};

    return {
        theme: 'light',
        topMenuOpen: false,
        settingsOpen: false,
        centerMenuOpen: false,
        notificationsOpen: false,
        sidebarMenu: null,
        eventMenu: null,
        draftFullscreen: false,
        tab: config.tab || 'Timeline',
        searchSidebar: '',
        searchMain: '',
        newCaseOpen: config.modal === 'new-case',
        modalStep: config.modal === 'new-case' ? (config.modalStep || 1) : 1,
        locations: config.locations || [],
        state: old.state || '',
        district: old.district || '',
        stateQuery: old.state || '',
        districtQuery: old.district || '',
        courtQuery: '',
        stateMenu: false,
        districtMenu: false,
        courtMenu: false,
        courtChoice: '',
        courtOther: '',
        caseNumber: old.case_number || '',
        caseYear: String(old.case_year || new Date().getFullYear()),
        p1Role: old.p1 || 'Petitioner',
        p2Role: old.p2 || 'Respondent',
        petitioners: Array.isArray(old.petitioners) && old.petitioners.length ? old.petitioners : [''],
        respondents: Array.isArray(old.respondents) && old.respondents.length ? old.respondents : [''],
        editOpen: config.modal === 'edit-case',
        edit: config.edit || { id: '', title: '', court: '' },
        eventOpen: config.modal === 'event',
        eventForm: config.event || {
            id: '',
            date: config.today,
            title: '',
            description: '',
            isHearing: false,
            fileLabel: 'No file',
        },
        docOpen: false,
        docName: old.doc_name || '',
        docFolder: old.doc_folder || 'Pleadings',
        templateOpen: config.modal === 'template',
        templateTitle: old.template_title || '',
        viewerOpen: false,
        viewer: { name: '', url: '' },
        editingHearing: false,
        activeTemplateId: config.templateId || null,
        eventHaystacks: config.eventHaystacks || [],
        documentHaystacks: config.documentHaystacks || [],
        init() {
            this.applyTheme();
            this.syncCourtChoice();
            if (config.modal === 'add-event') {
                this.tab = 'Timeline';
                this.$nextTick(() => this.focusAddEvent());
            }
            if (config.modal === 'add-document') {
                this.tab = 'Documents';
                this.$nextTick(() => this.focusUploadDocument());
            }
        },
        focusAddEvent() {
            this.tab = 'Timeline';
            this.$nextTick(() => {
                document.getElementById('add-event-form')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                document.getElementById('add-event-title')?.focus();
            });
        },
        focusUploadDocument() {
            this.tab = 'Documents';
            this.$nextTick(() => {
                document.getElementById('upload-document-form')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                document.getElementById('upload-document-name')?.focus();
            });
        },
        applyTheme() {
            this.theme = 'light';
            localStorage.setItem('casebook-theme', 'light');
            document.documentElement.classList.remove('dark');
        },
        matches(haystack) {
            const query = this.searchSidebar.trim().toLowerCase();
            return query === '' || (haystack || '').toLowerCase().includes(query);
        },
        matchesMain(haystack) {
            const query = this.searchMain.trim().toLowerCase();
            return query === '' || (haystack || '').toLowerCase().includes(query);
        },
        hasVisible(list) {
            return (list || []).some((item) => this.matchesMain(item));
        },
        stateDistricts() {
            return (this.locations.find((item) => item.name === this.state) || {}).districts || [];
        },
        stateCourts() {
            return (this.stateDistricts().find((item) => item.name === this.district) || {}).courts || [];
        },
        filteredStates() {
            const query = this.stateQuery.trim().toLowerCase();
            return this.locations.filter((item) => query === '' || item.name.toLowerCase().includes(query));
        },
        filteredDistricts() {
            const query = this.districtQuery.trim().toLowerCase();
            return this.stateDistricts().filter((item) => query === '' || item.name.toLowerCase().includes(query));
        },
        filteredCourts() {
            const query = this.courtQuery.trim().toLowerCase();
            const selected = (this.courtChoice || '').trim().toLowerCase();
            const searching = query !== '' && query !== selected;
            const names = this.stateCourts().filter((name) => {
                return name.toLowerCase() !== 'other' && (!searching || name.toLowerCase().includes(query));
            });
            names.push('Other');
            return names;
        },
        courtValue() {
            if (this.courtChoice === 'Other') {
                return (this.courtOther || '').trim();
            }
            return this.courtChoice || '';
        },
        chooseState(name) {
            this.state = name;
            this.stateQuery = name;
            this.stateMenu = false;
            this.selectState();
            this.districtQuery = '';
            this.courtQuery = '';
        },
        chooseDistrict(name) {
            this.district = name;
            this.districtQuery = name;
            this.districtMenu = false;
            this.selectDistrict();
            this.courtQuery = '';
        },
        chooseCourt(name) {
            this.courtChoice = name;
            this.courtQuery = name;
            this.courtMenu = false;
            if (name !== 'Other') {
                this.courtOther = '';
            }
        },
        selectState() {
            this.district = '';
            this.courtChoice = '';
            this.courtOther = '';
        },
        selectDistrict() {
            this.courtChoice = '';
            this.courtOther = '';
        },
        syncCourtChoice() {
            const saved = (old.court || '').trim();
            const courts = this.stateCourts();
            const listed = courts.some((name) => name.toLowerCase() === saved.toLowerCase());
            if (saved !== '' && !listed) {
                this.courtChoice = 'Other';
                this.courtQuery = 'Other';
                this.courtOther = saved.toLowerCase() === 'other' ? '' : saved;
                return;
            }
            this.courtChoice = saved;
            this.courtQuery = saved;
            this.courtOther = '';
        },
        openNewCase() {
            this.petitioners = [''];
            this.respondents = [''];
            this.state = '';
            this.district = '';
            this.stateQuery = '';
            this.districtQuery = '';
            this.courtQuery = '';
            this.stateMenu = false;
            this.districtMenu = false;
            this.courtMenu = false;
            this.courtChoice = '';
            this.courtOther = '';
            this.caseNumber = '';
            this.caseYear = String(new Date().getFullYear());
            this.p1Role = 'Petitioner';
            this.p2Role = 'Respondent';
            this.modalStep = 1;
            this.newCaseOpen = true;
            this.topMenuOpen = false;
        },
        openEdit(caseData) {
            this.edit = {
                id: caseData.id,
                title: caseData.title,
                court: caseData.court,
            };
            this.editOpen = true;
            this.sidebarMenu = null;
            this.centerMenuOpen = false;
            this.topMenuOpen = false;
        },
        openEvent(data) {
            this.eventForm = {
                id: data?.id || '',
                date: data?.date || config.today,
                title: data?.title || '',
                description: data?.description || '',
                isHearing: !!data?.isHearing,
                fileLabel: data?.fileName || 'No file',
            };
            this.eventOpen = true;
            this.eventMenu = null;
            this.$nextTick(() => {
                if (this.$refs.eventFile) {
                    this.$refs.eventFile.value = '';
                }
            });
        },
        openViewer(name, url) {
            this.viewer = { name: name || '', url: url || '' };
            this.viewerOpen = true;
        },
        caseUrl(base) {
            return base + (base.includes('?') ? '&' : '?') + 'tab=' + encodeURIComponent(this.tab);
        },
    };
};

window.Alpine = Alpine;
Alpine.start();

const casebookPush = window.casebookPush;
if (casebookPush && casebookPush.appId && casebookPush.userId) {
    window.OneSignalDeferred = window.OneSignalDeferred || [];
    window.OneSignalDeferred.push(async (OneSignal) => {
        await OneSignal.init({
            appId: casebookPush.appId,
            serviceWorkerPath: 'OneSignalSDKWorker.js',
        });
        await OneSignal.login(String(casebookPush.userId));
        await OneSignal.Notifications.requestPermission();
    });
}
