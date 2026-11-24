import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:material_ui/material_ui.dart';

import 'api.dart';
import 'app_state.dart';
import 'push.dart';
import 'screens/casebook_page.dart';
import 'screens/login_page.dart';
import 'theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Flutter does not package files named ".env", so the app loads assets/env.
  await dotenv.load(fileName: 'assets/env', isOptional: true);
  await CasebookPush.start();
  runApp(CasebookApp(state: AppState(ApiClient())));
}

class CasebookApp extends StatefulWidget {
  const CasebookApp({super.key, required this.state});

  final AppState state;

  @override
  State<CasebookApp> createState() => _CasebookAppState();
}

class _CasebookAppState extends State<CasebookApp> {
  bool ready = false;
  bool signedIn = false;
  String? pendingEmail;

  @override
  void initState() {
    super.initState();
    widget.state.restore().then((restored) {
      if (mounted) {
        setState(() {
          signedIn = restored;
          ready = true;
        });
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Case Book',
      debugShowCheckedModeBanner: false,
      theme: casebookTheme(),
      home: !ready
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : signedIn
          ? CasebookPage(
              state: widget.state,
              onSignedOut: () => setState(() {
                signedIn = false;
                pendingEmail = null;
              }),
            )
          : LoginPage(
              state: widget.state,
              pendingEmail: pendingEmail,
              onAuthenticated: () => setState(() {
                signedIn = true;
                pendingEmail = null;
              }),
              onVerificationSent: (email) =>
                  setState(() => pendingEmail = email),
            ),
    );
  }
}
