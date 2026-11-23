import 'dart:io';

import 'package:file_picker/file_picker.dart';
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
  static const tabs = ['Timeline', 'Documents', 'Parties', 'Tasks'];

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
              onEdit: () async {
                await editCase(context, widget.state, item);
                widget.onChanged();
              },
              onDelete: () async {
                final confirmed = await confirmDelete(
                  context,
                  'Delete this case?',
                );
                if (confirmed) {
                  await _run(widget.state.deleteCase(item.id));
                }
              },
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
                  _Tasks(state: widget.state, item: item, onRun: _run),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _CaseHeader extends StatelessWidget {
  const _CaseHeader({
    required this.item,
    required this.onEdit,
    required this.onDelete,
  });

  final CaseDetail item;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

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
              PopupMenuButton<String>(
                color: Colors.white,
                icon: const Icon(Icons.more_vert, color: casebookMuted),
                onSelected: (value) {
                  if (value == 'edit') {
                    onEdit();
                  } else if (value == 'delete') {
                    onDelete();
                  }
                },
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 'edit', child: Text('Edit Case')),
                  PopupMenuItem(
                    value: 'delete',
                    child: Text(
                      'Delete Case',
                      style: TextStyle(color: casebookDanger),
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
              children: [
                _Tag(
                  icon: Icons.account_balance,
                  label: item.court,
                  background: const Color(0xFFF3F4F6),
                  foreground: casebookText,
                ),
                _Tag(
                  label: item.status ?? 'Active',
                  background: const Color(0xFFFEF3C7),
                  foreground: casebookAmber,
                ),
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
  });

  final String label;
  final Color background;
  final Color foreground;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 12, color: foreground),
            const SizedBox(width: 4),
          ],
          Text(
            label,
            style: TextStyle(
              color: foreground,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
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
      child: Row(
        children: [
          for (var i = 0; i < labels.length; i++)
            Expanded(
              child: InkWell(
                onTap: () => onTap(i),
                child: Container(
                  alignment: Alignment.center,
                  padding: const EdgeInsets.only(bottom: 12, top: 4),
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
            ),
        ],
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
  final _scroll = ScrollController();
  final _formKey = GlobalKey();
  final _titleFocus = FocusNode();
  final _title = TextEditingController();
  final _notes = TextEditingController();
  DateTime _date = DateTime.now();
  bool _hearing = false;
  List<int>? _bytes;
  String? _filename;
  bool _busy = false;

  @override
  void dispose() {
    _scroll.dispose();
    _titleFocus.dispose();
    _title.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _focusForm() async {
    final target = _formKey.currentContext;
    if (target != null) {
      await Scrollable.ensureVisible(
        target,
        duration: const Duration(milliseconds: 280),
        curve: Curves.easeOut,
        alignment: 0.05,
      );
    } else if (_scroll.hasClients) {
      await _scroll.animateTo(
        _scroll.position.maxScrollExtent,
        duration: const Duration(milliseconds: 280),
        curve: Curves.easeOut,
      );
    }
    if (mounted) {
      _titleFocus.requestFocus();
    }
  }

  Future<void> _pickFile() async {
    final picked = await FilePicker.pickFiles();
    if (picked.isEmpty) {
      return;
    }
    final file = picked.first;
    final data = await file.readAsBytes();
    if (!mounted) {
      return;
    }
    setState(() {
      _bytes = data;
      _filename = file.name;
    });
  }

  Future<void> _save() async {
    final name = _title.text.trim();
    if (name.isEmpty || _busy) {
      _titleFocus.requestFocus();
      return;
    }
    setState(() => _busy = true);
    final formatted =
        '${_date.year.toString().padLeft(4, '0')}-${_date.month.toString().padLeft(2, '0')}-${_date.day.toString().padLeft(2, '0')}';
    try {
      await widget.state.saveEvent(
        widget.item.id,
        {
          'event_date': formatted,
          'title': name,
          'description': _notes.text,
          'is_hearing': _hearing ? '1' : '0',
        },
        bytes: _bytes,
        filename: _filename,
      );
      if (!mounted) {
        return;
      }
      _title.clear();
      _notes.clear();
      setState(() {
        _hearing = false;
        _bytes = null;
        _filename = null;
        _date = DateTime.now();
      });
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

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

    final formatted =
        '${_date.year.toString().padLeft(4, '0')}-${_date.month.toString().padLeft(2, '0')}-${_date.day.toString().padLeft(2, '0')}';

    return ListView(
      controller: _scroll,
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
      children: [
        PurpleButton(
          icon: Icons.add,
          label: 'Add Event',
          onPressed: _focusForm,
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
        KeyedSubtree(
          key: _formKey,
          child: WhiteCard(
            margin: const EdgeInsets.only(top: 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Add Event',
                  style: TextStyle(
                    color: casebookText,
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 12),
                const Text(
                  'Date',
                  style: TextStyle(
                    color: casebookMuted,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 6),
                InkWell(
                  onTap: () async {
                    final picked = await showDatePicker(
                      context: context,
                      firstDate: DateTime(2000),
                      lastDate: DateTime(2100),
                      initialDate: _date,
                    );
                    if (picked != null) {
                      setState(() => _date = picked);
                    }
                  },
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 14,
                    ),
                    decoration: BoxDecoration(
                      border: Border.all(color: casebookLine),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      formatted,
                      style: const TextStyle(color: casebookText),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                const Text(
                  'Title',
                  style: TextStyle(
                    color: casebookMuted,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 6),
                TextField(
                  controller: _title,
                  focusNode: _titleFocus,
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(
                    hintText: 'e.g. Counter Filed',
                  ),
                ),
                const SizedBox(height: 12),
                const Text(
                  'Notes',
                  style: TextStyle(
                    color: casebookMuted,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 6),
                TextField(
                  controller: _notes,
                  minLines: 2,
                  maxLines: 4,
                  decoration: const InputDecoration(
                    hintText: 'Event details...',
                  ),
                ),
                Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Hearing date',
                        style: TextStyle(color: casebookText),
                      ),
                    ),
                    Switch(
                      value: _hearing,
                      onChanged: (value) => setState(() => _hearing = value),
                    ),
                  ],
                ),
                OutlinedButton.icon(
                  onPressed: _busy ? null : _pickFile,
                  icon: const Icon(Icons.attach_file, size: 18),
                  label: Text(_filename ?? 'Attach file'),
                ),
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: _busy ? null : _save,
                  child: Text(_busy ? 'Saving...' : 'Save Event'),
                ),
              ],
            ),
          ),
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
  final _scroll = ScrollController();
  final _formKey = GlobalKey();
  final _nameFocus = FocusNode();
  final _name = TextEditingController();
  late String _folder = widget.state.workspace?.folders.first ?? 'Pleadings';
  List<int>? _bytes;
  String? _filename;
  bool _busy = false;

  @override
  void dispose() {
    _scroll.dispose();
    _nameFocus.dispose();
    _name.dispose();
    super.dispose();
  }

  Future<void> _focusForm() async {
    final target = _formKey.currentContext;
    if (target != null) {
      await Scrollable.ensureVisible(
        target,
        duration: const Duration(milliseconds: 280),
        curve: Curves.easeOut,
        alignment: 0.05,
      );
    } else if (_scroll.hasClients) {
      await _scroll.animateTo(
        _scroll.position.maxScrollExtent,
        duration: const Duration(milliseconds: 280),
        curve: Curves.easeOut,
      );
    }
    if (mounted) {
      _nameFocus.requestFocus();
    }
  }

  Future<void> _pickFile() async {
    final picked = await FilePicker.pickFiles();
    if (picked.isEmpty) {
      return;
    }
    final file = picked.first;
    final data = await file.readAsBytes();
    if (!mounted) {
      return;
    }
    setState(() {
      _bytes = data;
      _filename = file.name;
      if (_name.text.trim().isEmpty) {
        _name.text = file.name;
      }
    });
  }

  Future<void> _save() async {
    if (_busy) {
      return;
    }
    final name = _name.text.trim();
    if (name.isEmpty && _filename == null) {
      _nameFocus.requestFocus();
      return;
    }
    setState(() => _busy = true);
    try {
      await widget.onRun(
        widget.state.saveDocument(
          widget.item.id,
          _folder,
          name,
          bytes: _bytes,
          filename: _filename,
        ),
      );
      if (!mounted) {
        return;
      }
      _name.clear();
      setState(() {
        _bytes = null;
        _filename = null;
      });
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = widget.state;
    final item = widget.item;
    final query = widget.query;
    final onRun = widget.onRun;
    final onOpenFile = widget.onOpenFile;
    final folders = state.workspace?.folders ?? const ['Pleadings'];
    final q = query.trim().toLowerCase();
    final documents = q.isEmpty
        ? item.documents
        : item.documents
              .where((document) => document.name.toLowerCase().contains(q))
              .toList();

    return ListView(
      controller: _scroll,
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
      children: [
        PurpleButton(
          icon: Icons.upload,
          label: 'Add Document',
          onPressed: _focusForm,
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
        KeyedSubtree(
          key: _formKey,
          child: WhiteCard(
            margin: const EdgeInsets.only(top: 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Upload Document',
                  style: TextStyle(
                    color: casebookText,
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: _busy ? null : _pickFile,
                  icon: const Icon(Icons.attach_file, size: 18),
                  label: Text(_filename ?? 'Browse files'),
                ),
                const SizedBox(height: 12),
                const Text(
                  'Document name',
                  style: TextStyle(
                    color: casebookMuted,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 6),
                TextField(
                  controller: _name,
                  focusNode: _nameFocus,
                  decoration: const InputDecoration(
                    hintText: 'e.g. Plaint.pdf',
                  ),
                ),
                const SizedBox(height: 12),
                const Text(
                  'Folder',
                  style: TextStyle(
                    color: casebookMuted,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 6),
                DropdownButtonFormField<String>(
                  initialValue: folders.contains(_folder)
                      ? _folder
                      : folders.first,
                  items: [
                    for (final folder in folders)
                      DropdownMenuItem(value: folder, child: Text(folder)),
                  ],
                  onChanged: _busy
                      ? null
                      : (value) {
                          if (value != null) {
                            setState(() => _folder = value);
                          }
                        },
                ),
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: _busy ? null : _save,
                  child: Text(_busy ? 'Saving...' : 'Save Document'),
                ),
              ],
            ),
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
  const _Tasks({required this.state, required this.item, required this.onRun});

  final AppState state;
  final CaseDetail item;
  final Future<void> Function(Future<void> action) onRun;

  @override
  State<_Tasks> createState() => _TasksState();
}

class _TasksState extends State<_Tasks> {
  late final TextEditingController tasks;
  late final FocusNode focus;

  @override
  void initState() {
    super.initState();
    tasks = TextEditingController(text: widget.item.tasks);
    focus = FocusNode()
      ..addListener(() {
        if (!focus.hasFocus) {
          _save();
        }
      });
  }

  @override
  void didUpdateWidget(covariant _Tasks oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.item.id != widget.item.id &&
        tasks.text != widget.item.tasks) {
      tasks.text = widget.item.tasks;
    }
  }

  @override
  void dispose() {
    focus.dispose();
    tasks.dispose();
    super.dispose();
  }

  Future<void> _save() {
    if (tasks.text == widget.item.tasks) {
      return Future.value();
    }
    return widget.onRun(widget.state.updateTasks(widget.item.id, tasks.text));
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
      children: [
        const Text(
          'Quick Notes / Tasks',
          style: TextStyle(color: casebookMuted, fontSize: 12),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: tasks,
          focusNode: focus,
          minLines: 8,
          maxLines: 14,
          decoration: const InputDecoration(hintText: 'Type notes...'),
        ),
      ],
    );
  }
}

void showSavedFile(BuildContext context, File file) {
  ScaffoldMessenger.of(context)
      .showSnackBar(SnackBar(content: Text('Saved ${file.path}')));
}
