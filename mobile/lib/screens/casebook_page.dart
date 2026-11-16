import 'package:material_ui/material_ui.dart';

import '../app_state.dart';
import '../models.dart';
import '../theme.dart';
import 'case_page.dart';
import 'forms.dart';

class CasebookPage extends StatefulWidget {
  const CasebookPage({super.key, required this.state, required this.onSignedOut});

  final AppState state;
  final VoidCallback onSignedOut;

  @override
  State<CasebookPage> createState() => _CasebookPageState();
}

class _CasebookPageState extends State<CasebookPage> {
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
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }

  Future<void> _openCase(CaseSummary item, bool wide) async {
    await _run(widget.state.load(caseId: item.id));
    if (!wide && mounted && widget.state.workspace?.active != null) {
      await Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => CasePage(key: ValueKey(widget.state.workspace?.active?.id), state: widget.state, onChanged: _rebuild),
      ));
      if (mounted) {
        setState(() {});
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final wide = MediaQuery.sizeOf(context).width >= 900;
    final state = widget.state;
    final workspace = state.workspace;

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: wide
          ? null
          : AppBar(
              title: const Text('Case Book'),
              actions: [
                IconButton(onPressed: () => _settings(), icon: const Icon(Icons.more_vert)),
              ],
            ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: casebookBlue,
        foregroundColor: Colors.white,
        onPressed: workspace == null ? null : () => openNewCase(context, state),
        icon: const Icon(Icons.add),
        label: const Text('New Case'),
      ),
      body: workspace == null
          ? const Center(child: CircularProgressIndicator())
          : Row(
              children: [
                SizedBox(
                  width: wide ? 320 : MediaQuery.sizeOf(context).width,
                  child: _Sidebar(
                    state: state,
                    wide: wide,
                    onOpen: (item) => _openCase(item, wide),
                    onSettings: _settings,
                  ),
                ),
                if (wide)
                  Expanded(
                    child: workspace.active == null
                        ? const Center(child: Text('No cases yet', style: TextStyle(color: casebookMuted)))
                        : CasePage(key: ValueKey(workspace.active?.id), state: state, embedded: true, onChanged: _rebuild),
                  ),
              ],
            ),
    );
  }

  Future<void> _settings() async {
    final signedOut = await showSettings(context, widget.state);
    if (signedOut) {
      widget.onSignedOut();
    }
  }
}

class _Sidebar extends StatelessWidget {
  const _Sidebar({required this.state, required this.wide, required this.onOpen, required this.onSettings});

  final AppState state;
  final bool wide;
  final ValueChanged<CaseSummary> onOpen;
  final VoidCallback onSettings;

  @override
  Widget build(BuildContext context) {
    final workspace = state.workspace!;
    final activeId = workspace.active?.id;

    return DecoratedBox(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(right: BorderSide(color: casebookLine)),
      ),
      child: Column(
        children: [
          if (wide)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 8, 8),
              child: Row(
                children: [
                  const Icon(Icons.balance, color: casebookBlue),
                  const SizedBox(width: 8),
                  const Expanded(child: Text('Case Book', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700))),
                  IconButton(onPressed: onSettings, icon: const Icon(Icons.more_vert)),
                ],
              ),
            ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Column(
              children: [
                for (final filter in workspace.filters)
                  _FilterTile(
                    label: filter,
                    count: workspace.counts[filter] ?? 0,
                    selected: state.filter == filter,
                    icon: _filterIcon(filter),
                    onTap: () => state.setFilter(filter),
                  ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              decoration: casebookField('Search cases'),
              onChanged: state.setSearch,
            ),
          ),
          const Align(
            alignment: Alignment.centerLeft,
            child: Padding(
              padding: EdgeInsets.fromLTRB(20, 8, 16, 4),
              child: Text('ALL CASES', style: TextStyle(fontSize: 11, letterSpacing: 0.6, color: casebookMuted, fontWeight: FontWeight.w700)),
            ),
          ),
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.only(bottom: 88),
              itemCount: state.visibleCases.length,
              itemBuilder: (context, index) {
                final item = state.visibleCases[index];
                final selected = item.id == activeId;
                return InkWell(
                  onTap: () => onOpen(item),
                  child: Container(
                    margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: selected ? const Color(0xFFEFF6FF) : Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: selected ? const Color(0xFFBFDBFE) : casebookLine),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            if (item.isPinned) const Icon(Icons.star, size: 16, color: Color(0xFFEAB308)),
                            if (item.isPinned) const SizedBox(width: 4),
                            Expanded(
                              child: Text(item.title, style: TextStyle(fontWeight: FontWeight.w700, color: selected ? casebookBlue : casebookText)),
                            ),
                          ],
                        ),
                        if (item.partiesLabel.isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(top: 4),
                            child: Text(item.partiesLabel, style: const TextStyle(color: casebookMuted, fontSize: 13)),
                          ),
                        Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Text(item.court, style: const TextStyle(color: casebookMuted, fontSize: 12)),
                        ),
                        if (item.nextHearing != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Text(item.nextHearing!, style: const TextStyle(fontSize: 12, color: casebookText)),
                          ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  IconData _filterIcon(String filter) {
    switch (filter) {
      case "Today's Hearings":
        return Icons.calendar_today_outlined;
      case 'Upcoming':
        return Icons.schedule;
      case 'Starred':
        return Icons.star_border;
      default:
        return Icons.work_outline;
    }
  }
}

class _FilterTile extends StatelessWidget {
  const _FilterTile({required this.label, required this.count, required this.selected, required this.icon, required this.onTap});

  final String label;
  final int count;
  final bool selected;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      dense: true,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      selected: selected,
      selectedTileColor: const Color(0xFFDBEAFE),
      leading: Icon(icon, size: 18, color: selected ? casebookBlue : casebookMuted),
      title: Text(label, style: TextStyle(fontWeight: selected ? FontWeight.w700 : FontWeight.w500, color: selected ? casebookBlue : casebookMuted, fontSize: 14)),
      trailing: Text('$count', style: TextStyle(color: selected ? casebookBlue : casebookMuted, fontWeight: FontWeight.w700)),
      onTap: onTap,
    );
  }
}
