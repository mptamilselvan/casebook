import 'dart:io';

import 'package:material_ui/material_ui.dart';

import '../app_state.dart';
import '../models.dart';
import '../theme.dart';
import 'chrome.dart';
import 'forms.dart';

class CasePage extends StatefulWidget {
  const CasePage({
    super.key,
    required this.state,
    required this.onChanged,
    required this.onMenu,
    required this.onAccount,
  });

  final AppState state;
  final VoidCallback onChanged;
  final VoidCallback onMenu;
  final VoidCallback onAccount;

  @override
  State<CasePage> createState() => _CasePageState();
}

class _CasePageState extends State<CasePage> {
  static const tabs = ['Timeline', 'Documents', 'Parties', 'Tasks', 'Settings'];

  final page = PageController();
  final searchBox = TextEditingController();
  int tab = 0;
  bool searching = false;
  String query = '';

  CaseDetail? get active => widget.state.workspace?.active;

  @override
  void dispose() {
    page.dispose();
    searchBox.dispose();
    super.dispose();
  }

  Future<void> _run(Future<void> action) async {
    try {
      await action;
      widget.onChanged();
    } on Exception catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }

  Future<void> _openFile(String? path, String filename) async {
    if (path == null) {
      return;
    }
    try {
      final file = await widget.state.download(path, filename);
      if (mounted) {
        showSavedFile(context, file);
      }
    } on Exception catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }

  Future<void> _setHearing(CaseDetail item) async {
    final initial = DateTime.tryParse(item.nextHearing ?? '') ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
      initialDate: initial,
    );
    if (picked == null) {
      return;
    }
    final formatted =
        '${picked.year.toString().padLeft(4, '0')}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
    await _run(widget.state.updateHearing(item.id, formatted));
  }

  void _goTo(int index) {
    setState(() => tab = index);
    page.animateToPage(
      index,
      duration: const Duration(milliseconds: 250),
      curve: Curves.easeOut,
    );
  }

  @override
  Widget build(BuildContext context) {
    final item = active;

    return ColoredBox(
      color: Colors.white,
      child: Column(
        children: [
          CasebookTopBar(
            title: 'Case Book',
            onMenu: widget.onMenu,
            onAvatar: widget.onAccount,
            avatarLetter: avatarLetter(widget.state),
            notifications: widget.state.workspace?.notifications ?? const [],
            searching: searching,
            searchController: searchBox,
            searchHint: 'Search in ${tabs[tab]}...',
            onStartSearch: item == null
                ? null
                : () => setState(() => searching = true),
            onCloseSearch: () => setState(() {
              searching = false;
              query = '';
              searchBox.clear();
            }),
            onSearchChanged: (value) => setState(() => query = value),
            subtitle: _AccountLine(user: widget.state.user),
          ),
          if (item == null)
            const Expanded(
              child: Center(
                child: Text(
                  'No cases yet',
                  style: TextStyle(color: casebookMuted),
                ),
              ),
            )
          else ...[
            _CaseHeader(
              item: item,
              onSetHearing: () => _setHearing(item),
              onPriority: (value) =>
                  _run(widget.state.updatePriority(item.id, value)),
              onEdit: () async {
                await editCase(context, widget.state, item);
                widget.onChanged();
              },
              onToggleStar: () => _run(widget.state.togglePin(item.id)),
            ),
            _TabBar(labels: tabs, index: tab, onTap: _goTo),
            Expanded(
              child: PageView(
                controller: page,
                onPageChanged: (index) => setState(() => tab = index),
                children: [
                  _Timeline(
                    state: widget.state,
                    item: item,
                    query: query,
                    onOpenFile: _openFile,
                  ),
                  _Documents(
                    state: widget.state,
                    item: item,
                    query: query,
                    onRun: _run,
                    onOpenFile: _openFile,
                  ),
                  _Parties(state: widget.state, item: item),
                  _Tasks(
                    state: widget.state,
                    item: item,
                    active: tab == 3,
                    onRun: _run,
                  ),
                  _Settings(
                    item: item,
                    onDelete: () async {
                      final confirmed = await confirmCaseDelete(
                        context,
                        item.title,
                      );
                      if (confirmed) {
                        await _run(widget.state.deleteCase(item.id));
                      }
                    },
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

String? _storageLine(UserAccount? user) {
  final used = user?.storageLabel?.trim() ?? '';
  if (used.isEmpty) {
    return null;
  }
  final limit = user?.storageLimitLabel?.trim() ?? '';
  if (limit.isEmpty) {
    return used;
  }
  return '$used of $limit';
}

String? _casesLine(UserAccount? user) {
  final count = user?.casesCount;
  if (count == null) {
    return null;
  }
  final limit = user?.casesLimit;
  if (limit == null) {
    return '$count cases';
  }
  return '$count of $limit cases';
}

class _AccountLine extends StatelessWidget {
  const _AccountLine({required this.user});

  final UserAccount? user;

  @override
  Widget build(BuildContext context) {
    final email = user?.email.trim() ?? '';
    final plan = user?.planName?.trim() ?? '';
    final storage = _storageLine(user);
    final cases = _casesLine(user);
    if (email.isEmpty && plan.isEmpty && storage == null && cases == null) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.only(top: 2, bottom: 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (email.isNotEmpty)
            Text(
              email,
              style: const TextStyle(color: casebookMuted, fontSize: 12),
            ),
          if (plan.isNotEmpty)
            Text(
              plan,
              style: const TextStyle(
                color: casebookText,
                fontSize: 13,
                fontWeight: FontWeight.w700,
              ),
            ),
          if (storage != null)
            Text(
              storage,
              style: const TextStyle(color: casebookMuted, fontSize: 12),
            ),
          if (cases != null)
            Text(
              cases,
              style: const TextStyle(color: casebookMuted, fontSize: 12),
            ),
        ],
      ),
    );
  }
}

class _CaseHeader extends StatelessWidget {
  const _CaseHeader({
    required this.item,
    required this.onSetHearing,
    required this.onPriority,
    required this.onEdit,
    required this.onToggleStar,
  });

  final CaseDetail item;
  final VoidCallback onSetHearing;
  final ValueChanged<String> onPriority;
  final VoidCallback onEdit;
  final VoidCallback onToggleStar;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 8, 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFFEDE9FE),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.folder,
                  color: casebookPurple,
                  size: 22,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.title,
                        style: const TextStyle(
                          color: casebookText,
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      if (item.partiesLabel.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Text(
                            item.partiesLabel,
                            style: const TextStyle(
                              color: casebookMuted,
                              fontSize: 13,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      TextButton(
                        onPressed: onSetHearing,
                        style: TextButton.styleFrom(
                          foregroundColor: casebookPurple,
                          padding: const EdgeInsets.symmetric(horizontal: 4),
                          minimumSize: Size.zero,
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                          textStyle: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        child: const Text('Set Hearing date'),
                      ),
                      PopupMenuButton<String>(
                        color: Colors.white,
                        icon: const Icon(Icons.more_vert, color: casebookMuted),
                        onSelected: (value) {
                          if (value == 'edit') {
                            onEdit();
                          }
                        },
                        itemBuilder: (_) => const [
                          PopupMenuItem(
                            value: 'edit',
                            child: Text('Edit Case'),
                          ),
                        ],
                      ),
                    ],
                  ),
                  if (item.nextHearing != null)
                    Padding(
                      padding: const EdgeInsets.only(right: 12, bottom: 4),
                      child: Text(
                        formatDay(item.nextHearing),
                        style: const TextStyle(
                          color: casebookBlue,
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                ],
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.only(top: 12, right: 12),
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                _Tag(
                  icon: Icons.account_balance,
                  label: item.court,
                  background: const Color(0xFFF3F4F6),
                  foreground: casebookText,
                  expand: true,
                ),
                _Tag(
                  label: item.status ?? 'Active',
                  background: const Color(0xFFFEF3C7),
                  foreground: casebookAmber,
                ),
                PopupMenuButton<String>(
                  onSelected: onPriority,
                  color: Colors.white,
                  padding: EdgeInsets.zero,
                  itemBuilder: (_) => [
                    for (final value in ['low', 'medium', 'high'])
                      PopupMenuItem(
                        value: value,
                        child: Text(
                          PriorityColors.label(value),
                          style: TextStyle(
                            color: PriorityColors.foreground(value),
                            backgroundColor: PriorityColors.background(value),
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                  ],
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: PriorityColors.background(item.priority),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      PriorityColors.label(item.priority),
                      style: TextStyle(
                        color: PriorityColors.foreground(item.priority),
                        backgroundColor: PriorityColors.background(
                          item.priority,
                        ),
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ),
                _StarButton(starred: item.isPinned, onPressed: onToggleStar),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Tag extends StatelessWidget {
  const _Tag({
    required this.label,
    required this.background,
    required this.foreground,
    this.icon,
    this.expand = false,
  });

  final String label;
  final Color background;
  final Color foreground;
  final IconData? icon;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(
      color: foreground,
      fontSize: 12,
      fontWeight: FontWeight.w600,
      height: 1.3,
    );
    final maxWidth = MediaQuery.sizeOf(context).width - 52;
    return Container(
      constraints: expand ? BoxConstraints(maxWidth: maxWidth) : null,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text.rich(
        TextSpan(
          children: [
            if (icon != null)
              WidgetSpan(
                alignment: PlaceholderAlignment.middle,
                child: Padding(
                  padding: const EdgeInsets.only(right: 4),
                  child: Icon(icon, size: 12, color: foreground),
                ),
              ),
            TextSpan(text: label, style: style),
          ],
        ),
      ),
    );
  }
}

class _StarButton extends StatelessWidget {
  const _StarButton({required this.starred, required this.onPressed});

  final bool starred;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final color = starred ? const Color(0xFFCA8A04) : casebookMuted;
    return Material(
      color: starred ? const Color(0xFFFEF3C7) : const Color(0xFFF3F4F6),
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(20),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                starred ? Icons.star : Icons.star_border,
                size: 14,
                color: color,
              ),
              const SizedBox(width: 4),
              Text(
                starred ? 'Starred' : 'Star',
                style: TextStyle(
                  color: color,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Settings extends StatelessWidget {
  const _Settings({required this.item, required this.onDelete});

  final CaseDetail item;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
      children: [
        const Text(
          'Case settings',
          style: TextStyle(
            color: casebookText,
            fontSize: 16,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          'Delete ${item.title} and its documents, events, and files.',
          style: const TextStyle(color: casebookMuted, fontSize: 13),
        ),
        const SizedBox(height: 16),
        OutlinedButton.icon(
          onPressed: onDelete,
          style: OutlinedButton.styleFrom(
            foregroundColor: const Color(0xFFDC2626),
            side: const BorderSide(color: Color(0xFFFECACA)),
            padding: const EdgeInsets.symmetric(vertical: 14),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
          ),
          icon: const Icon(Icons.delete_outline),
          label: const Text(
            'Delete case',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
      ],
    );
  }
}

class _TabBar extends StatelessWidget {
  const _TabBar({
    required this.labels,
    required this.index,
    required this.onTap,
  });

  final List<String> labels;
  final int index;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 12),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: casebookLine)),
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 8),
        child: Row(
          children: [
            for (var i = 0; i < labels.length; i++)
              InkWell(
                onTap: () => onTap(i),
                child: Container(
                  alignment: Alignment.center,
                  padding: const EdgeInsets.fromLTRB(12, 4, 12, 12),
                  decoration: BoxDecoration(
                    border: Border(
                      bottom: BorderSide(
                        color: i == index ? casebookPurple : Colors.transparent,
                        width: 3,
                      ),
                    ),
                  ),
                  child: Text(
                    labels[i],
                    style: TextStyle(
                      color: i == index ? casebookText : casebookMuted,
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _Timeline extends StatefulWidget {
  const _Timeline({
    required this.state,
    required this.item,
    required this.query,
    required this.onOpenFile,
  });

  final AppState state;
  final CaseDetail item;
  final String query;
  final Future<void> Function(String? path, String filename) onOpenFile;

  @override
  State<_Timeline> createState() => _TimelineState();
}

class _TimelineState extends State<_Timeline> {
  @override
  Widget build(BuildContext context) {
    final state = widget.state;
    final item = widget.item;
    final query = widget.query;
    final onOpenFile = widget.onOpenFile;
    final q = query.trim().toLowerCase();
    final events = q.isEmpty
        ? item.events
        : item.events.where((event) {
            return event.title.toLowerCase().contains(q) ||
                event.description.toLowerCase().contains(q) ||
                (event.fileName ?? '').toLowerCase().contains(q);
          }).toList();

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
      children: [
        PurpleButton(
          icon: Icons.add,
          label: 'Add Event',
          onPressed: () => openEventForm(context, state, item),
        ),
        if (events.isEmpty)
          const Padding(
            padding: EdgeInsets.only(top: 12),
            child: Text(
              'No events on this case yet.',
              style: TextStyle(color: casebookMuted),
            ),
          )
        else
          Stack(
            children: [
              const Positioned(
                left: 6,
                top: 8,
                bottom: 0,
                child: ColoredBox(
                  color: casebookLine,
                  child: SizedBox(width: 2),
                ),
              ),
              Column(
                children: [
                  for (final event in events)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 24),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                width: 14,
                                height: 14,
                                decoration: BoxDecoration(
                                  color: event.isHearing
                                      ? casebookDanger
                                      : casebookBlue,
                                  shape: BoxShape.circle,
                                  border: Border.all(
                                    color: Colors.white,
                                    width: 3,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Text(
                                formatDay(event.date),
                                style: const TextStyle(
                                  color: casebookText,
                                  fontSize: 13,
                                ),
                              ),
                            ],
                          ),
                          Padding(
                            padding: const EdgeInsets.only(left: 26, top: 8),
                            child: WhiteCard(
                              margin: EdgeInsets.zero,
                              radius: 16,
                              child: Column(
                                children: [
                                  Row(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Container(
                                        padding: const EdgeInsets.all(10),
                                        decoration: BoxDecoration(
                                          color: event.isHearing
                                              ? const Color(0xFFFEE2E2)
                                              : const Color(0xFFDBEAFE),
                                          borderRadius: BorderRadius.circular(
                                            10,
                                          ),
                                        ),
                                        child: Icon(
                                          event.isHearing
                                              ? Icons.calendar_today
                                              : Icons.description_outlined,
                                          size: 16,
                                          color: event.isHearing
                                              ? casebookDanger
                                              : casebookBlue,
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              event.title,
                                              style: const TextStyle(
                                                color: casebookText,
                                                fontSize: 15,
                                                fontWeight: FontWeight.w700,
                                              ),
                                            ),
                                            if (event.description.isNotEmpty)
                                              Padding(
                                                padding: const EdgeInsets.only(
                                                  top: 4,
                                                ),
                                                child: Text(
                                                  event.description,
                                                  style: const TextStyle(
                                                    color: casebookMuted,
                                                    fontSize: 13,
                                                  ),
                                                ),
                                              ),
                                          ],
                                        ),
                                      ),
                                      IconButton(
                                        visualDensity: VisualDensity.compact,
                                        onPressed: () => openEventForm(
                                          context,
                                          state,
                                          item,
                                          event: event,
                                        ),
                                        icon: const Icon(
                                          Icons.edit_outlined,
                                          size: 18,
                                          color: casebookMuted,
                                        ),
                                      ),
                                    ],
                                  ),
                                  if (event.fileName != null)
                                    Container(
                                      margin: const EdgeInsets.only(top: 15),
                                      padding: const EdgeInsets.all(12),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFF9FAFB),
                                        borderRadius: BorderRadius.circular(10),
                                      ),
                                      child: Row(
                                        children: [
                                          const Icon(
                                            Icons.insert_drive_file_outlined,
                                            size: 16,
                                            color: casebookMuted,
                                          ),
                                          const SizedBox(width: 10),
                                          Expanded(
                                            child: Text(
                                              event.fileName!,
                                              style: const TextStyle(
                                                color: casebookText,
                                                fontSize: 13,
                                                fontWeight: FontWeight.w600,
                                              ),
                                            ),
                                          ),
                                          if (event.fileUrl != null)
                                            IconButton(
                                              visualDensity:
                                                  VisualDensity.compact,
                                              onPressed: () => onOpenFile(
                                                event.fileUrl,
                                                event.fileName ?? 'event-file',
                                              ),
                                              icon: const Icon(
                                                Icons.visibility_outlined,
                                                size: 18,
                                                color: casebookBlue,
                                              ),
                                            ),
                                        ],
                                      ),
                                    ),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ],
          ),
      ],
    );
  }
}

class _Documents extends StatefulWidget {
  const _Documents({
    required this.state,
    required this.item,
    required this.query,
    required this.onRun,
    required this.onOpenFile,
  });

  final AppState state;
  final CaseDetail item;
  final String query;
  final Future<void> Function(Future<void> action) onRun;
  final Future<void> Function(String? path, String filename) onOpenFile;

  @override
  State<_Documents> createState() => _DocumentsState();
}

class _DocumentsState extends State<_Documents> {
  @override
  Widget build(BuildContext context) {
    final state = widget.state;
    final item = widget.item;
    final query = widget.query;
    final onRun = widget.onRun;
    final onOpenFile = widget.onOpenFile;
    final q = query.trim().toLowerCase();
    final documents = q.isEmpty
        ? item.documents
        : item.documents
              .where((document) => document.name.toLowerCase().contains(q))
              .toList();

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
      children: [
        PurpleButton(
          icon: Icons.upload,
          label: 'Add Document',
          onPressed: () => openDocumentForm(context, state, item),
        ),
        if (documents.isEmpty)
          const Text(
            'No documents uploaded to this case.',
            style: TextStyle(color: casebookMuted),
          )
        else
          for (final document in documents)
            WhiteCard(
              child: Row(
                children: [
                  const Icon(
                    Icons.description,
                    size: 24,
                    color: casebookDanger,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          document.name,
                          style: const TextStyle(
                            color: casebookText,
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          _documentMeta(document),
                          style: const TextStyle(
                            color: casebookMuted,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (document.fileUrl != null)
                    IconButton(
                      onPressed: () =>
                          onOpenFile(document.fileUrl, document.name),
                      icon: const Icon(
                        Icons.visibility_outlined,
                        size: 18,
                        color: casebookBlue,
                      ),
                    ),
                  IconButton(
                    onPressed: () async {
                      final confirmed = await confirmDelete(
                        context,
                        'Delete this document?',
                      );
                      if (confirmed) {
                        await onRun(state.deleteDocument(item.id, document.id));
                      }
                    },
                    icon: const Icon(
                      Icons.delete_outline,
                      size: 18,
                      color: casebookDanger,
                    ),
                  ),
                ],
              ),
            ),
      ],
    );
  }

  String _documentMeta(CaseDocument document) {
    final parts = <String>[
      if (document.folder.isNotEmpty) document.folder,
      if ((document.createdAt ?? '').isNotEmpty) formatDay(document.createdAt),
      if ((document.size ?? '').isNotEmpty) document.size!,
    ];
    return parts.join(' • ');
  }
}

class _Parties extends StatelessWidget {
  const _Parties({required this.state, required this.item});

  final AppState state;
  final CaseDetail item;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
      children: [
        _side(context, 1),
        const SizedBox(height: 25),
        _side(context, 2),
      ],
    );
  }

  Widget _side(BuildContext context, int side) {
    final roles = side == 1
        ? state.workspace?.party1Roles
        : state.workspace?.party2Roles;
    final role = side == 1 ? item.party1Role : item.party2Role;
    final title =
        roles?[role] ?? role ?? (side == 1 ? 'Petitioners' : 'Respondents');
    final people = item.parties
        .where((party) => party.side == side && party.name.trim().isNotEmpty)
        .toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SectionLabel(text: title),
        for (final party in people)
          WhiteCard(
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        party.name,
                        style: const TextStyle(
                          color: casebookText,
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        role ?? '',
                        style: const TextStyle(
                          color: casebookMuted,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: () => openPartyForm(
                    context,
                    state,
                    item,
                    side: side,
                    party: party,
                  ),
                  icon: const Icon(
                    Icons.edit_outlined,
                    size: 18,
                    color: casebookMuted,
                  ),
                ),
              ],
            ),
          ),
        SizedBox(
          width: double.infinity,
          child: TextButton(
            style: TextButton.styleFrom(
              backgroundColor: const Color(0xFFF3F4F6),
              foregroundColor: casebookText,
              padding: const EdgeInsets.symmetric(vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            onPressed: () => openPartyForm(context, state, item, side: side),
            child: const Text(
              '+ Add Party',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
        ),
      ],
    );
  }
}

class _Tasks extends StatefulWidget {
  const _Tasks({
    required this.state,
    required this.item,
    required this.active,
    required this.onRun,
  });

  final AppState state;
  final CaseDetail item;
  final bool active;
  final Future<void> Function(Future<void> action) onRun;

  @override
  State<_Tasks> createState() => _TasksState();
}

class _TasksState extends State<_Tasks> {
  late final TextEditingController tasks;
  late final FocusNode focus;
  final fieldKey = GlobalKey();
  String? _posted;

  @override
  void initState() {
    super.initState();
    tasks = TextEditingController(text: widget.item.tasks);
    focus = FocusNode()..addListener(_onFocus);
  }

  @override
  void didUpdateWidget(covariant _Tasks oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.item.id != widget.item.id) {
      final value = tasks.text;
      if (value != oldWidget.item.tasks) {
        widget.onRun(widget.state.updateTasks(oldWidget.item.id, value));
      }
      _posted = null;
      tasks.text = widget.item.tasks;
      return;
    }
    if (oldWidget.active && !widget.active) {
      _save();
    }
    if (!focus.hasFocus &&
        tasks.text == oldWidget.item.tasks &&
        tasks.text != widget.item.tasks) {
      tasks.text = widget.item.tasks;
    }
  }

  @override
  void deactivate() {
    _save();
    super.deactivate();
  }

  @override
  void dispose() {
    focus.removeListener(_onFocus);
    focus.dispose();
    tasks.dispose();
    super.dispose();
  }

  void _onFocus() {
    if (!focus.hasFocus) {
      _save();
    }
  }

  void _saveIfOutside(Offset global) {
    final box = fieldKey.currentContext?.findRenderObject() as RenderBox?;
    if (box == null || !box.attached) {
      return;
    }
    final local = box.globalToLocal(global);
    if (box.size.contains(local)) {
      return;
    }
    focus.unfocus();
    _save();
  }

  Future<void> _save() async {
    final value = tasks.text;
    if (value == widget.item.tasks || value == _posted) {
      return;
    }
    _posted = value;
    final caseId = widget.item.id;
    await widget.onRun(widget.state.updateTasks(caseId, value));
    if (mounted && widget.item.id == caseId && widget.item.tasks != value) {
      _posted = null;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Listener(
      behavior: HitTestBehavior.translucent,
      onPointerDown: (event) => _saveIfOutside(event.position),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
        children: [
          const Text(
            'Quick Notes / Tasks',
            style: TextStyle(color: casebookMuted, fontSize: 12),
          ),
          const SizedBox(height: 8),
          TextField(
            key: fieldKey,
            controller: tasks,
            focusNode: focus,
            minLines: 8,
            maxLines: 14,
            onTapOutside: (_) {
              focus.unfocus();
              _save();
            },
            decoration: const InputDecoration(
              hintText: 'Write down tasks to complete before the next hearing.',
            ),
          ),
        ],
      ),
    );
  }
}

void showSavedFile(BuildContext context, File file) {
  ScaffoldMessenger.of(context)
      .showSnackBar(SnackBar(content: Text('Saved ${file.path}')));
}
