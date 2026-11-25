import 'package:material_ui/material_ui.dart';

import '../app_state.dart';
import '../models.dart';
import '../theme.dart';
import 'case_page.dart';
import 'chrome.dart';
import 'forms.dart';
import 'prices_page.dart';
import 'team_pages.dart';

enum _Screen { cases, templates, calendar }

class CasebookPage extends StatefulWidget {
  const CasebookPage({
    super.key,
    required this.state,
    required this.onSignedOut,
  });

  final AppState state;
  final VoidCallback onSignedOut;

  @override
  State<CasebookPage> createState() => _CasebookPageState();
}

class _CasebookPageState extends State<CasebookPage> {
  final search = TextEditingController();
  final scaffoldKey = GlobalKey<ScaffoldState>();
  _Screen screen = _Screen.cases;

  @override
  void initState() {
    super.initState();
    widget.state.addListener(_rebuild);
    if (widget.state.workspace == null) {
      widget.state.load();
    }
  }

  @override
  void dispose() {
    search.dispose();
    widget.state.removeListener(_rebuild);
    super.dispose();
  }

  void _rebuild() {
    if (mounted) {
      setState(() {});
    }
  }

  Future<void> _run(Future<void> action) async {
    try {
      await action;
    } on Exception catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }

  Future<void> _account() async {
    final signedOut = await showSettings(context, widget.state);
    if (signedOut) {
      widget.onSignedOut();
    }
  }

  void _openMenu() => scaffoldKey.currentState?.openDrawer();

  void _closeMenu() => scaffoldKey.currentState?.closeDrawer();

  @override
  Widget build(BuildContext context) {
    final state = widget.state;
    final workspace = state.workspace;

    return Scaffold(
      key: scaffoldKey,
      backgroundColor: Colors.white,
      drawer: Drawer(
        width: MediaQuery.sizeOf(context).width * 0.82,
        backgroundColor: Colors.white,
        child: SafeArea(
          child: workspace == null
              ? const SizedBox.shrink()
              : _Menu(
                  state: state,
                  search: search,
                  screen: screen,
                  onNewCase: () {
                    _closeMenu();
                    openNewCase(context, state);
                  },
                  onFilter: (filter) => _run(state.setFilter(filter)),
                  onTemplates: () {
                    _closeMenu();
                    setState(() => screen = _Screen.templates);
                  },
                  onPrices: () {
                    _closeMenu();
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => PricesPage(
                          api: state.api,
                          onUser: (user) {
                            state.user = user;
                            state.notifyListeners();
                          },
                        ),
                      ),
                    );
                  },
                  onPeople: () {
                    _closeMenu();
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => PeoplePage(api: state.api)),
                    );
                  },
                  onPermissions: () {
                    _closeMenu();
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => RolesPermissionsPage(api: state.api),
                      ),
                    );
                  },
                  onOpenCase: (item) {
                    _closeMenu();
                    setState(() => screen = _Screen.cases);
                    _run(state.load(caseId: item.id));
                  },
                ),
        ),
      ),
      bottomNavigationBar: _BottomNav(
        casesActive: screen != _Screen.calendar,
        calendarActive: screen == _Screen.calendar,
        onCases: () => setState(() => screen = _Screen.cases),
        onCalendar: () {
          setState(() => screen = _Screen.calendar);
          if (state.filter != 'All Cases') {
            _run(state.setFilter('All Cases'));
          }
        },
      ),
      body: SafeArea(
        bottom: false,
        child: workspace == null
            ? const Center(child: CircularProgressIndicator())
            : switch (screen) {
                _Screen.cases => CasePage(
                  key: ValueKey(workspace.active?.id ?? 0),
                  state: state,
                  onChanged: _rebuild,
                  onMenu: _openMenu,
                  onAccount: _account,
                ),
                _Screen.templates => _TemplatesScreen(
                  state: state,
                  onMenu: _openMenu,
                  onAccount: _account,
                  onRun: _run,
                ),
                _Screen.calendar => _CalendarScreen(
                  state: state,
                  onMenu: _openMenu,
                  onAccount: _account,
                  onOpen: (item) {
                    setState(() => screen = _Screen.cases);
                    _run(state.load(caseId: item.id));
                  },
                ),
              },
      ),
    );
  }
}

class _Menu extends StatelessWidget {
  const _Menu({
    required this.state,
    required this.search,
    required this.screen,
    required this.onNewCase,
    required this.onFilter,
    required this.onTemplates,
    required this.onPrices,
    required this.onPeople,
    required this.onPermissions,
    required this.onOpenCase,
  });

  final AppState state;
  final TextEditingController search;
  final _Screen screen;
  final VoidCallback onNewCase;
  final ValueChanged<String> onFilter;
  final VoidCallback onTemplates;
  final VoidCallback onPrices;
  final VoidCallback onPeople;
  final VoidCallback onPermissions;
  final ValueChanged<CaseSummary> onOpenCase;

  @override
  Widget build(BuildContext context) {
    final workspace = state.workspace!;
    final filters = <(String, String, IconData)>[
      ('All Cases', 'All Cases', Icons.folder_outlined),
      ("Today's Hearings", "Today's Cases", Icons.calendar_today_outlined),
      ('Upcoming', 'Upcoming', Icons.schedule),
    ];

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
      child: Column(
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Menu',
                  style: TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w700,
                    color: casebookText,
                  ),
                ),
              ),
              IconButton(
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.close, color: casebookText),
              ),
            ],
          ),
          const SizedBox(height: 8),
          PurpleButton(
            icon: Icons.add,
            label: 'New Case',
            onPressed: onNewCase,
          ),
          Container(
            width: double.infinity,
            margin: const EdgeInsets.only(bottom: 16),
            padding: const EdgeInsets.fromLTRB(15, 15, 15, 4),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: casebookLine),
            ),
            child: Column(
              children: [
                for (final filter in filters)
                  _StatRow(
                    icon: filter.$3,
                    label: filter.$2,
                    count: workspace.counts[filter.$1] ?? 0,
                    selected:
                        state.filter == filter.$1 && screen == _Screen.cases,
                    onTap: () => onFilter(filter.$1),
                  ),
                const Divider(height: 16),
                _StatRow(
                  icon: Icons.layers_outlined,
                  label: 'Saved Templates',
                  selected: screen == _Screen.templates,
                  accent: const Color(0xFFD97706),
                  onTap: onTemplates,
                ),
                _StatRow(
                  icon: Icons.currency_rupee,
                  label: 'Prices',
                  selected: false,
                  onTap: onPrices,
                ),
                _StatRow(
                  icon: Icons.group_outlined,
                  label: 'People Management',
                  selected: false,
                  onTap: onPeople,
                ),
                _StatRow(
                  icon: Icons.checklist,
                  label: 'Roles & Permissions',
                  selected: false,
                  onTap: onPermissions,
                ),
              ],
            ),
          ),
          TextField(
            controller: search,
            decoration: casebookField('Search cases').copyWith(
              prefixIcon: const Icon(Icons.search, color: casebookMuted),
              hintText: 'Search cases...',
              labelText: null,
            ),
            onChanged: state.setSearch,
          ),
          const SizedBox(height: 12),
          Expanded(
            child: ListView(
              children: [
                for (final item in state.visibleCases)
                  _CaseTile(
                    item: item,
                    selected:
                        item.id == workspace.active?.id &&
                        screen == _Screen.cases,
                    onTap: () => onOpenCase(item),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StatRow extends StatelessWidget {
  const _StatRow({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
    this.count,
    this.accent,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;
  final int? count;
  final Color? accent;

  @override
  Widget build(BuildContext context) {
    final color = selected ? (accent ?? casebookPurple) : casebookMuted;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Row(
          children: [
            Icon(icon, size: 16, color: color),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                label,
                style: TextStyle(
                  color: selected ? color : casebookText,
                  fontSize: 14,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                ),
              ),
            ),
            if (count != null)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: const Color(0xFFF3F4F6),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  '$count',
                  style: const TextStyle(
                    color: casebookText,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _CaseTile extends StatelessWidget {
  const _CaseTile({
    required this.item,
    required this.selected,
    required this.onTap,
  });

  final CaseSummary item;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: selected ? const Color(0xFFF5F3FF) : Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: selected ? casebookPurple : casebookLine),
        ),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(12),
          child: Padding(
            padding: const EdgeInsets.all(15),
            child: Row(
              children: [
                Icon(
                  Icons.folder,
                  size: 20,
                  color: selected ? casebookPurple : casebookBlue,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              item.title,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: casebookText,
                                fontSize: 15,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          PriorityLabel(priority: item.priority),
                        ],
                      ),
                      if (item.partiesLabel.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Text(
                            item.partiesLabel,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: casebookMuted,
                              fontSize: 12,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _BottomNav extends StatelessWidget {
  const _BottomNav({
    required this.casesActive,
    required this.calendarActive,
    required this.onCases,
    required this.onCalendar,
  });

  final bool casesActive;
  final bool calendarActive;
  final VoidCallback onCases;
  final VoidCallback onCalendar;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: casebookLine)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
      child: SafeArea(
        top: false,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: [
            _NavItem(
              icon: Icons.folder_outlined,
              label: 'Cases',
              active: casesActive,
              onTap: onCases,
            ),
            _NavItem(
              icon: Icons.calendar_today_outlined,
              label: 'Calendar',
              active: calendarActive,
              onTap: onCalendar,
            ),
          ],
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  const _NavItem({
    required this.icon,
    required this.label,
    required this.active,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = active ? Colors.white : casebookMuted;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 30, vertical: 8),
        decoration: BoxDecoration(
          color: active ? const Color(0xFF3730A3) : Colors.transparent,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 20, color: color),
            const SizedBox(height: 4),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TemplatesScreen extends StatelessWidget {
  const _TemplatesScreen({
    required this.state,
    required this.onMenu,
    required this.onAccount,
    required this.onRun,
  });

  final AppState state;
  final VoidCallback onMenu;
  final VoidCallback onAccount;
  final Future<void> Function(Future<void> action) onRun;

  @override
  Widget build(BuildContext context) {
    final templates = state.workspace?.templates ?? [];

    return ColoredBox(
      color: Colors.white,
      child: Column(
        children: [
          CasebookTopBar(
            title: 'Templates Library',
            onMenu: onMenu,
            onAvatar: onAccount,
            avatarLetter: avatarLetter(state),
            notifications: state.workspace?.notifications ?? const [],
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
              children: [
                PurpleButton(
                  icon: Icons.add,
                  label: 'New Template',
                  onPressed: () =>
                      onRun(openTemplateForm(context, state).then((_) {})),
                ),
                Row(
                  children: [
                    const Icon(
                      Icons.folder,
                      size: 20,
                      color: Color(0xFFD97706),
                    ),
                    const SizedBox(width: 10),
                    const Expanded(
                      child: Text(
                        'Templates',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                          color: casebookText,
                        ),
                      ),
                    ),
                    IconButton(
                      onPressed: () =>
                          onRun(openTemplateForm(context, state).then((_) {})),
                      icon: const Icon(
                        Icons.add_circle_outline,
                        color: casebookBlue,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                if (templates.isEmpty)
                  const Text(
                    'No saved templates yet.',
                    style: TextStyle(color: casebookMuted),
                  )
                else
                  for (final template in templates)
                    WhiteCard(
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(
                              template.title,
                              style: const TextStyle(
                                color: casebookText,
                                fontSize: 15,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          _MiniButton(
                            label: 'Edit',
                            onTap: () =>
                                openTemplateEditor(context, state, template),
                          ),
                          const SizedBox(width: 8),
                          _MiniButton(
                            label: 'View',
                            onTap: () async {
                              final file = await state.download(
                                template.exportUrl,
                                '${template.title}.doc',
                              );
                              if (context.mounted) {
                                showSavedFile(context, file);
                              }
                            },
                          ),
                        ],
                      ),
                    ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MiniButton extends StatelessWidget {
  const _MiniButton({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return TextButton(
      style: TextButton.styleFrom(
        backgroundColor: const Color(0xFFF3F4F6),
        foregroundColor: casebookText,
        minimumSize: Size.zero,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
      ),
      onPressed: onTap,
      child: Text(
        label,
        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
      ),
    );
  }
}

class _CalendarScreen extends StatefulWidget {
  const _CalendarScreen({
    required this.state,
    required this.onMenu,
    required this.onAccount,
    required this.onOpen,
  });

  final AppState state;
  final VoidCallback onMenu;
  final VoidCallback onAccount;
  final ValueChanged<CaseSummary> onOpen;

  @override
  State<_CalendarScreen> createState() => _CalendarScreenState();
}

class _CalendarScreenState extends State<_CalendarScreen> {
  late DateTime month = DateTime(DateTime.now().year, DateTime.now().month);
  String? selected;

  @override
  Widget build(BuildContext context) {
    final cases = widget.state.workspace?.cases ?? [];
    final firstWeekday = DateTime(month.year, month.month).weekday % 7;
    final days = DateTime(month.year, month.month + 1, 0).day;
    final cells = <int?>[
      for (var i = 0; i < firstWeekday; i++) null,
      for (var day = 1; day <= days; day++) day,
    ];
    const months = [
      'January',
      'February',
      'March',
      'April',
      'May',
      'June',
      'July',
      'August',
      'September',
      'October',
      'November',
      'December',
    ];
    final selectedCases = selected == null
        ? <CaseSummary>[]
        : cases.where((item) => item.nextHearing == selected).toList();

    return ColoredBox(
      color: Colors.white,
      child: Column(
        children: [
          CasebookTopBar(
            title: 'Calendar',
            onMenu: widget.onMenu,
            onAvatar: widget.onAccount,
            avatarLetter: avatarLetter(widget.state),
            notifications: widget.state.workspace?.notifications ?? const [],
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    IconButton(
                      onPressed: () => setState(
                        () => month = DateTime(month.year, month.month - 1),
                      ),
                      icon: const Icon(
                        Icons.chevron_left,
                        color: casebookPurple,
                      ),
                    ),
                    Text(
                      '${months[month.month - 1]} ${month.year}',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                        color: casebookText,
                      ),
                    ),
                    IconButton(
                      onPressed: () => setState(
                        () => month = DateTime(month.year, month.month + 1),
                      ),
                      icon: const Icon(
                        Icons.chevron_right,
                        color: casebookPurple,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    for (final label in ['S', 'M', 'T', 'W', 'T', 'F', 'S'])
                      Expanded(
                        child: Center(
                          child: Text(
                            label,
                            style: const TextStyle(
                              color: casebookMuted,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 8),
                GridView.count(
                  crossAxisCount: 7,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  children: [
                    for (final day in cells)
                      if (day == null)
                        const SizedBox.shrink()
                      else
                        _DayCell(
                          day: day,
                          count: cases
                              .where((item) => item.nextHearing == _date(day))
                              .length,
                          selected: selected == _date(day),
                          onTap: () => setState(() => selected = _date(day)),
                        ),
                  ],
                ),
                if (selected != null) ...[
                  const SizedBox(height: 16),
                  Text(
                    formatDay(selected),
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: casebookText,
                    ),
                  ),
                  const SizedBox(height: 10),
                  if (selectedCases.isEmpty)
                    const Text(
                      'No hearings on this day.',
                      style: TextStyle(color: casebookMuted),
                    )
                  else
                    for (final item in selectedCases)
                      WhiteCard(
                        child: InkWell(
                          onTap: () => widget.onOpen(item),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                item.title,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                  color: casebookText,
                                ),
                              ),
                              if (item.partiesLabel.isNotEmpty)
                                Padding(
                                  padding: const EdgeInsets.only(top: 4),
                                  child: Text(
                                    item.partiesLabel,
                                    style: const TextStyle(
                                      color: casebookMuted,
                                      fontSize: 12,
                                    ),
                                  ),
                                ),
                              Padding(
                                padding: const EdgeInsets.only(top: 4),
                                child: Text(
                                  item.court,
                                  style: const TextStyle(
                                    color: casebookMuted,
                                    fontSize: 12,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _date(int day) =>
      '${month.year.toString().padLeft(4, '0')}-${month.month.toString().padLeft(2, '0')}-${day.toString().padLeft(2, '0')}';
}

class _DayCell extends StatelessWidget {
  const _DayCell({
    required this.day,
    required this.count,
    required this.selected,
    required this.onTap,
  });

  final int day;
  final int count;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      customBorder: const CircleBorder(),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 32,
            height: 32,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: selected ? casebookPurple : Colors.white,
              shape: BoxShape.circle,
              border: Border.all(
                color: selected ? casebookPurple : casebookLine,
              ),
            ),
            child: Text(
              '$day',
              style: TextStyle(
                color: selected ? Colors.white : casebookText,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          const SizedBox(height: 2),
          Container(
            width: 5,
            height: 5,
            decoration: BoxDecoration(
              color: count > 0 ? casebookPurple : Colors.transparent,
              shape: BoxShape.circle,
            ),
          ),
        ],
      ),
    );
  }
}
