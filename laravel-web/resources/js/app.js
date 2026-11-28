import Alpine from 'alpinejs';

window.casebookPage = function (config) {
    const old = config.old || {};

    return {
        theme: 'light',
        topMenuOpen: false,
        settingsOpen: false,
        centerMenuOpen: false,
        notificationsOpen: false,
        deleteCaseOpen: false,
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
        area: old.area || '',
        stateQuery: old.state || '',
        districtQuery: old.district || '',
        areaQuery: old.area || '',
        courtQuery: '',
        stateMenu: false,
        districtMenu: false,
        areaMenu: false,
        courtMenu: false,
        courtChoice: '',
        courtOther: '',
        caseNumber: old.case_number || '',
        caseYear: String(old.case_year || new Date().getFullYear()),
        caseTypeId: String(old.case_type_id || ''),
        courtType: old.court_type || '',
        p1Role: old.p1 || 'Petitioner',
        p2Role: old.p2 || 'Respondent',
        petitioners: Array.isArray(old.petitioners) && old.petitioners.length ? old.petitioners : [''],
        respondents: Array.isArray(old.respondents) && old.respondents.length ? old.respondents : [''],
        counsel: Array.isArray(old.counsel) ? old.counsel.filter((name) => String(name || '').trim() !== '') : [],
        editOpen: config.modal === 'edit-case',
        edit: config.edit || { id: '', title: '', court: '', mode: 'personal' },
        eventOpen: config.modal === 'event',
        docOpen: config.modal === 'document',
        eventForm: config.event || {
            id: '',
            date: config.today,
            title: '',
            description: '',
            isHearing: false,
            fileLabel: 'No file',
        },
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
            if (config.modal === 'event') {
                this.tab = 'Timeline';
            }
            if (config.modal === 'document') {
                this.tab = 'Documents';
            }
        },
        openDocument() {
            this.docName = '';
            this.docFolder = 'Pleadings';
            this.docOpen = true;
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
        districtAreas() {
            return (this.stateDistricts().find((item) => item.name === this.district) || {}).areas || [];
        },
        courtReady() {
            if (this.district === '') {
                return false;
            }
            return this.districtAreas().length === 0 || this.area !== '';
        },
        stateCourts() {
            const district = this.stateDistricts().find((item) => item.name === this.district) || {};
            const base = district.courts || [];
            const areas = district.areas || [];
            if (areas.length === 0) {
                return base;
            }
            const area = areas.find((item) => item.name === this.area);
            if (!area) {
                return [];
            }
            return base.concat(area.courts || []);
        },
        filteredStates() {
            const query = this.stateQuery.trim().toLowerCase();
            return this.locations.filter((item) => query === '' || item.name.toLowerCase().includes(query));
        },
        filteredDistricts() {
            const query = this.districtQuery.trim().toLowerCase();
            return this.stateDistricts().filter((item) => query === '' || item.name.toLowerCase().includes(query));
        },
        filteredAreas() {
            const query = this.areaQuery.trim().toLowerCase();
            return this.districtAreas().filter((item) => query === '' || item.name.toLowerCase().includes(query));
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
            this.areaQuery = '';
            this.courtQuery = '';
        },
        chooseArea(name) {
            this.area = name;
            this.areaQuery = name;
            this.areaMenu = false;
            this.selectArea();
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
            this.area = '';
            this.courtChoice = '';
            this.courtOther = '';
        },
        selectDistrict() {
            this.area = '';
            this.courtChoice = '';
            this.courtOther = '';
        },
        selectArea() {
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
            this.area = '';
            this.stateQuery = '';
            this.districtQuery = '';
            this.areaQuery = '';
            this.courtQuery = '';
            this.stateMenu = false;
            this.districtMenu = false;
            this.areaMenu = false;
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
                mode: caseData.mode || 'personal',
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
        counselChoices() {
            const names = [...this.petitioners, ...this.respondents]
                .map((name) => String(name || '').trim())
                .filter((name) => name !== '');
            return [...new Set(names)];
        },
        toggleCounsel(name) {
            if (this.counsel.includes(name)) {
                this.counsel = this.counsel.filter((item) => item !== name);
                return;
            }
            this.counsel = [...this.counsel, name];
        },
        selectedCounsel() {
            const choices = this.counselChoices();
            return this.counsel.filter((name) => choices.includes(name));
        },
    };
};

window.Alpine = Alpine;
Alpine.start();
