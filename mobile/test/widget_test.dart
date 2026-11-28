import 'package:casebook/api.dart';
import 'package:casebook/app_state.dart';
import 'package:casebook/models.dart';
import 'package:casebook/screens/casebook_page.dart';
import 'package:casebook/screens/prices_page.dart';
import 'package:casebook/screens/login_page.dart';
import 'package:casebook/theme.dart';
import 'package:flutter/foundation.dart';
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
    state.user = UserAccount(
      id: 1,
      name: 'Priya',
      email: 'priya@firm.test',
      planName: 'Free',
      storageLabel: '1.2 MB',
      storageLimitLabel: '1 GB',
      casesCount: 1,
      casesLimit: 10,
    );
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

    expect(find.text('priya@firm.test'), findsOneWidget);
    expect(find.text('Free'), findsOneWidget);
    expect(find.text('1.2 MB of 1 GB'), findsOneWidget);
    expect(find.text('1 of 10 cases'), findsOneWidget);
    expect(find.text('Today'), findsOneWidget);
    expect(find.text('Tomorrow'), findsOneWidget);
    expect(find.text('Starred'), findsOneWidget);
    expect(find.text('O.S. 10/2026'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.menu));
    await tester.pumpAndSettle();
    expect(find.text('New Case'), findsOneWidget);
    expect(find.text('Saved Templates'), findsOneWidget);
    await tester.tap(find.byIcon(Icons.close));
    await tester.pumpAndSettle();

    await tester.tap(find.text('O.S. 10/2026'));
    await tester.pumpAndSettle();
    expect(find.text('Set Hearing date'), findsOneWidget);
    expect(find.text('23 Nov 2026'), findsWidgets);
    expect(find.text('Hearing date'), findsNothing);
    expect(find.text('Case history'), findsOneWidget);
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

    await tester.ensureVisible(find.text('Tasks'));
    await tester.tap(find.text('Tasks'));
    await tester.pumpAndSettle();
    expect(find.text('Quick Notes / Tasks'), findsOneWidget);
    expect(find.text('Read the counter'), findsOneWidget);
  });

  testWidgets('saving a party keeps the entered name', (tester) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);

    final api = _PartyApi();
    final state = AppState(api);
    state.user = UserAccount(
      id: 1,
      name: 'Priya',
      email: 'priya@firm.test',
      planName: 'Free',
    );
    state.workspace = Workspace(
      filter: 'All Cases',
      today: '2026-11-23',
      counts: const {'All Cases': 1},
      filters: const ['All Cases'],
      tabs: const ['Timeline', 'Documents', 'Parties', 'Tasks'],
      party1Roles: const {'Petitioner': 'Petitioners'},
      party2Roles: const {'Respondent': 'Respondents'},
      folders: const ['Pleadings'],
      cases: [
        CaseSummary(
          id: 1,
          title: 'O.S. 10/2026',
          partiesLabel: '',
          court: 'Madras High Court',
          isPinned: false,
          party1Role: 'Petitioner',
          party2Role: 'Respondent',
        ),
      ],
      templates: const [],
      active: CaseDetail(
        id: 1,
        title: 'O.S. 10/2026',
        partiesLabel: '',
        court: 'Madras High Court',
        isPinned: false,
        party1Role: 'Petitioner',
        party2Role: 'Respondent',
        tasks: '',
        parties: const [],
        events: const [],
        documents: const [],
      ),
    );

    await tester.pumpWidget(
      MaterialApp(
        theme: casebookTheme(),
        home: CasebookPage(state: state, onSignedOut: () {}),
      ),
    );
    await tester.pump();

    await tester.tap(find.text('O.S. 10/2026'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Parties'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('+ Add Party').first);
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).first, 'Suresh');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(api.saved?['side1'], ['Suresh']);
    expect(find.text('Add Party'), findsNothing);
    expect(find.text('Suresh'), findsAtLeastNWidgets(1));
  });

  testWidgets('task notes are saved when leaving the field', (tester) async {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    addTearDown(() => debugDefaultTargetPlatformOverride = null);
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);

    final api = _TasksApi();
    final state = AppState(api);
    state.user = UserAccount(
      id: 1,
      name: 'Priya',
      email: 'priya@firm.test',
      planName: 'Free',
    );
    state.workspace = Workspace(
      filter: 'All Cases',
      today: '2026-11-23',
      counts: const {'All Cases': 1},
      filters: const ['All Cases'],
      tabs: const ['Timeline', 'Documents', 'Parties', 'Tasks'],
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
        ),
      ],
      templates: const [],
      active: CaseDetail(
        id: 1,
        title: 'O.S. 10/2026',
        partiesLabel: 'Ramesh vs. State',
        court: 'Madras High Court',
        isPinned: false,
        tasks: 'Read the counter',
        parties: const [],
        events: const [],
        documents: const [],
      ),
    );

    await tester.pumpWidget(
      MaterialApp(
        theme: casebookTheme(),
        home: CasebookPage(state: state, onSignedOut: () {}),
      ),
    );
    await tester.pump();

    await tester.tap(find.text('O.S. 10/2026'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Tasks'));
    await tester.tap(find.text('Tasks'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'File the counter.');
    await tester.ensureVisible(find.text('Documents'));
    await tester.tap(find.text('Documents'));
    await tester.pumpAndSettle();

    expect(api.path, 'cases/1/tasks');
    expect(api.tasks, 'File the counter.');

    await tester.ensureVisible(find.text('Tasks'));
    await tester.tap(find.text('Tasks'));
    await tester.pumpAndSettle();
    expect(find.text('File the counter.'), findsOneWidget);
  });

  testWidgets('editing a case saves the updated details', (tester) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);

    final api = _EditCaseApi();
    final state = AppState(api);
    state.user = UserAccount(
      id: 1,
      name: 'Priya',
      email: 'priya@firm.test',
      planName: 'Free',
    );
    state.workspace = Workspace(
      filter: 'All Cases',
      today: '2026-11-23',
      counts: const {'All Cases': 1},
      filters: const ['All Cases'],
      tabs: const ['Timeline', 'Documents', 'Parties', 'Tasks'],
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
          state: 'Tamil Nadu',
          district: 'Chennai',
          caseNumber: 'O.S. 10',
          caseYear: '2026',
        ),
      ],
      templates: const [],
      active: CaseDetail(
        id: 1,
        title: 'O.S. 10/2026',
        partiesLabel: 'Ramesh vs. State',
        court: 'Madras High Court',
        isPinned: false,
        state: 'Tamil Nadu',
        district: 'Chennai',
        caseNumber: 'O.S. 10',
        caseYear: '2026',
        status: 'Active',
        party1Role: 'Petitioner',
        party2Role: 'Respondent',
        tasks: '',
        parties: [Party(id: 1, side: 1, name: 'Ramesh')],
        events: const [],
        documents: const [],
      ),
    );

    await tester.pumpWidget(
      MaterialApp(
        theme: casebookTheme(),
        home: CasebookPage(state: state, onSignedOut: () {}),
      ),
    );
    await tester.pump();

    await tester.tap(find.text('O.S. 10/2026'));
    await tester.pumpAndSettle();
    await tester.tap(find.byIcon(Icons.more_vert));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Edit Case'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).first, 'O.S. 99');
    await tester.ensureVisible(find.text('Save Changes'));
    await tester.tap(find.text('Save Changes'));
    await tester.pumpAndSettle();

    expect(api.path, 'cases/1');
    expect(api.saved?['case_number'], 'O.S. 99');
    expect(api.saved?['court'], 'Madras High Court');
    expect(api.saved?['petitioners'], ['Ramesh']);
    expect(find.text('O.S. 99/2026'), findsWidgets);
  });

  testWidgets('prices page lists the three plans', (tester) async {
    tester.view.physicalSize = const Size(800, 2000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      MaterialApp(
        theme: casebookTheme(),
        home: PricesPage(api: _PlansApi()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Free'), findsOneWidget);
    expect(find.text('Basic'), findsOneWidget);
    expect(find.text('Pro'), findsOneWidget);
    expect(find.text('₹0'), findsOneWidget);
    expect(find.text('₹100'), findsOneWidget);
    expect(find.text('₹300'), findsOneWidget);
    expect(find.text('1 GB'), findsOneWidget);
    expect(find.text('Your plan'), findsOneWidget);
    expect(find.text('Monthly'), findsOneWidget);
    expect(find.text('Yearly'), findsOneWidget);
    expect(find.text('Subscribe · ₹100 / month'), findsOneWidget);
    expect(find.text('Subscribe · ₹300 / month'), findsOneWidget);

    await tester.tap(find.text('Yearly'));
    await tester.pumpAndSettle();

    expect(find.text('₹1,000'), findsOneWidget);
    expect(find.text('₹3,000'), findsOneWidget);
    expect(find.text('Subscribe · ₹1,000 / year'), findsOneWidget);
  });
}

class _PlansApi extends ApiClient {
  _PlansApi() : super(baseUrl: 'http://127.0.0.1:9');

  @override
  Future<dynamic> getJson(String path) async {
    return {
      'currency': 'INR',
      'current_plan_id': 1,
      'plans': [
        {
          'id': 1,
          'name': 'Free',
          'space_label': '1 GB',
          'cases_label': '10 cases',
          'prices': [
            {'interval': 'monthly', 'amount': 0, 'label': '₹0'},
            {'interval': 'yearly', 'amount': 0, 'label': '₹0'},
          ],
        },
        {
          'id': 2,
          'name': 'Basic',
          'space_label': '5 GB',
          'cases_label': '50 cases',
          'prices': [
            {'interval': 'monthly', 'amount': 100, 'label': '₹100'},
            {'interval': 'yearly', 'amount': 1000, 'label': '₹1,000'},
          ],
        },
        {
          'id': 3,
          'name': 'Pro',
          'space_label': '10 GB',
          'cases_label': 'Unlimited cases',
          'prices': [
            {'interval': 'monthly', 'amount': 300, 'label': '₹300'},
            {'interval': 'yearly', 'amount': 3000, 'label': '₹3,000'},
          ],
        },
      ],
    };
  }
}

class _EditCaseApi extends ApiClient {
  _EditCaseApi() : super(baseUrl: 'http://127.0.0.1:9');

  String? path;
  Map<String, dynamic>? saved;

  @override
  Future<dynamic> putJson(String path, Map<String, dynamic> body) async {
    this.path = path;
    saved = body;
    return _case();
  }

  @override
  Future<dynamic> getJson(String path) async {
    return {
      'filter': 'All Cases',
      'today': '2026-11-23',
      'counts': {'All Cases': 1},
      'filters': ['All Cases'],
      'tabs': ['Timeline', 'Documents', 'Parties', 'Tasks'],
      'party1_roles': {'Petitioner': 'Petitioners'},
      'party2_roles': {'Respondent': 'Respondents'},
      'folders': ['Pleadings'],
      'locations': [],
      'cases': [
        {
          'id': 1,
          'title': 'O.S. 99/2026',
          'parties_label': 'Ramesh vs. State',
          'court': 'Madras High Court',
          'state': 'Tamil Nadu',
          'district': 'Chennai',
          'case_number': 'O.S. 99',
          'case_year': '2026',
          'is_pinned': false,
        },
      ],
      'templates': [],
      'case': _case(),
    };
  }

  Map<String, dynamic> _case() {
    return {
      'id': 1,
      'title': 'O.S. 99/2026',
      'parties_label': 'Ramesh vs. Respondent',
      'court': saved?['court'] ?? 'Madras High Court',
      'state': 'Tamil Nadu',
      'district': 'Chennai',
      'case_number': saved?['case_number'] ?? 'O.S. 99',
      'case_year': '2026',
      'status': 'Active',
      'is_pinned': false,
      'party1_role': 'Petitioner',
      'party2_role': 'Respondent',
      'tasks': '',
      'parties': [
        {'id': 1, 'side': 1, 'name': 'Ramesh'},
      ],
      'events': [],
      'documents': [],
    };
  }
}

class _TasksApi extends ApiClient {
  _TasksApi() : super(baseUrl: 'http://127.0.0.1:9');

  String? path;
  String? tasks;

  @override
  Future<dynamic> putJson(String path, Map<String, dynamic> body) async {
    this.path = path;
    tasks = body['tasks'] as String?;
    return _case();
  }

  @override
  Future<dynamic> getJson(String path) async {
    return {
      'filter': 'All Cases',
      'today': '2026-11-23',
      'counts': {'All Cases': 1},
      'filters': ['All Cases'],
      'tabs': ['Timeline', 'Documents', 'Parties', 'Tasks'],
      'party1_roles': {'Petitioner': 'Petitioners'},
      'party2_roles': {'Respondent': 'Respondents'},
      'folders': ['Pleadings'],
      'locations': [],
      'cases': [
        {
          'id': 1,
          'title': 'O.S. 10/2026',
          'parties_label': 'Ramesh vs. State',
          'court': 'Madras High Court',
          'is_pinned': false,
        },
      ],
      'templates': [],
      'case': _case(),
    };
  }

  Map<String, dynamic> _case() {
    return {
      'id': 1,
      'title': 'O.S. 10/2026',
      'parties_label': 'Ramesh vs. State',
      'court': 'Madras High Court',
      'is_pinned': false,
      'tasks': tasks ?? 'Read the counter',
      'parties': [],
      'events': [],
      'documents': [],
    };
  }
}

class _PartyApi extends ApiClient {
  _PartyApi() : super(baseUrl: 'http://127.0.0.1:9');

  Map<String, dynamic>? saved;

  @override
  Future<dynamic> putJson(String path, Map<String, dynamic> body) async {
    saved = body;
    return _case(body['side1'] as List? ?? const []);
  }

  @override
  Future<dynamic> getJson(String path) async {
    return {
      'filter': 'All Cases',
      'today': '2026-11-23',
      'counts': {'All Cases': 1},
      'filters': ['All Cases'],
      'tabs': ['Timeline', 'Documents', 'Parties', 'Tasks'],
      'party1_roles': {'Petitioner': 'Petitioners'},
      'party2_roles': {'Respondent': 'Respondents'},
      'folders': ['Pleadings'],
      'locations': [],
      'cases': [
        {
          'id': 1,
          'title': 'O.S. 10/2026',
          'parties_label': 'Suresh',
          'court': 'Madras High Court',
          'is_pinned': false,
        },
      ],
      'templates': [],
      'case': _case(saved?['side1'] as List? ?? const []),
    };
  }

  Map<String, dynamic> _case(List names) {
    return {
      'id': 1,
      'title': 'O.S. 10/2026',
      'parties_label': names.join(', '),
      'court': 'Madras High Court',
      'is_pinned': false,
      'party1_role': 'Petitioner',
      'party2_role': 'Respondent',
      'tasks': '',
      'parties': [
        for (var index = 0; index < names.length; index++)
          {'id': index + 1, 'side': 1, 'name': names[index]},
      ],
      'events': [],
      'documents': [],
    };
  }
}
