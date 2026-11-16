import 'package:material_ui/material_ui.dart';

import '../api.dart';
import '../app_state.dart';
import '../theme.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({
    super.key,
    required this.state,
    required this.onAuthenticated,
    required this.onVerificationSent,
    this.pendingEmail,
  });

  final AppState state;
  final VoidCallback onAuthenticated;
  final ValueChanged<String> onVerificationSent;
  final String? pendingEmail;

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final email = TextEditingController();
  final link = TextEditingController();
  bool busy = false;
  String? error;

  @override
  void dispose() {
    email.dispose();
    link.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final status = await widget.state.login(email.text);
      if (!mounted) {
        return;
      }
      if (status == 'authenticated') {
        widget.onAuthenticated();
      } else {
        widget.onVerificationSent(email.text.trim());
      }
    } on ApiException catch (failure) {
      setState(() => error = failure.message);
    } catch (_) {
      setState(() => error = 'Could not reach Case Book. Check that the server is running.');
    } finally {
      if (mounted) {
        setState(() => busy = false);
      }
    }
  }

  Future<void> verify() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.state.verify(link.text);
      if (mounted) {
        widget.onAuthenticated();
      }
    } on ApiException catch (failure) {
      setState(() => error = failure.message);
    } catch (_) {
      setState(() => error = 'Could not verify that link.');
    } finally {
      if (mounted) {
        setState(() => busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final pending = widget.pendingEmail;

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Container(
                padding: const EdgeInsets.all(28),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: casebookLine),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.balance, color: casebookBlue, size: 32),
                        SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Case Book', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: casebookText)),
                              Text('Sign in with your email', style: TextStyle(color: casebookMuted)),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 24),
                    if (pending != null)
                      Container(
                        margin: const EdgeInsets.only(bottom: 16),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFECFDF5),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFA7F3D0)),
                        ),
                        child: Text(
                          'We sent a verification link to $pending. Open it, paste the link here, or tap Continue after it is verified.',
                          style: const TextStyle(color: Color(0xFF065F46), fontSize: 13),
                        ),
                      ),
                    if (error != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Text(error!, style: const TextStyle(color: Color(0xFFDC2626))),
                      ),
                    TextField(
                      controller: email,
                      keyboardType: TextInputType.emailAddress,
                      autofocus: true,
                      decoration: casebookField('Email', hint: 'you@firm.com'),
                      onSubmitted: (_) => submit(),
                    ),
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: busy ? null : submit,
                      child: Text(busy ? 'Please wait' : 'Continue'),
                    ),
                    if (pending != null) ...[
                      const SizedBox(height: 20),
                      TextField(
                        controller: link,
                        decoration: casebookField('Verification link'),
                      ),
                      const SizedBox(height: 12),
                      OutlinedButton(
                        onPressed: busy ? null : verify,
                        style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(46)),
                        child: const Text('Verify email'),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
