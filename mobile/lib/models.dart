class UserAccount {
  UserAccount({required this.id, required this.name, required this.email});

  final int id;
  final String name;
  final String email;

  factory UserAccount.fromJson(Map<String, dynamic> json) {
    return UserAccount(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
    );
  }
}

class CaseSummary {
  CaseSummary({
    required this.id,
    required this.title,
    required this.partiesLabel,
    required this.court,
    required this.isPinned,
    this.nextHearing,
    this.state,
    this.district,
    this.caseNumber,
    this.caseYear,
    this.party1Role,
    this.party2Role,
  });

  final int id;
  final String title;
  final String partiesLabel;
  final String court;
  final bool isPinned;
  final String? nextHearing;
  final String? state;
  final String? district;
  final String? caseNumber;
  final String? caseYear;
  final String? party1Role;
  final String? party2Role;

  factory CaseSummary.fromJson(Map<String, dynamic> json) {
    return CaseSummary(
      id: json['id'] as int,
      title: json['title'] as String? ?? 'Untitled Case',
      partiesLabel: json['parties_label'] as String? ?? '',
      court: json['court'] as String? ?? '',
      isPinned: json['is_pinned'] == true,
      nextHearing: json['next_hearing'] as String?,
      state: json['state'] as String?,
      district: json['district'] as String?,
      caseNumber: json['case_number'] as String?,
      caseYear: json['case_year'] as String?,
      party1Role: json['party1_role'] as String?,
      party2Role: json['party2_role'] as String?,
    );
  }

  String get haystack => '$title $partiesLabel $court'.toLowerCase();
}

class Party {
  Party({required this.id, required this.side, required this.name});

  final int id;
  final int side;
  final String name;

  factory Party.fromJson(Map<String, dynamic> json) {
    return Party(
      id: json['id'] as int,
      side: json['side'] as int,
      name: json['name'] as String? ?? '',
    );
  }
}

class CaseEvent {
  CaseEvent({
    required this.id,
    required this.date,
    required this.title,
    required this.description,
    required this.isHearing,
    this.fileName,
    this.fileSize,
    this.fileUrl,
  });

  final int id;
  final String date;
  final String title;
  final String description;
  final bool isHearing;
  final String? fileName;
  final String? fileSize;
  final String? fileUrl;

  factory CaseEvent.fromJson(Map<String, dynamic> json) {
    return CaseEvent(
      id: json['id'] as int,
      date: json['event_date'] as String? ?? '',
      title: json['title'] as String? ?? '',
      description: json['description'] as String? ?? '',
      isHearing: json['is_hearing'] == true,
      fileName: json['file_name'] as String?,
      fileSize: json['file_size'] as String?,
      fileUrl: json['file_url'] as String?,
    );
  }
}

class CaseDocument {
  CaseDocument({required this.id, required this.name, required this.folder, this.size, this.fileUrl});

  final int id;
  final String name;
  final String folder;
  final String? size;
  final String? fileUrl;

  factory CaseDocument.fromJson(Map<String, dynamic> json) {
    return CaseDocument(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      folder: json['folder'] as String? ?? '',
      size: json['size'] as String?,
      fileUrl: json['file_url'] as String?,
    );
  }
}

class CaseDetail extends CaseSummary {
  CaseDetail({
    required super.id,
    required super.title,
    required super.partiesLabel,
    required super.court,
    required super.isPinned,
    super.nextHearing,
    super.state,
    super.district,
    super.caseNumber,
    super.caseYear,
    super.party1Role,
    super.party2Role,
    required this.tasks,
    required this.parties,
    required this.events,
    required this.documents,
  });

  final String tasks;
  final List<Party> parties;
  final List<CaseEvent> events;
  final List<CaseDocument> documents;

  factory CaseDetail.fromJson(Map<String, dynamic> json) {
    final summary = CaseSummary.fromJson(json);
    return CaseDetail(
      id: summary.id,
      title: summary.title,
      partiesLabel: summary.partiesLabel,
      court: summary.court,
      isPinned: summary.isPinned,
      nextHearing: summary.nextHearing,
      state: summary.state,
      district: summary.district,
      caseNumber: summary.caseNumber,
      caseYear: summary.caseYear,
      party1Role: summary.party1Role,
      party2Role: summary.party2Role,
      tasks: json['tasks'] as String? ?? '',
      parties: (json['parties'] as List? ?? []).map((item) => Party.fromJson(item as Map<String, dynamic>)).toList(),
      events: (json['events'] as List? ?? []).map((item) => CaseEvent.fromJson(item as Map<String, dynamic>)).toList(),
      documents: (json['documents'] as List? ?? []).map((item) => CaseDocument.fromJson(item as Map<String, dynamic>)).toList(),
    );
  }
}

class CaseTemplate {
  CaseTemplate({required this.id, required this.title, required this.content, required this.exportUrl});

  final int id;
  final String title;
  final String content;
  final String exportUrl;

  factory CaseTemplate.fromJson(Map<String, dynamic> json) {
    return CaseTemplate(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      content: json['content'] as String? ?? '',
      exportUrl: json['export_url'] as String? ?? '',
    );
  }
}

class Workspace {
  Workspace({
    required this.filter,
    required this.today,
    required this.counts,
    required this.filters,
    required this.tabs,
    required this.party1Roles,
    required this.party2Roles,
    required this.folders,
    required this.cases,
    required this.templates,
    this.active,
  });

  final String filter;
  final String today;
  final Map<String, int> counts;
  final List<String> filters;
  final List<String> tabs;
  final Map<String, String> party1Roles;
  final Map<String, String> party2Roles;
  final List<String> folders;
  final List<CaseSummary> cases;
  final List<CaseTemplate> templates;
  final CaseDetail? active;

  factory Workspace.fromJson(Map<String, dynamic> json) {
    return Workspace(
      filter: json['filter'] as String? ?? 'All Cases',
      today: json['today'] as String? ?? '',
      counts: (json['counts'] as Map? ?? {}).map((key, value) => MapEntry(key.toString(), (value as num).toInt())),
      filters: (json['filters'] as List? ?? []).map((item) => item.toString()).toList(),
      tabs: (json['tabs'] as List? ?? []).map((item) => item.toString()).toList(),
      party1Roles: (json['party1_roles'] as Map? ?? {}).map((key, value) => MapEntry(key.toString(), value.toString())),
      party2Roles: (json['party2_roles'] as Map? ?? {}).map((key, value) => MapEntry(key.toString(), value.toString())),
      folders: (json['folders'] as List? ?? []).map((item) => item.toString()).toList(),
      cases: (json['cases'] as List? ?? []).map((item) => CaseSummary.fromJson(item as Map<String, dynamic>)).toList(),
      templates: (json['templates'] as List? ?? []).map((item) => CaseTemplate.fromJson(item as Map<String, dynamic>)).toList(),
      active: json['case'] == null ? null : CaseDetail.fromJson(json['case'] as Map<String, dynamic>),
    );
  }
}
