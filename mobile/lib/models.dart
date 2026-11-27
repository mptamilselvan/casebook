class UserAccount {
  UserAccount({
    required this.id,
    required this.name,
    required this.email,
    this.planName,
    this.storageLabel,
    this.storageLimitLabel,
    this.casesCount,
    this.casesLimit,
  });

  final int id;
  final String name;
  final String email;
  final String? planName;
  final String? storageLabel;
  final String? storageLimitLabel;
  final int? casesCount;
  final int? casesLimit;

  factory UserAccount.fromJson(Map<String, dynamic> json) {
    final usage = json['usage'];
    final map = usage is Map ? Map<String, dynamic>.from(usage) : null;

    return UserAccount(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      planName: json['plan_name'] as String?,
      storageLabel: map?['storage_label'] as String?,
      storageLimitLabel: map?['storage_limit_label'] as String?,
      casesCount: (map?['cases_count'] as num?)?.toInt(),
      casesLimit: (map?['cases_limit'] as num?)?.toInt(),
    );
  }

  UserAccount withUsage(Map<String, dynamic> usage) {
    return UserAccount(
      id: id,
      name: name,
      email: email,
      planName: planName,
      storageLabel: usage['storage_label'] as String?,
      storageLimitLabel: usage['storage_limit_label'] as String?,
      casesCount: (usage['cases_count'] as num?)?.toInt(),
      casesLimit: (usage['cases_limit'] as num?)?.toInt(),
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
    this.status,
    this.priority = 'medium',
    this.type,
    this.userId,
    this.mode = 'personal',
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
  final String? status;
  final String priority;
  final String? type;
  final int? userId;
  final String mode;

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
      status: json['status'] as String?,
      priority: json['priority'] as String? ?? 'medium',
      type: json['type'] as String?,
      userId: json['user_id'] as int?,
      mode: json['mode'] as String? ?? 'personal',
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
      id: (json['id'] as num).toInt(),
      side: (json['side'] as num).toInt(),
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
  CaseDocument({
    required this.id,
    required this.name,
    required this.folder,
    this.size,
    this.fileUrl,
    this.createdAt,
  });

  final int id;
  final String name;
  final String folder;
  final String? size;
  final String? fileUrl;
  final String? createdAt;

  factory CaseDocument.fromJson(Map<String, dynamic> json) {
    return CaseDocument(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      folder: json['folder'] as String? ?? '',
      size: json['size'] as String?,
      fileUrl: json['file_url'] as String?,
      createdAt: json['created_at'] as String?,
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
    super.status,
    super.priority,
    super.type,
    super.userId,
    super.mode,
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
      status: summary.status,
      priority: summary.priority,
      type: summary.type,
      userId: summary.userId,
      mode: summary.mode,
      tasks: json['tasks'] as String? ?? '',
      parties: (json['parties'] as List? ?? [])
          .map((item) => Party.fromJson(item as Map<String, dynamic>))
          .toList(),
      events: (json['events'] as List? ?? [])
          .map((item) => CaseEvent.fromJson(item as Map<String, dynamic>))
          .toList(),
      documents: (json['documents'] as List? ?? [])
          .map((item) => CaseDocument.fromJson(item as Map<String, dynamic>))
          .toList(),
    );
  }
}

class CaseTemplate {
  CaseTemplate({
    required this.id,
    required this.title,
    required this.content,
    required this.exportUrl,
  });

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

class CourtPlace {
  CourtPlace({required this.name, required this.courts});

  final String name;
  final List<String> courts;

  factory CourtPlace.fromJson(Map<String, dynamic> json) {
    return CourtPlace(
      name: json['name'] as String? ?? '',
      courts: (json['courts'] as List? ?? [])
          .map((item) => item.toString())
          .toList(),
    );
  }
}

class StatePlace {
  StatePlace({required this.name, required this.districts});

  final String name;
  final List<CourtPlace> districts;

  factory StatePlace.fromJson(Map<String, dynamic> json) {
    return StatePlace(
      name: json['name'] as String? ?? '',
      districts: (json['districts'] as List? ?? [])
          .map((item) => CourtPlace.fromJson(item as Map<String, dynamic>))
          .toList(),
    );
  }
}

class AppNotification {
  AppNotification({required this.id, required this.title, required this.date});

  final int id;
  final String title;
  final String date;

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: (json['id'] as num).toInt(),
      title: json['title'] as String? ?? '',
      date: json['date'] as String? ?? '',
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
    this.locations = const [],
    this.notifications = const [],
    this.permissions,
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
  final List<StatePlace> locations;
  final List<CaseSummary> cases;
  final List<CaseTemplate> templates;
  final List<AppNotification> notifications;
  final Map<String, Map<String, bool>>? permissions;
  final CaseDetail? active;

  bool allows(String group, String action) {
    final grants = permissions;
    if (grants == null) {
      return true;
    }
    return grants[group]?[action] ?? false;
  }

  factory Workspace.fromJson(Map<String, dynamic> json) {
    return Workspace(
      filter: json['filter'] as String? ?? 'All',
      today: json['today'] as String? ?? '',
      counts: (json['counts'] as Map? ?? {}).map(
        (key, value) => MapEntry(key.toString(), (value as num).toInt()),
      ),
      filters: (json['filters'] as List? ?? [])
          .map((item) => item.toString())
          .toList(),
      tabs: (json['tabs'] as List? ?? [])
          .map((item) => item.toString())
          .toList(),
      party1Roles: (json['party1_roles'] as Map? ?? {}).map(
        (key, value) => MapEntry(key.toString(), value.toString()),
      ),
      party2Roles: (json['party2_roles'] as Map? ?? {}).map(
        (key, value) => MapEntry(key.toString(), value.toString()),
      ),
      folders: (json['folders'] as List? ?? [])
          .map((item) => item.toString())
          .toList(),
      locations: (json['locations'] as List? ?? [])
          .map((item) => StatePlace.fromJson(item as Map<String, dynamic>))
          .toList(),
      cases: (json['cases'] as List? ?? [])
          .map((item) => CaseSummary.fromJson(item as Map<String, dynamic>))
          .toList(),
      templates: (json['templates'] as List? ?? [])
          .map((item) => CaseTemplate.fromJson(item as Map<String, dynamic>))
          .toList(),
      notifications: (json['notifications'] as List? ?? [])
          .map((item) => AppNotification.fromJson(item as Map<String, dynamic>))
          .toList(),
      permissions: json.containsKey('permissions')
          ? _readPermissions(json['permissions'])
          : null,
      active: json['case'] == null
          ? null
          : CaseDetail.fromJson(json['case'] as Map<String, dynamic>),
    );
  }

  Workspace withActive(CaseDetail detail) {
    return Workspace(
      filter: filter,
      today: today,
      counts: counts,
      filters: filters,
      tabs: tabs,
      party1Roles: party1Roles,
      party2Roles: party2Roles,
      folders: folders,
      locations: locations,
      cases: [
        for (final item in cases)
          if (item.id == detail.id) detail else item,
      ],
      templates: templates,
      notifications: notifications,
      permissions: permissions,
      active: detail,
    );
  }
}

Map<String, Map<String, bool>> _readPermissions(Object? raw) {
  final grants = <String, Map<String, bool>>{};
  if (raw is Map) {
    raw.forEach((group, actions) {
      final parsed = <String, bool>{};
      if (actions is Map) {
        actions.forEach((action, value) {
          parsed[action.toString()] = value == true;
        });
      }
      grants[group.toString()] = parsed;
    });
  }
  return grants;
}
