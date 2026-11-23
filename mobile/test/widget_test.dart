import 'package:casebook/api.dart';
import 'package:casebook/app_state.dart';
import 'package:casebook/models.dart';
import 'package:casebook/screens/casebook_page.dart';
import 'package:casebook/screens/login_page.dart';
import 'package:casebook/theme.dart';
import 'package:material_ui/material_ui.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('login screen asks for an email', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: LoginPage(
          state: AppState(ApiClient(baseUrl: 'http://127.0.0.1:8000/api')),
          onAuthenticated: () {},
          onVerificationSent: (_) {},
        ),
      ),
    );

    expect(find.text('Case Book'), findsOneWidget);
    expect(find.text('Sign in with your email'), findsOneWidget);
    expect(find.text('Continue'), findsOneWidget);
  });

  testWidgets('case workspace shows the reference tabs on a white background', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);

    final state = AppState(ApiClient(baseUrl: 'http://127.0.0.1:9'));
    state.user = UserAccount(id: 1, name: 'Priya', email: 'priya@firm.test');
    state.workspace = Workspace(
      filter: 'All Cases',
      today: '2026-11-23',
      counts: {
        'All Cases': 1,
        "Today's Hearings": 0,
        'Upcoming': 1,
        'Starred': 0,
      },
      filters: const ['All Cases', "Today's Hearings", 'Upcoming', 'Starred'],
      tabs: const ['Timeline', 'Documents', 'Templates', 'Parties', 'Tasks'],
      party1Roles: const {'Petitioner': 'Petitioners'},
      party2Roles: const {'Respondent': 'Respondents'},
      folders: const ['Pleadings'],
      cases: [
        CaseSummary(
          id: 1,
          title: 'O.S. 10/2026',
          partiesLabel: 'Ramesh vs. State',
          court: 'Madras High Court',
          isPinned: false,
          status: 'Pending',
          nextHearing: '2026-11-23',
        ),
      ],
      templates: const [],
      active: CaseDetail(
        id: 1,
        title: 'O.S. 10/2026',
        partiesLabel: 'Ramesh vs. State',
        court: 'Madras High Court',
        isPinned: false,
        status: 'Pending',
        nextHearing: '2026-11-23',
        tasks: 'Read the counter',
        parties: [Party(id: 1, side: 1, name: 'Ramesh')],
        events: [
          CaseEvent(
            id: 1,
            date: '2026-11-23',
            title: 'Next Hearing Date',
            description: 'Court Hall No. 5',
            isHearing: true,
          ),
        ],
        documents: [
          CaseDocument(
            id: 1,
            name: 'Initial_Plaint.pdf',
            folder: 'Pleadings',
            size: '1.2 MB',
            createdAt: '2026-11-01',
          ),
        ],
      ),
    );

    await tester.pumpWidget(
      MaterialApp(
        theme: casebookTheme(),
        home: CasebookPage(state: state, onSignedOut: () {}),
      ),
    );
    await tester.pump();

    expect(find.text('Timeline'), findsOneWidget);
    expect(find.text('Documents'), findsOneWidget);
    expect(find.text('Parties'), findsOneWidget);
    expect(find.text('Tasks'), findsOneWidget);
    expect(find.text('Next Hearing Date'), findsOneWidget);
    expect(
      tester.widget<Material>(find.byType(Material).first).color,
      isNot(const Color(0xFF0B0C10)),
    );

    await tester.tap(find.text('Documents'));
    await tester.pumpAndSettle();
    expect(find.text('Initial_Plaint.pdf'), findsOneWidget);

    await tester.tap(find.text('Parties'));
    await tester.pumpAndSettle();
    expect(find.text('Ramesh'), findsOneWidget);
    expect(find.text('+ Add Party'), findsWidgets);

    await tester.tap(find.text('Tasks'));
    await tester.pumpAndSettle();
    expect(find.text('Quick Notes / Tasks'), findsOneWidget);
    expect(find.text('Read the counter'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.menu));
    await tester.pumpAndSettle();
    expect(find.text('New Case'), findsOneWidget);
    expect(find.text("Today's Cases"), findsOneWidget);
    expect(find.text('Saved Templates'), findsOneWidget);
  });
}
