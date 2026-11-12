import Alpine from 'alpinejs';

window.casebookPage = function (config) {
    const old = config.old || {};

    return {
        theme: 'light',
        topMenuOpen: false,
        settingsOpen: false,
        centerMenuOpen: false,
        sidebarMenu: null,
        eventMenu: null,
        draftFullscreen: false,
        tab: config.tab || 'Timeline',
        searchSidebar: '',
        searchMain: '',
        newCaseOpen: config.modal === 'new-case',
        modalStep: config.modal === 'new-case' ? (config.modalStep || 1) : 1,
        state: old.state || '',
        district: old.district || '',
        court: old.court || '',
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
        docOpen: config.modal === 'document',
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
        openNewCase() {
            this.petitioners = [''];
            this.respondents = [''];
            this.state = '';
            this.district = '';
            this.court = '';
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
