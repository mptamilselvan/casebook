import 'package:material_ui/material_ui.dart';

import '../api.dart';
import '../theme.dart';

class PeoplePage extends StatefulWidget {
  const PeoplePage({super.key, required this.api});

  final ApiClient api;

  @override
  State<PeoplePage> createState() => _PeoplePageState();
}

class _PeoplePageState extends State<PeoplePage> {
  final email = TextEditingController();
  List<Map<String, dynamic>> roles = const [];
  List<Map<String, dynamic>> people = const [];
  List<Map<String, dynamic>> cases = const [];
  Set<int> selectedCases = {};
  String role = 'member';
  String? error;
  String? notice;
  bool loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    email.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final body = await widget.api.getJson('people');
      final map = Map<String, dynamic>.from(body as Map);
      if (!mounted) return;
      setState(() {
        roles = [
          for (final item in map['roles'] as List? ?? [])
            Map<String, dynamic>.from(item as Map),
        ];
        people = [
          for (final item in map['people'] as List? ?? [])
            Map<String, dynamic>.from(item as Map),
        ];
        cases = [
          for (final item in map['cases'] as List? ?? [])
            Map<String, dynamic>.from(item as Map),
        ];
        loading = false;
        error = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        loading = false;
        error = e.toString();
      });
    }
  }

  Future<void> _saveNew() async {
    setState(() {
      error = null;
      notice = null;
    });
    try {
      final saved = await widget.api.postJson('people', {
        'email': email.text.trim(),
        'role': role,
        'case_ids': selectedCases.toList(),
      });
      email.clear();
      selectedCases = {};
      if (!mounted) return;
      setState(() => notice = _notice(saved, 'Person saved.'));
      await _load();
    } catch (e) {
      if (!mounted) return;
      setState(() => error = e.toString());
    }
  }

  Future<void> _update(
    Map<String, dynamic> person,
    String nextEmail,
    String nextRole,
    Set<int> caseIds,
  ) async {
    setState(() {
      error = null;
      notice = null;
    });
    try {
      final saved = await widget.api.putJson('people/${person['id']}', {
        'email': nextEmail.trim(),
        'role': nextRole,
        'case_ids': caseIds.toList(),
      });
      if (!mounted) return;
      setState(() => notice = _notice(saved, 'Person updated.'));
      await _load();
    } catch (e) {
      if (!mounted) return;
      setState(() => error = e.toString());
    }
  }

  String _notice(dynamic saved, String fallback) {
    final message = saved is Map ? saved['message']?.toString() : null;
    if (message == null || message.isEmpty) return fallback;
    return message;
  }

  Future<void> _delete(Map<String, dynamic> person) async {
    setState(() {
      error = null;
      notice = null;
    });
    try {
      await widget.api.deleteJson('people/${person['id']}');
      if (!mounted) return;
      setState(() => notice = 'Person removed.');
      await _load();
    } catch (e) {
      if (!mounted) return;
      setState(() => error = e.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(title: const Text('People Management')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(20),
              children: [
                const Text(
                  'Add a person, choose a role, and check the cases they can open after they sign in.',
                  style: TextStyle(color: casebookMuted),
                ),
                if (notice != null) _Banner(notice!, const Color(0xFF047857)),
                if (error != null) _Banner(error!, const Color(0xFFB91C1C)),
                const SizedBox(height: 16),
                TextField(
                  controller: email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: casebookField('Email'),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: roles.any((item) => item['slug'] == role) ? role : null,
                  decoration: casebookField('Role'),
                  items: [
                    for (final item in roles)
                      DropdownMenuItem(
                        value: item['slug'] as String,
                        child: Text(item['name'] as String? ?? ''),
                      ),
                  ],
                  onChanged: (value) {
                    if (value != null) setState(() => role = value);
                  },
                ),
                const SizedBox(height: 12),
                _CaseAccessPicker(
                  cases: cases,
                  selected: selectedCases,
                  onChanged: (next) => setState(() => selectedCases = next),
                ),
                const SizedBox(height: 12),
                FilledButton(onPressed: _saveNew, child: const Text('Add person')),
                const SizedBox(height: 24),
                if (people.isEmpty)
                  const Text('No people added yet.', style: TextStyle(color: casebookMuted))
                else
                  for (final person in people)
                    _PersonEditor(
                      person: person,
                      roles: roles,
                      cases: cases,
                      onUpdate: _update,
                      onDelete: _delete,
                    ),
              ],
            ),
    );
  }
}

class _PersonEditor extends StatefulWidget {
  const _PersonEditor({
    required this.person,
    required this.roles,
    required this.cases,
    required this.onUpdate,
    required this.onDelete,
  });

  final Map<String, dynamic> person;
  final List<Map<String, dynamic>> roles;
  final List<Map<String, dynamic>> cases;
  final Future<void> Function(Map<String, dynamic> person, String email, String role, Set<int> caseIds) onUpdate;
  final Future<void> Function(Map<String, dynamic> person) onDelete;

  @override
  State<_PersonEditor> createState() => _PersonEditorState();
}

class _PersonEditorState extends State<_PersonEditor> {
  late final TextEditingController email;
  late String role;
  late Set<int> selected;

  Set<int> _ids(Map<String, dynamic> person) {
    return {
      for (final id in person['case_ids'] as List? ?? []) (id as num).toInt(),
    };
  }

  @override
  void initState() {
    super.initState();
    email = TextEditingController(text: widget.person['email'] as String? ?? '');
    role = widget.person['role'] as String? ?? 'member';
    selected = _ids(widget.person);
  }

  @override
  void didUpdateWidget(covariant _PersonEditor oldWidget) {
    super.didUpdateWidget(oldWidget);
    final next = widget.person['email'] as String? ?? '';
    if (next != email.text) email.text = next;
    role = widget.person['role'] as String? ?? role;
    if (widget.person['id'] != oldWidget.person['id']) {
      selected = _ids(widget.person);
    }
  }

  @override
  void dispose() {
    email.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        border: Border.all(color: casebookLine),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        children: [
          TextField(
            controller: email,
            decoration: casebookField('Email'),
          ),
          const SizedBox(height: 8),
          DropdownButtonFormField<String>(
            initialValue: widget.roles.any((item) => item['slug'] == role) ? role : null,
            decoration: casebookField('Role'),
            items: [
              for (final item in widget.roles)
                DropdownMenuItem(
                  value: item['slug'] as String,
                  child: Text(item['name'] as String? ?? ''),
                ),
            ],
            onChanged: (value) {
              if (value != null) setState(() => role = value);
            },
          ),
          const SizedBox(height: 8),
          _CaseAccessPicker(
            cases: widget.cases,
            selected: selected,
            onChanged: (next) => setState(() => selected = next),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              FilledButton(
                onPressed: () => widget.onUpdate(widget.person, email.text, role, selected),
                child: const Text('Save changes'),
              ),
              const SizedBox(width: 8),
              TextButton(
                onPressed: () => widget.onDelete(widget.person),
                child: const Text('Delete', style: TextStyle(color: Color(0xFFB91C1C))),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class RolesPermissionsPage extends StatefulWidget {
  const RolesPermissionsPage({super.key, required this.api});

  final ApiClient api;

  @override
  State<RolesPermissionsPage> createState() => _RolesPermissionsPageState();
}

class _RolesPermissionsPageState extends State<RolesPermissionsPage> {
  List<Map<String, dynamic>> roles = const [];
  List<Map<String, dynamic>> groups = const [];
  List<String> actions = const [];
  int? selectedId;
  bool canEdit = false;
  bool loading = true;
  String? error;
  String? notice;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final body = await widget.api.getJson('role-permissions');
      final map = Map<String, dynamic>.from(body as Map);
      final nextRoles = [
        for (final item in map['roles'] as List? ?? [])
          Map<String, dynamic>.from(item as Map),
      ];
      if (!mounted) return;
      setState(() {
        roles = nextRoles;
        groups = [
          for (final item in map['groups'] as List? ?? [])
            Map<String, dynamic>.from(item as Map),
        ];
        actions = [
          for (final item in map['actions'] as List? ?? []) item.toString(),
        ];
        canEdit = map['can_edit'] == true;
        selectedId ??= nextRoles.isEmpty ? null : (nextRoles.first['id'] as num).toInt();
        loading = false;
        error = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        loading = false;
        error = e.toString();
      });
    }
  }

  Map<String, dynamic>? get selected {
    for (final role in roles) {
      if ((role['id'] as num).toInt() == selectedId) return role;
    }
    return roles.isEmpty ? null : roles.first;
  }

  Future<void> _toggle(int permissionId, bool checked) async {
    final role = selected;
    if (role == null || !canEdit) return;
    final grants = [
      for (final id in role['grants'] as List? ?? []) (id as num).toInt(),
    ];
    if (checked) {
      grants.add(permissionId);
    } else {
      grants.remove(permissionId);
    }
    setState(() => role['grants'] = grants);
    try {
      await widget.api.putJson('role-permissions', {
        'role_id': role['id'],
        'grants': grants,
      });
      if (!mounted) return;
      setState(() => notice = 'Permissions saved.');
    } catch (e) {
      if (!mounted) return;
      setState(() => error = e.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    final role = selected;
    final grants = {
      for (final id in role?['grants'] as List? ?? []) (id as num).toInt(),
    };

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(title: const Text('Roles & Permissions')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(20),
              children: [
                Text(
                  canEdit
                      ? 'Choose a role and set what your people with that role can do.'
                      : 'These permissions are set by the account that added you.',
                  style: const TextStyle(color: casebookMuted),
                ),
                if (notice != null) _Banner(notice!, const Color(0xFF047857)),
                if (error != null) _Banner(error!, const Color(0xFFB91C1C)),
                const SizedBox(height: 16),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final item in roles)
                      ChoiceChip(
                        label: Text(item['name'] as String? ?? ''),
                        selected: (item['id'] as num).toInt() == role?['id'],
                        onSelected: (_) => setState(() => selectedId = (item['id'] as num).toInt()),
                      ),
                  ],
                ),
                const SizedBox(height: 16),
                for (final group in groups) ...[
                  Text(
                    group['label'] as String? ?? '',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  for (final action in actions)
                    if ((group['permissions'] as Map?)?[action] != null)
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(action),
                        value: grants.contains(((group['permissions'] as Map)[action] as num).toInt()),
                        onChanged: canEdit
                            ? (checked) => _toggle(
                                  ((group['permissions'] as Map)[action] as num).toInt(),
                                  checked ?? false,
                                )
                            : null,
                      ),
                  const SizedBox(height: 8),
                ],
              ],
            ),
    );
  }
}

class _CaseAccessPicker extends StatelessWidget {
  const _CaseAccessPicker({
    required this.cases,
    required this.selected,
    required this.onChanged,
  });

  final List<Map<String, dynamic>> cases;
  final Set<int> selected;
  final ValueChanged<Set<int>> onChanged;

  @override
  Widget build(BuildContext context) {
    final label = selected.isEmpty
        ? 'Select cases'
        : '${selected.length} ${selected.length == 1 ? 'case' : 'cases'} selected';

    return Material(
      color: Colors.white,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: casebookLine),
      ),
      child: ExpansionTile(
        title: Text(label),
        subtitle: const Text('They can open only the cases you check'),
        children: [
          if (cases.isEmpty)
            const Padding(
              padding: EdgeInsets.fromLTRB(16, 0, 16, 12),
              child: Text('You have no cases yet.', style: TextStyle(color: casebookMuted)),
            )
          else
            for (final item in cases)
              CheckboxListTile(
                value: selected.contains((item['id'] as num).toInt()),
                title: Text(item['title'] as String? ?? 'Untitled case'),
                subtitle: (item['label'] as String?)?.isNotEmpty == true ? Text(item['label'] as String) : null,
                controlAffinity: ListTileControlAffinity.leading,
                onChanged: (checked) {
                  final next = {...selected};
                  final id = (item['id'] as num).toInt();
                  if (checked ?? false) {
                    next.add(id);
                  } else {
                    next.remove(id);
                  }
                  onChanged(next);
                },
              ),
        ],
      ),
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner(this.text, this.color);

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Text(text, style: TextStyle(color: color)),
    );
  }
}
