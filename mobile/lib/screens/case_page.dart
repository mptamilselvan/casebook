import 'dart:io';

import 'package:material_ui/material_ui.dart';

import '../app_state.dart';
import '../models.dart';
import '../theme.dart';
import 'forms.dart';

class CasePage extends StatefulWidget {
  const CasePage({super.key, required this.state, required this.onChanged, this.embedded = false});

  final AppState state;
  final VoidCallback onChanged;
  final bool embedded;

  @override
  State<CasePage> createState() => _CasePageState();
}

class _CasePageState extends State<CasePage> with SingleTickerProviderStateMixin {
  late final TabController tabs;

  @override
  void initState() {
    super.initState();
    final count = widget.state.workspace?.tabs.length ?? 5;
    tabs = TabController(length: count, vsync: this);
  }

  @override
  void dispose() {
    tabs.dispose();
    super.dispose();
  }

  CaseDetail? get active => widget.state.workspace?.active;

  Future<void> _run(Future<void> action) async {
    try {
      await action;
      widget.onChanged();
    } on Exception catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final item = active;
    if (item == null) {
      return const Center(child: Text('Select a case'));
    }
    final labels = widget.state.workspace?.tabs ?? const ['Timeline', 'Documents', 'Templates', 'Parties', 'Tasks'];

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        automaticallyImplyLeading: !widget.embedded,
        title: Text(item.title, maxLines: 1, overflow: TextOverflow.ellipsis),
        actions: [
          IconButton(
            tooltip: item.isPinned ? 'Unstar' : 'Star',
            onPressed: () => _run(widget.state.togglePin(item.id)),
            icon: Icon(item.isPinned ? Icons.star : Icons.star_border, color: item.isPinned ? const Color(0xFFEAB308) : casebookMuted),
          ),
          PopupMenuButton<String>(
            onSelected: (value) async {
              if (value == 'edit') {
                await editCase(context, widget.state, item);
                widget.onChanged();
              } else if (value == 'delete') {
                final confirmed = await confirmDelete(context, 'Delete this case?');
                if (confirmed) {
                  await _run(widget.state.deleteCase(item.id));
                  if (context.mounted && !widget.embedded) {
                    Navigator.pop(context);
                  }
                }
              }
            },
            itemBuilder: (_) => const [
              PopupMenuItem(value: 'edit', child: Text('Edit case')),
              PopupMenuItem(value: 'delete', child: Text('Delete')),
            ],
          ),
        ],
        bottom: TabBar(
          controller: tabs,
          isScrollable: true,
          labelColor: casebookBlue,
          unselectedLabelColor: casebookMuted,
          indicatorColor: casebookBlue,
          tabs: [for (final label in labels) Tab(text: label)],
        ),
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.partiesLabel, style: const TextStyle(color: casebookMuted)),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _Chip(icon: Icons.balance, label: item.court),
                    ActionChip(
                      avatar: const Icon(Icons.event, size: 16),
                      label: Text(item.nextHearing ?? 'Set hearing'),
                      onPressed: () async {
                        final date = await showDatePicker(
                          context: context,
                          firstDate: DateTime(2000),
                          lastDate: DateTime(2100),
                          initialDate: DateTime.tryParse(item.nextHearing ?? '') ?? DateTime.now(),
                        );
                        if (date != null) {
                          final formatted = '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
                          await _run(widget.state.updateHearing(item.id, formatted));
                        }
                      },
                    ),
                  ],
                ),
              ],
            ),
          ),
          Expanded(
            child: TabBarView(
              controller: tabs,
              children: [
                _Timeline(state: widget.state, item: item, onRun: _run),
                _Documents(state: widget.state, item: item, onRun: _run),
                _Templates(state: widget.state, onRun: _run),
                _Parties(state: widget.state, item: item, onRun: _run),
                _Tasks(state: widget.state, item: item, onRun: _run),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(border: Border.all(color: casebookLine), borderRadius: BorderRadius.circular(20)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: casebookMuted),
          const SizedBox(width: 6),
          Text(label, style: const TextStyle(fontSize: 12)),
        ],
      ),
    );
  }
}

class _Timeline extends StatelessWidget {
  const _Timeline({required this.state, required this.item, required this.onRun});

  final AppState state;
  final CaseDetail item;
  final Future<void> Function(Future<void> action) onRun;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      children: [
        Align(
          alignment: Alignment.centerLeft,
          child: FilledButton.icon(
            onPressed: () => openEventForm(context, state, item),
            icon: const Icon(Icons.add),
            label: const Text('Add Event'),
          ),
        ),
        const SizedBox(height: 12),
        for (final event in item.events)
          Card(
            elevation: 0,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: const BorderSide(color: casebookLine)),
            child: ListTile(
              title: Text(event.title, style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Text('${event.date}${event.isHearing ? ' · Hearing' : ''}\n${event.description}'.trim()),
              isThreeLine: event.description.isNotEmpty,
              trailing: PopupMenuButton<String>(
                onSelected: (value) async {
                  if (value == 'edit') {
                    await openEventForm(context, state, item, event: event);
                  } else if (value == 'delete') {
                    await onRun(state.deleteEvent(item.id, event.id));
                  } else if (value == 'file' && event.fileUrl != null) {
                    final file = await state.download(event.fileUrl!, event.fileName ?? 'event-file');
                    if (context.mounted) {
                      showSavedFile(context, file);
                    }
                  }
                },
                itemBuilder: (_) => [
                  const PopupMenuItem(value: 'edit', child: Text('Edit')),
                  if (event.fileUrl != null) PopupMenuItem(value: 'file', child: Text(event.fileName ?? 'Open file')),
                  const PopupMenuItem(value: 'delete', child: Text('Delete')),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class _Documents extends StatelessWidget {
  const _Documents({required this.state, required this.item, required this.onRun});

  final AppState state;
  final CaseDetail item;
  final Future<void> Function(Future<void> action) onRun;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      children: [
        Align(
          alignment: Alignment.centerLeft,
          child: FilledButton.icon(
            onPressed: () => openDocumentForm(context, state, item),
            icon: const Icon(Icons.upload_file),
            label: const Text('Upload Document'),
          ),
        ),
        const SizedBox(height: 12),
        for (final document in item.documents)
          Card(
            elevation: 0,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: const BorderSide(color: casebookLine)),
            child: ListTile(
              leading: const Icon(Icons.description_outlined, color: casebookBlue),
              title: Text(document.name, style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Text('${document.folder}${document.size == null ? '' : ' · ${document.size}'}'),
              onTap: document.fileUrl == null
                  ? null
                  : () async {
                      final file = await state.download(document.fileUrl!, document.name);
                      if (context.mounted) {
                        showSavedFile(context, file);
                      }
                    },
            ),
          ),
      ],
    );
  }
}

class _Templates extends StatefulWidget {
  const _Templates({required this.state, required this.onRun});

  final AppState state;
  final Future<void> Function(Future<void> action) onRun;

  @override
  State<_Templates> createState() => _TemplatesState();
}

class _TemplatesState extends State<_Templates> {
  late final TextEditingController content;
  int? templateId;

  @override
  void initState() {
    super.initState();
    final first = widget.state.workspace?.templates.firstOrNull;
    templateId = first?.id;
    content = TextEditingController(text: first?.content ?? '');
  }

  @override
  void dispose() {
    content.dispose();
    super.dispose();
  }

  void select(CaseTemplate template) {
    setState(() {
      templateId = template.id;
      content.text = template.content;
    });
  }

  @override
  Widget build(BuildContext context) {
    final templates = widget.state.workspace?.templates ?? [];
    final selected = templates.where((item) => item.id == templateId).firstOrNull;

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      children: [
        Row(
          children: [
            FilledButton.icon(
              onPressed: () async {
                final created = await openTemplateForm(context, widget.state);
                if (created != null) {
                  select(created);
                }
              },
              icon: const Icon(Icons.add),
              label: const Text('New Template'),
            ),
            const SizedBox(width: 8),
            if (selected != null)
              OutlinedButton(
                onPressed: () async {
                  final file = await widget.state.download(selected.exportUrl, '${selected.title}.doc');
                  if (context.mounted) {
                    showSavedFile(context, file);
                  }
                },
                child: const Text('Export Word'),
              ),
          ],
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 8,
          children: [
            for (final template in templates)
              ChoiceChip(
                label: Text(template.title),
                selected: template.id == templateId,
                onSelected: (_) => select(template),
              ),
          ],
        ),
        const SizedBox(height: 12),
        TextField(
          controller: content,
          minLines: 8,
          maxLines: 16,
          decoration: casebookField('Template'),
        ),
        const SizedBox(height: 12),
        FilledButton(
          onPressed: selected == null ? null : () => widget.onRun(widget.state.saveTemplate(selected.id, content.text)),
          child: const Text('Save Template'),
        ),
      ],
    );
  }
}

class _Parties extends StatefulWidget {
  const _Parties({required this.state, required this.item, required this.onRun});

  final AppState state;
  final CaseDetail item;
  final Future<void> Function(Future<void> action) onRun;

  @override
  State<_Parties> createState() => _PartiesState();
}

class _PartiesState extends State<_Parties> {
  late String role1;
  late String role2;
  late List<TextEditingController> side1;
  late List<TextEditingController> side2;

  @override
  void initState() {
    super.initState();
    _load(widget.item);
  }

  @override
  void didUpdateWidget(covariant _Parties oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.item.id != widget.item.id) {
      _disposeControllers();
      _load(widget.item);
    }
  }

  void _load(CaseDetail item) {
    role1 = item.party1Role ?? 'Petitioner';
    role2 = item.party2Role ?? 'Respondent';
    side1 = item.parties.where((party) => party.side == 1).map((party) => TextEditingController(text: party.name)).toList();
    side2 = item.parties.where((party) => party.side == 2).map((party) => TextEditingController(text: party.name)).toList();
    if (side1.isEmpty) {
      side1 = [TextEditingController()];
    }
    if (side2.isEmpty) {
      side2 = [TextEditingController()];
    }
  }

  void _disposeControllers() {
    for (final controller in [...side1, ...side2]) {
      controller.dispose();
    }
  }

  @override
  void dispose() {
    _disposeControllers();
    super.dispose();
  }

  Future<void> save({String? addSide}) {
    return widget.onRun(widget.state.syncParties(widget.item.id, {
      'party1_role': role1,
      'party2_role': role2,
      'side1': side1.map((controller) => controller.text).toList(),
      'side2': side2.map((controller) => controller.text).toList(),
      'add_side': ?addSide,
    }));
  }

  @override
  Widget build(BuildContext context) {
    final roles1 = widget.state.workspace?.party1Roles ?? {};
    final roles2 = widget.state.workspace?.party2Roles ?? {};

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      children: [
        _rolePicker('Side 1', roles1, role1, (value) => setState(() => role1 = value)),
        for (final controller in side1) Padding(padding: const EdgeInsets.only(bottom: 8), child: TextField(controller: controller, decoration: casebookField('Name'))),
        TextButton(onPressed: () => save(addSide: '1'), child: const Text('Add party')),
        const SizedBox(height: 12),
        _rolePicker('Side 2', roles2, role2, (value) => setState(() => role2 = value)),
        for (final controller in side2) Padding(padding: const EdgeInsets.only(bottom: 8), child: TextField(controller: controller, decoration: casebookField('Name'))),
        TextButton(onPressed: () => save(addSide: '2'), child: const Text('Add party')),
        const SizedBox(height: 8),
        FilledButton(onPressed: save, child: const Text('Save parties')),
      ],
    );
  }

  Widget _rolePicker(String label, Map<String, String> roles, String value, ValueChanged<String> onChanged) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: DropdownButtonFormField<String>(
        initialValue: roles.containsKey(value) ? value : roles.keys.firstOrNull,
        decoration: casebookField(label),
        items: [for (final role in roles.keys) DropdownMenuItem(value: role, child: Text(role))],
        onChanged: (next) {
          if (next != null) {
            onChanged(next);
          }
        },
      ),
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

  @override
  void initState() {
    super.initState();
    tasks = TextEditingController(text: widget.item.tasks);
  }

  @override
  void didUpdateWidget(covariant _Tasks oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.item.id != widget.item.id && tasks.text != widget.item.tasks) {
      tasks.text = widget.item.tasks;
    }
  }

  @override
  void dispose() {
    tasks.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      children: [
        const Text('Tasks & Preparation Notes', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
        const SizedBox(height: 12),
        TextField(controller: tasks, minLines: 8, maxLines: 14, decoration: casebookField('Notes')),
        const SizedBox(height: 12),
        FilledButton(onPressed: () => widget.onRun(widget.state.updateTasks(widget.item.id, tasks.text)), child: const Text('Save tasks')),
      ],
    );
  }
}

void showSavedFile(BuildContext context, File file) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Saved ${file.path}')));
}
