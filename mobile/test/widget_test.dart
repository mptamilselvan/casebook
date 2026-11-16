import 'package:casebook/api.dart';
import 'package:casebook/app_state.dart';
import 'package:casebook/screens/login_page.dart';
import 'package:material_ui/material_ui.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('login screen asks for an email', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: LoginPage(
        state: AppState(ApiClient(baseUrl: 'http://127.0.0.1:8000/api')),
        onAuthenticated: () {},
        onVerificationSent: (_) {},
      ),
    ));

    expect(find.text('Case Book'), findsOneWidget);
    expect(find.text('Sign in with your email'), findsOneWidget);
    expect(find.text('Continue'), findsOneWidget);
  });
}
