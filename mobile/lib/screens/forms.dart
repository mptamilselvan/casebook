import 'package:file_picker/file_picker.dart';
import 'package:material_ui/material_ui.dart';

import '../app_state.dart';
import '../models.dart';
import '../theme.dart';

Future<void> openNewCase(BuildContext context, AppState state) {
  return Navigator.of(context)
      .push(MaterialPageRoute(builder: (_) => NewCasePage(state: state)));
}

class NewCasePage extends StatefulWidget {
  const NewCasePage({super.key, required this.state});

  final AppState state;

  @override
  State<NewCasePage> createState() => _NewCasePageState();
}

class _NewCasePageState extends State<NewCasePage> {
  int step = 1;
  String? selectedState;
  String? selectedDistrict;
  String? selectedCourt;
  final courtOther = TextEditingController();
  final number = TextEditingController();
  final year = TextEditingController(text: DateTime.now().year.toString());
  late String role1 =
      widget.state.workspace?.party1Roles.keys.first ?? 'Petitioner';
  late String role2 =
      widget.state.workspace?.party2Roles.keys.first ?? 'Respondent';
  final petitioners = [TextEditingController()];
  final respondents = [TextEditingController()];
  String? error;
  bool busy = false;

  @override
  void dispose() {
    for (final controller in [
      courtOther,
      number,
      year,
      ...petitioners,
      ...respondents,
    ]) {
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
        'state': selectedState ?? '',
        'district': selectedDistrict ?? '',
        'court': _courtName(),
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
    final places = widget.state.workspace?.locations ?? const <StatePlace>[];
    final districts = places
        .where((place) => place.name == selectedState)
        .expand((place) => place.districts)
        .toList();
    final courts = districts
        .where((place) => place.name == selectedDistrict)
        .expand((place) => place.courts)
        .toList();

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(title: Text('Create New Case · Step $step of 2')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          if (error != null)
            Text(error!, style: const TextStyle(color: Color(0xFFDC2626))),
          if (step == 1) ...[
            SearchChoice(
              label: 'State',
              value: selectedState,
              hint: 'Search state',
              options: [for (final place in places) place.name],
              onChanged: (value) {
                setState(() {
                  selectedState = value;
                  selectedDistrict = null;
                  selectedCourt = null;
                  courtOther.clear();
                });
              },
            ),
            const SizedBox(height: 12),
            SearchChoice(
              label: 'District',
              value: selectedDistrict,
              hint: 'Search district',
              enabled: selectedState != null,
              options: [for (final place in districts) place.name],
              onChanged: (value) {
                setState(() {
                  selectedDistrict = value;
                  selectedCourt = null;
                  courtOther.clear();
                });
              },
            ),
            const SizedBox(height: 12),
            SearchChoice(
              label: 'Court',
              value: selectedCourt,
              hint: 'Search court',
              enabled: selectedDistrict != null,
              options: courts
                  .where((name) => name.toLowerCase() != 'other')
                  .toList(),
              pinned: 'Other',
              onChanged: (value) => setState(() {
                selectedCourt = value;
                if (value != 'Other') {
                  courtOther.clear();
                }
              }),
            ),
            if (selectedCourt == 'Other') ...[
              const SizedBox(height: 12),
              TextField(
                controller: courtOther,
                decoration: casebookField(
                  'New court',
                  hint: 'Enter the court name',
                ),
              ),
            ],
            const SizedBox(height: 16),
            FilledButton(
              onPressed: () {
                final courtName = _courtName();
                if (selectedState == null ||
                    selectedDistrict == null ||
                    courtName.isEmpty) {
                  setState(
                    () => error = 'State, district, and court are required.',
                  );
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
            TextField(
              controller: number,
              decoration: casebookField('Case number'),
            ),
            const SizedBox(height: 12),
            TextField(controller: year, decoration: casebookField('Year')),
            const SizedBox(height: 12),
            _dropdown(
              'Side 1 role',
              roles1,
              role1,
              (value) => setState(() => role1 = value),
            ),
            for (var index = 0; index < petitioners.length; index++)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: TextField(
                  controller: petitioners[index],
                  decoration: casebookField('Petitioner ${index + 1}'),
                ),
              ),
            TextButton(
              onPressed: () =>
                  setState(() => petitioners.add(TextEditingController())),
              child: const Text('Add petitioner'),
            ),
            _dropdown(
              'Side 2 role',
              roles2,
              role2,
              (value) => setState(() => role2 = value),
            ),
            for (var index = 0; index < respondents.length; index++)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: TextField(
                  controller: respondents[index],
                  decoration: casebookField('Respondent ${index + 1}'),
                ),
              ),
            TextButton(
              onPressed: () =>
                  setState(() => respondents.add(TextEditingController())),
              child: const Text('Add respondent'),
            ),
            const SizedBox(height: 8),
            FilledButton(
              onPressed: busy ? null : create,
              child: const Text('Create Case'),
            ),
          ],
        ],
      ),
    );
  }

  String _courtName() {
    if (selectedCourt == 'Other') {
      return courtOther.text.trim();
    }
    return selectedCourt ?? '';
  }

  Widget _dropdown(
    String label,
    Map<String, String> roles,
    String value,
    ValueChanged<String> onChanged,
  ) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DropdownButtonFormField<String>(
        initialValue: value,
        decoration: casebookField(label),
        items: [
          for (final role in roles.keys)
            DropdownMenuItem(value: role, child: Text(role)),
        ],
        onChanged: (next) {
          if (next != null) {
            onChanged(next);
          }
        },
      ),
    );
  }
}

Future<void> editCase(BuildContext context, AppState state, CaseDetail item) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    builder: (_) => _EditCaseForm(state: state, item: item),
  );
}

Future<bool> confirmDelete(BuildContext context, String message) async {
  final result = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      backgroundColor: Colors.white,
      title: const Text('Delete'),
      content: Text(message),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context, false),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(context, true),
          child: const Text('Delete'),
        ),
      ],
    ),
  );
  return result ?? false;
}

Future<void> openEventForm(
  BuildContext context,
  AppState state,
  CaseDetail item, {
  CaseEvent? event,
}) {
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

class _EditCaseForm extends StatefulWidget {
  const _EditCaseForm({required this.state, required this.item});

  final AppState state;
  final CaseDetail item;

  @override
  State<_EditCaseForm> createState() => _EditCaseFormState();
}

class _EditCaseFormState extends State<_EditCaseForm> {
  late final title = TextEditingController(text: widget.item.title);
  late final court = TextEditingController(text: widget.item.court);
  late final status = TextEditingController(
    text: widget.item.status ?? 'Active',
  );
  bool busy = false;

  @override
  void dispose() {
    title.dispose();
    court.dispose();
    status.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return formSheet(
      context: context,
      title: 'Edit Case',
      busy: busy,
      onSave: () async {
        setState(() => busy = true);
        try {
          await widget.state.updateCase(
            widget.item.id,
            title.text,
            court.text,
            status: status.text,
          );
          if (context.mounted) {
            Navigator.pop(context);
          }
        } catch (error) {
          if (context.mounted) {
            ScaffoldMessenger.of(context)
                .showSnackBar(SnackBar(content: Text(error.toString())));
          }
        } finally {
          if (mounted) {
            setState(() => busy = false);
          }
        }
      },
      children: [
        formLabel('Case Title / Number'),
        TextField(controller: title),
        formLabel('Court Name'),
        TextField(controller: court),
        formLabel('Status'),
        TextField(controller: status),
      ],
    );
  }
}

class _EventFormState extends State<_EventForm> {
  late final title = TextEditingController(text: widget.event?.title ?? '');
  late final notes = TextEditingController(
    text: widget.event?.description ?? '',
  );
  late DateTime date =
      DateTime.tryParse(widget.event?.date ?? '') ?? DateTime.now();
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

  Future<void> _pickFile() async {
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
  }

  @override
  Widget build(BuildContext context) {
    final formatted =
        '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
    return formSheet(
      context: context,
      title: widget.event == null ? 'Add Event' : 'Edit Event',
      busy: busy,
      onSave: () async {
        setState(() => busy = true);
        try {
          await widget.state.saveEvent(
            widget.item.id,
            {
              'event_date': formatted,
              'title': title.text,
              'description': notes.text,
              'is_hearing': hearing ? '1' : '0',
            },
            eventId: widget.event?.id,
            bytes: bytes,
            filename: filename,
          );
          if (context.mounted) {
            Navigator.pop(context);
          }
        } catch (error) {
          if (context.mounted) {
            ScaffoldMessenger.of(context)
                .showSnackBar(SnackBar(content: Text(error.toString())));
          }
        } finally {
          if (mounted) {
            setState(() => busy = false);
          }
        }
      },
      children: [
        formLabel('Date'),
        _TapField(
          text: formatted,
          onTap: () async {
            final picked = await showDatePicker(
              context: context,
              firstDate: DateTime(2000),
              lastDate: DateTime(2100),
              initialDate: date,
            );
            if (picked != null) {
              setState(() => date = picked);
            }
          },
        ),
        formLabel('Subject'),
        TextField(
          controller: title,
          decoration: const InputDecoration(hintText: 'e.g. Counter Filed'),
        ),
        formLabel('Notes'),
        TextField(
          controller: notes,
          minLines: 3,
          maxLines: 5,
          decoration: const InputDecoration(hintText: 'Event details...'),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Hearing date'),
          value: hearing,
          onChanged: (value) => setState(() => hearing = value),
        ),
        _UploadButton(
          label:
              filename ?? widget.event?.fileName ?? 'Select Attached Document',
          onPressed: _pickFile,
        ),
        if (widget.event != null)
          TextButton(
            onPressed: busy
                ? null
                : () async {
                    await widget.state.deleteEvent(
                      widget.item.id,
                      widget.event!.id,
                    );
                    if (context.mounted) {
                      Navigator.pop(context);
                    }
                  },
            child: const Text(
              'Delete event',
              style: TextStyle(color: casebookDanger),
            ),
          ),
      ],
    );
  }
}

Future<void> openDocumentForm(
  BuildContext context,
  AppState state,
  CaseDetail item,
) {
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
  bool busy = false;

  @override
  void dispose() {
    name.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final folders = widget.state.workspace?.folders ?? ['Pleadings'];
    return formSheet(
      context: context,
      title: 'Add Document',
      busy: busy,
      onSave: () async {
        setState(() => busy = true);
        try {
          await widget.state.saveDocument(
            widget.item.id,
            folder,
            name.text,
            bytes: bytes,
            filename: filename,
          );
          if (context.mounted) {
            Navigator.pop(context);
          }
        } catch (error) {
          if (context.mounted) {
            ScaffoldMessenger.of(context)
                .showSnackBar(SnackBar(content: Text(error.toString())));
          }
        } finally {
          if (mounted) {
            setState(() => busy = false);
          }
        }
      },
      children: [
        _UploadButton(
          label: filename == null
              ? 'Browse Files on Device'
              : 'Selected: $filename',
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
        ),
        formLabel('Save As (Document Name)'),
        TextField(
          controller: name,
          decoration: const InputDecoration(hintText: 'e.g. Plaint.pdf'),
        ),
        formLabel('Folder'),
        DropdownButtonFormField<String>(
          initialValue: folder,
          items: [
            for (final item in folders)
              DropdownMenuItem(value: item, child: Text(item)),
          ],
          onChanged: (value) {
            if (value != null) {
              setState(() => folder = value);
            }
          },
        ),
      ],
    );
  }
}

Future<CaseTemplate?> openTemplateForm(
  BuildContext context,
  AppState state,
) async {
  final title = TextEditingController();
  final created = await showDialog<CaseTemplate>(
    context: context,
    builder: (context) => AlertDialog(
      backgroundColor: Colors.white,
      title: const Text('New Template'),
      content: TextField(
        controller: title,
        decoration: casebookField('Template name'),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
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
          const Text(
            'Signed in',
            style: TextStyle(
              color: casebookMuted,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Text(state.user?.email ?? ''),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context, false),
          child: const Text('Close'),
        ),
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

Future<void> openPartyForm(
  BuildContext context,
  AppState state,
  CaseDetail item, {
  required int side,
  Party? party,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    builder: (_) =>
        _PartyForm(state: state, item: item, side: side, party: party),
  );
}

class _PartyForm extends StatefulWidget {
  const _PartyForm({
    required this.state,
    required this.item,
    required this.side,
    this.party,
  });

  final AppState state;
  final CaseDetail item;
  final int side;
  final Party? party;

  @override
  State<_PartyForm> createState() => _PartyFormState();
}

class _PartyFormState extends State<_PartyForm> {
  late final name = TextEditingController(text: widget.party?.name ?? '');
  late String role =
      (widget.side == 1 ? widget.item.party1Role : widget.item.party2Role) ??
      (widget.side == 1 ? 'Petitioner' : 'Respondent');
  bool busy = false;

  @override
  void dispose() {
    name.dispose();
    super.dispose();
  }

  Future<void> save() async {
    final trimmed = name.text.trim();
    if (trimmed.isEmpty) {
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('Enter the party name.')));
      return;
    }
    setState(() => busy = true);
    try {
      final side1 = <String>[];
      final side2 = <String>[];
      var included = false;
      for (final party in widget.item.parties) {
        final bucket = party.side == 1 ? side1 : side2;
        if (widget.party != null && party.id == widget.party!.id) {
          bucket.add(trimmed);
          included = true;
          continue;
        }
        final existing = party.name.trim();
        if (existing.isNotEmpty) {
          bucket.add(existing);
        }
      }
      if (!included) {
        (widget.side == 1 ? side1 : side2).add(trimmed);
      }
      final roles1 = widget.state.workspace?.party1Roles ?? {};
      final roles2 = widget.state.workspace?.party2Roles ?? {};
      await widget.state.syncParties(widget.item.id, {
        'party1_role': _knownRole(
          widget.side == 1 ? role : widget.item.party1Role,
          roles1,
          'Petitioner',
        ),
        'party2_role': _knownRole(
          widget.side == 2 ? role : widget.item.party2Role,
          roles2,
          'Respondent',
        ),
        'side1': side1,
        'side2': side2,
      });
      if (mounted) {
        Navigator.pop(context);
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => busy = false);
      }
    }
  }

  String _knownRole(String? value, Map<String, String> roles, String fallback) {
    if (value != null && roles.containsKey(value)) {
      return value;
    }
    return roles.keys.firstOrNull ?? fallback;
  }

  @override
  Widget build(BuildContext context) {
    final roles =
        (widget.side == 1
            ? widget.state.workspace?.party1Roles
            : widget.state.workspace?.party2Roles) ??
        {};
    final current = roles.containsKey(role) ? role : roles.keys.firstOrNull;
    return formSheet(
      context: context,
      title: widget.party == null ? 'Add Party' : 'Edit Party',
      busy: busy,
      onSave: save,
      children: [
        formLabel('Party Name'),
        TextField(
          controller: name,
          autofocus: true,
          textInputAction: TextInputAction.done,
          onSubmitted: (_) => save(),
          decoration: const InputDecoration(hintText: 'Enter name'),
        ),
        formLabel('Role / Designation'),
        DropdownButtonFormField<String>(
          initialValue: current,
          items: [
            for (final entry in roles.keys)
              DropdownMenuItem(value: entry, child: Text(entry)),
          ],
          onChanged: (value) {
            if (value != null) {
              setState(() => role = value);
            }
          },
        ),
      ],
    );
  }
}

Future<void> openTemplateEditor(
  BuildContext context,
  AppState state,
  CaseTemplate template,
) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    builder: (_) => _TemplateEditor(state: state, template: template),
  );
}

class _TemplateEditor extends StatefulWidget {
  const _TemplateEditor({required this.state, required this.template});

  final AppState state;
  final CaseTemplate template;

  @override
  State<_TemplateEditor> createState() => _TemplateEditorState();
}

class _TemplateEditorState extends State<_TemplateEditor> {
  late final content = TextEditingController(text: widget.template.content);
  bool busy = false;

  @override
  void dispose() {
    content.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return formSheet(
      context: context,
      title: widget.template.title,
      busy: busy,
      onSave: () async {
        setState(() => busy = true);
        try {
          await widget.state.saveTemplate(widget.template.id, content.text);
          if (context.mounted) {
            Navigator.pop(context);
          }
        } catch (error) {
          if (context.mounted) {
            ScaffoldMessenger.of(context)
                .showSnackBar(SnackBar(content: Text(error.toString())));
          }
        } finally {
          if (mounted) {
            setState(() => busy = false);
          }
        }
      },
      children: [
        formLabel('Template'),
        TextField(controller: content, minLines: 8, maxLines: 14),
      ],
    );
  }
}

Widget formLabel(String text) {
  return Padding(
    padding: const EdgeInsets.only(top: 15, bottom: 8),
    child: Text(
      text,
      style: const TextStyle(color: casebookMuted, fontSize: 12),
    ),
  );
}

Widget formSheet({
  required BuildContext context,
  required String title,
  required List<Widget> children,
  required VoidCallback? onSave,
  bool busy = false,
}) {
  return Padding(
    padding: EdgeInsets.fromLTRB(
      20,
      12,
      20,
      16 + MediaQuery.viewInsetsOf(context).bottom,
    ),
    child: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: casebookText,
                  ),
                ),
              ),
              IconButton(
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.close, color: casebookMuted),
              ),
            ],
          ),
          ...children,
          const SizedBox(height: 20),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: casebookSave),
            onPressed: busy ? null : onSave,
            child: const Text('Save'),
          ),
        ],
      ),
    ),
  );
}

class _TapField extends StatelessWidget {
  const _TapField({required this.text, required this.onTap});

  final String text;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: InputDecorator(
        decoration: const InputDecoration(),
        child: Text(
          text,
          style: const TextStyle(color: casebookText, fontSize: 14),
        ),
      ),
    );
  }
}

class _UploadButton extends StatelessWidget {
  const _UploadButton({required this.label, required this.onPressed});

  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 15),
      child: SizedBox(
        width: double.infinity,
        child: FilledButton.icon(
          style: FilledButton.styleFrom(
            backgroundColor: const Color(0xFFF3F4F6),
            foregroundColor: casebookText,
          ),
          onPressed: onPressed,
          icon: const Icon(Icons.upload, size: 18),
          label: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis),
        ),
      ),
    );
  }
}

class SearchChoice extends StatelessWidget {
  const SearchChoice({
    super.key,
    required this.label,
    required this.value,
    required this.hint,
    required this.options,
    required this.onChanged,
    this.enabled = true,
    this.pinned,
  });

  final String label;
  final String? value;
  final String hint;
  final List<String> options;
  final ValueChanged<String> onChanged;
  final bool enabled;
  final String? pinned;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: !enabled
          ? null
          : () async {
              final picked = await showModalBottomSheet<String>(
                context: context,
                isScrollControlled: true,
                backgroundColor: Colors.white,
                builder: (context) => _OptionSearch(
                  title: label,
                  options: options,
                  selected: value,
                  pinned: pinned,
                ),
              );
              if (picked != null) {
                onChanged(picked);
              }
            },
      borderRadius: BorderRadius.circular(12),
      child: InputDecorator(
        decoration: casebookField(label)
            .copyWith(enabled: enabled, suffixIcon: const Icon(Icons.search)),
        child: Text(
          (value == null || value!.isEmpty) ? hint : value!,
          style: TextStyle(
            color: value == null || value!.isEmpty
                ? casebookMuted
                : casebookText,
            fontSize: 14,
          ),
        ),
      ),
    );
  }
}

class _OptionSearch extends StatefulWidget {
  const _OptionSearch({
    required this.title,
    required this.options,
    required this.selected,
    this.pinned,
  });

  final String title;
  final List<String> options;
  final String? selected;
  final String? pinned;

  @override
  State<_OptionSearch> createState() => _OptionSearchState();
}

class _OptionSearchState extends State<_OptionSearch> {
  final query = TextEditingController();

  @override
  void dispose() {
    query.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final needle = query.text.trim().toLowerCase();
    final matches = widget.options
        .where(
          (option) =>
              option != widget.pinned &&
              (needle.isEmpty || option.toLowerCase().contains(needle)),
        )
        .toList();
    final pinned = widget.pinned;
    if (pinned != null && pinned.isNotEmpty) {
      matches.add(pinned);
    }
    final height = MediaQuery.sizeOf(context).height * 0.72;

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SizedBox(
        height: height,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
              child: TextField(
                controller: query,
                autofocus: true,
                decoration: casebookField(widget.title, hint: 'Search'),
                onChanged: (_) => setState(() {}),
              ),
            ),
            Expanded(
              child: matches.isEmpty
                  ? const Center(
                      child: Text(
                        'No matches',
                        style: TextStyle(color: casebookMuted),
                      ),
                    )
                  : ListView.builder(
                      itemCount: matches.length,
                      itemBuilder: (context, index) {
                        final option = matches[index];
                        final chosen = option == widget.selected;
                        return ListTile(
                          title: Text(
                            option,
                            style: TextStyle(
                              color: casebookText,
                              fontWeight: chosen
                                  ? FontWeight.w700
                                  : FontWeight.w500,
                            ),
                          ),
                          trailing: chosen
                              ? const Icon(Icons.check, color: casebookPurple)
                              : null,
                          onTap: () => Navigator.pop(context, option),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
