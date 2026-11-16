import 'package:file_picker/file_picker.dart';
import 'package:material_ui/material_ui.dart';

import '../app_state.dart';
import '../models.dart';
import '../theme.dart';

Future<void> openNewCase(BuildContext context, AppState state) {
  return Navigator.of(context).push(MaterialPageRoute(builder: (_) => NewCasePage(state: state)));
}

class NewCasePage extends StatefulWidget {
  const NewCasePage({super.key, required this.state});

  final AppState state;

  @override
  State<NewCasePage> createState() => _NewCasePageState();
}

class _NewCasePageState extends State<NewCasePage> {
  int step = 1;
  final stateName = TextEditingController();
  final district = TextEditingController();
  final court = TextEditingController();
  final number = TextEditingController();
  final year = TextEditingController(text: DateTime.now().year.toString());
  late String role1 = widget.state.workspace?.party1Roles.keys.first ?? 'Petitioner';
  late String role2 = widget.state.workspace?.party2Roles.keys.first ?? 'Respondent';
  final petitioners = [TextEditingController()];
  final respondents = [TextEditingController()];
  String? error;
  bool busy = false;

  @override
  void dispose() {
    for (final controller in [stateName, district, court, number, year, ...petitioners, ...respondents]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> create() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.state.createCase({
        'state': stateName.text,
        'district': district.text,
        'court': court.text,
        'case_number': number.text,
        'case_year': year.text,
        'party1_role': role1,
        'party2_role': role2,
        'petitioners': petitioners.map((item) => item.text).toList(),
        'respondents': respondents.map((item) => item.text).toList(),
      });
      if (mounted) {
        Navigator.pop(context);
      }
    } catch (failure) {
      setState(() => error = failure.toString());
    } finally {
      if (mounted) {
        setState(() => busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final roles1 = widget.state.workspace?.party1Roles ?? {};
    final roles2 = widget.state.workspace?.party2Roles ?? {};

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(title: Text('Create New Case · Step $step of 2')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          if (error != null) Text(error!, style: const TextStyle(color: Color(0xFFDC2626))),
          if (step == 1) ...[
            TextField(controller: stateName, decoration: casebookField('State')),
            const SizedBox(height: 12),
            TextField(controller: district, decoration: casebookField('District')),
            const SizedBox(height: 12),
            TextField(controller: court, decoration: casebookField('Court')),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: () {
                if (court.text.trim().isEmpty) {
                  setState(() => error = 'Court is required.');
                  return;
                }
                setState(() {
                  error = null;
                  step = 2;
                });
              },
              child: const Text('Next'),
            ),
          ] else ...[
            TextField(controller: number, decoration: casebookField('Case number')),
            const SizedBox(height: 12),
            TextField(controller: year, decoration: casebookField('Year')),
            const SizedBox(height: 12),
            _dropdown('Side 1 role', roles1, role1, (value) => setState(() => role1 = value)),
            for (var index = 0; index < petitioners.length; index++)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: TextField(controller: petitioners[index], decoration: casebookField('Petitioner ${index + 1}')),
              ),
            TextButton(onPressed: () => setState(() => petitioners.add(TextEditingController())), child: const Text('Add petitioner')),
            _dropdown('Side 2 role', roles2, role2, (value) => setState(() => role2 = value)),
            for (var index = 0; index < respondents.length; index++)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: TextField(controller: respondents[index], decoration: casebookField('Respondent ${index + 1}')),
              ),
            TextButton(onPressed: () => setState(() => respondents.add(TextEditingController())), child: const Text('Add respondent')),
            const SizedBox(height: 8),
            FilledButton(onPressed: busy ? null : create, child: const Text('Create Case')),
          ],
        ],
      ),
    );
  }

  Widget _dropdown(String label, Map<String, String> roles, String value, ValueChanged<String> onChanged) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DropdownButtonFormField<String>(
        initialValue: value,
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

Future<void> editCase(BuildContext context, AppState state, CaseDetail item) async {
  final title = TextEditingController(text: item.title);
  final court = TextEditingController(text: item.court);
  await showDialog<void>(
    context: context,
    builder: (context) => AlertDialog(
      backgroundColor: Colors.white,
      title: const Text('Edit Case Info'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(controller: title, decoration: casebookField('Case title')),
          const SizedBox(height: 12),
          TextField(controller: court, decoration: casebookField('Court')),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
        FilledButton(
          onPressed: () async {
            await state.updateCase(item.id, title.text, court.text);
            if (context.mounted) {
              Navigator.pop(context);
            }
          },
          child: const Text('Save'),
        ),
      ],
    ),
  );
  title.dispose();
  court.dispose();
}

Future<bool> confirmDelete(BuildContext context, String message) async {
  final result = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      backgroundColor: Colors.white,
      title: const Text('Delete'),
      content: Text(message),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
        FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Delete')),
      ],
    ),
  );
  return result ?? false;
}

Future<void> openEventForm(BuildContext context, AppState state, CaseDetail item, {CaseEvent? event}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    builder: (_) => _EventForm(state: state, item: item, event: event),
  );
}

class _EventForm extends StatefulWidget {
  const _EventForm({required this.state, required this.item, this.event});

  final AppState state;
  final CaseDetail item;
  final CaseEvent? event;

  @override
  State<_EventForm> createState() => _EventFormState();
}

class _EventFormState extends State<_EventForm> {
  late final title = TextEditingController(text: widget.event?.title ?? '');
  late final notes = TextEditingController(text: widget.event?.description ?? '');
  late DateTime date = DateTime.tryParse(widget.event?.date ?? '') ?? DateTime.now();
  late bool hearing = widget.event?.isHearing ?? false;
  List<int>? bytes;
  String? filename;
  bool busy = false;

  @override
  void dispose() {
    title.dispose();
    notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final formatted = '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 20, 20, 20 + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(widget.event == null ? 'Add Timeline Event' : 'Edit Event', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
          const SizedBox(height: 12),
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Date'),
            subtitle: Text(formatted),
            trailing: const Icon(Icons.calendar_today),
            onTap: () async {
              final picked = await showDatePicker(context: context, firstDate: DateTime(2000), lastDate: DateTime(2100), initialDate: date);
              if (picked != null) {
                setState(() => date = picked);
              }
            },
          ),
          SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('This is a hearing date'), value: hearing, onChanged: (value) => setState(() => hearing = value)),
          TextField(controller: title, decoration: casebookField('Title')),
          const SizedBox(height: 12),
          TextField(controller: notes, decoration: casebookField('Notes'), minLines: 2, maxLines: 4),
          TextButton(
            onPressed: () async {
              final picked = await FilePicker.pickFiles();
              if (picked.isEmpty) {
                return;
              }
              final file = picked.first;
              final data = await file.readAsBytes();
              setState(() {
                bytes = data;
                filename = file.name;
              });
            },
            child: Text(filename ?? widget.event?.fileName ?? 'Attach file'),
          ),
          FilledButton(
            onPressed: busy
                ? null
                : () async {
                    setState(() => busy = true);
                    await widget.state.saveEvent(widget.item.id, {
                      'event_date': formatted,
                      'title': title.text,
                      'description': notes.text,
                      'is_hearing': hearing ? '1' : '0',
                    }, eventId: widget.event?.id, bytes: bytes, filename: filename);
                    if (context.mounted) {
                      Navigator.pop(context);
                    }
                  },
            child: const Text('Save Event'),
          ),
        ],
      ),
    );
  }
}

Future<void> openDocumentForm(BuildContext context, AppState state, CaseDetail item) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    builder: (_) => _DocumentForm(state: state, item: item),
  );
}

class _DocumentForm extends StatefulWidget {
  const _DocumentForm({required this.state, required this.item});

  final AppState state;
  final CaseDetail item;

  @override
  State<_DocumentForm> createState() => _DocumentFormState();
}

class _DocumentFormState extends State<_DocumentForm> {
  final name = TextEditingController();
  late String folder = widget.state.workspace?.folders.first ?? 'Pleadings';
  List<int>? bytes;
  String? filename;

  @override
  void dispose() {
    name.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final folders = widget.state.workspace?.folders ?? ['Pleadings'];
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 20, 20, 20 + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Align(alignment: Alignment.centerLeft, child: Text('Upload Document', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700))),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: folder,
            decoration: casebookField('Folder'),
            items: [for (final item in folders) DropdownMenuItem(value: item, child: Text(item))],
            onChanged: (value) {
              if (value != null) {
                setState(() => folder = value);
              }
            },
          ),
          const SizedBox(height: 12),
          TextField(controller: name, decoration: casebookField('Document name')),
          TextButton(
            onPressed: () async {
              final picked = await FilePicker.pickFiles();
              if (picked.isEmpty) {
                return;
              }
              final file = picked.first;
              final data = await file.readAsBytes();
              setState(() {
                bytes = data;
                filename = file.name;
                if (name.text.isEmpty) {
                  name.text = file.name;
                }
              });
            },
            child: Text(filename ?? 'Choose file'),
          ),
          FilledButton(
            onPressed: () async {
              await widget.state.saveDocument(widget.item.id, folder, name.text, bytes: bytes, filename: filename);
              if (context.mounted) {
                Navigator.pop(context);
              }
            },
            child: const Text('Save Document'),
          ),
        ],
      ),
    );
  }
}

Future<CaseTemplate?> openTemplateForm(BuildContext context, AppState state) async {
  final title = TextEditingController();
  final created = await showDialog<CaseTemplate>(
    context: context,
    builder: (context) => AlertDialog(
      backgroundColor: Colors.white,
      title: const Text('New Template'),
      content: TextField(controller: title, decoration: casebookField('Template name')),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
        FilledButton(
          onPressed: () async {
            final template = await state.createTemplate(title.text);
            if (context.mounted) {
              Navigator.pop(context, template);
            }
          },
          child: const Text('Create'),
        ),
      ],
    ),
  );
  title.dispose();
  return created;
}

Future<bool> showSettings(BuildContext context, AppState state) async {
  final signedOut = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      backgroundColor: Colors.white,
      title: const Text('App Settings'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Signed in', style: TextStyle(color: casebookMuted, fontSize: 12, fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          Text(state.user?.email ?? ''),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Close')),
        FilledButton(
          onPressed: () async {
            await state.logout();
            if (context.mounted) {
              Navigator.pop(context, true);
            }
          },
          child: const Text('Sign out'),
        ),
      ],
    ),
  );
  return signedOut ?? false;
}
