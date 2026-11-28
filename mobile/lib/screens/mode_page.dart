import 'package:material_ui/material_ui.dart';

import '../api.dart';
import '../app_state.dart';
import '../theme.dart';

class ModePage extends StatefulWidget {
  const ModePage({super.key, required this.state, required this.onChosen});

  final AppState state;
  final VoidCallback onChosen;

  @override
  State<ModePage> createState() => _ModePageState();
}

class _ModePageState extends State<ModePage> {
  bool busy = false;
  String? error;

  Future<void> choose(String mode) async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.state.chooseMode(mode);
      if (mounted) {
        widget.onChosen();
      }
    } on ApiException catch (failure) {
      setState(() => error = failure.message);
    } catch (_) {
      setState(() => error = 'Could not open that mode.');
    } finally {
      if (mounted) {
        setState(() => busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Case Book',
                    style: TextStyle(
                      color: casebookText,
                      fontSize: 24,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Which mode would you like to use?',
                    style: TextStyle(color: casebookMuted, fontSize: 14),
                  ),
                  const SizedBox(height: 24),
                  _ModeButton(
                    icon: Icons.person_outline,
                    title: 'Personal',
                    detail: 'Show personal cases only',
                    busy: busy,
                    onTap: () => choose('personal'),
                  ),
                  const SizedBox(height: 12),
                  _ModeButton(
                    icon: Icons.business_center_outlined,
                    title: 'Office',
                    detail: 'Show office cases only',
                    busy: busy,
                    onTap: () => choose('office'),
                  ),
                  if (error != null) ...[
                    const SizedBox(height: 16),
                    Text(error!, style: const TextStyle(color: Colors.red)),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ModeButton extends StatelessWidget {
  const _ModeButton({
    required this.icon,
    required this.title,
    required this.detail,
    required this.busy,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String detail;
  final bool busy;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: casebookLine),
      ),
      child: InkWell(
        onTap: busy ? null : onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Icon(icon, color: casebookBlue, size: 22),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        color: casebookText,
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      detail,
                      style: const TextStyle(
                        color: casebookMuted,
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: casebookMuted),
            ],
          ),
        ),
      ),
    );
  }
}
