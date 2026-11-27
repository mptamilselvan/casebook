import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api.dart';
import 'models.dart';
import 'push.dart';

class AppState extends ChangeNotifier {
  AppState(this.api) {
    CasebookPush.onCaseOpened = (caseId) {
      load(caseId: caseId);
    };
  }

  final ApiClient api;
  UserAccount? user;
  Workspace? workspace;
  String? mode;
  String filter = 'All';
  String search = '';
  bool busy = false;
  String? error;

  bool allows(String group, String action) {
    return workspace?.allows(group, action) ?? true;
  }

  List<CaseSummary> get visibleCases {
    final query = search.trim().toLowerCase();
    final cases = workspace?.cases ?? [];
    if (query.isEmpty) {
      return cases;
    }
    return cases.where((item) => item.haystack.contains(query)).toList();
  }

  Future<bool> restore() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString('casebook_token');
    if (saved == null || saved.isEmpty) {
      return false;
    }
    api.token = saved;
    mode = prefs.getString('casebook_mode');
    api.mode = mode;
    try {
      user = UserAccount.fromJson(
        await api.getJson('me') as Map<String, dynamic>,
      );
      await CasebookPush.login(user!.id);
      if (mode == null) {
        return true;
      }
      await load(caseId: CasebookPush.takePendingCase());
      return true;
    } catch (_) {
      await clearSession();
      return false;
    }
  }

  Future<String> login(String email) async {
    final body = await api.postJson('login', {
      'email': email.trim(),
    }) as Map<String, dynamic>;
    if (body['status'] == 'authenticated') {
      await _storeSession(body);
    }
    return body['status'] as String? ?? '';
  }

  Future<void> verify(String url) async {
    final body = await api.postJson('email/verify', {
      'url': url.trim(),
    }) as Map<String, dynamic>;
    await _storeSession(body);
  }

  Future<void> chooseMode(String value) async {
    mode = value;
    api.mode = value;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('casebook_mode', value);
    notifyListeners();
    await load(keepSelection: false);
  }

  Future<void> clearMode() async {
    mode = null;
    api.mode = null;
    workspace = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('casebook_mode');
    notifyListeners();
  }

  Future<void> load({int? caseId, bool keepSelection = true}) async {
    final id = caseId ?? (keepSelection ? workspace?.active?.id : null);
    final query = StringBuffer(
      'casebook?filter=${Uri.encodeQueryComponent(filter)}',
    );
    if (id != null) {
      query.write('&case=$id');
    }
    final body = await api.getJson(query.toString()) as Map<String, dynamic>;
    workspace = Workspace.fromJson(body);
    final usage = body['usage'];
    if (user != null && usage is Map) {
      user = user!.withUsage(Map<String, dynamic>.from(usage));
    }
    filter = workspace!.filter;
    notifyListeners();
  }

  void setSearch(String value) {
    search = value;
    notifyListeners();
  }

  Future<void> setFilter(String value) async {
    filter = value;
    notifyListeners();
    await load(keepSelection: false);
  }

  Future<CaseDetail> createCase(Map<String, dynamic> payload) async {
    final created = CaseDetail.fromJson(
      await api.postJson('cases', payload) as Map<String, dynamic>,
    );
    final createdMode = created.mode == 'office' ? 'office' : 'personal';
    if (mode != createdMode) {
      mode = createdMode;
      api.mode = createdMode;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('casebook_mode', createdMode);
    }
    filter = 'All';
    await load(caseId: created.id, keepSelection: false);
    return created;
  }

  Future<void> updateCase(int id, Map<String, dynamic> payload) async {
    final body = await api.putJson('cases/$id', payload);
    final current = workspace;
    if (body is Map<String, dynamic> && current != null) {
      try {
        workspace = current.withActive(CaseDetail.fromJson(body));
        notifyListeners();
      } catch (_) {
        // The saved case is loaded again below.
      }
    }
    await load(caseId: id);
  }

  Future<void> deleteCase(int id) async {
    await api.deleteJson('cases/$id');
    workspace = null;
    await load();
  }

  Future<void> togglePin(int id) async {
    await api.postJson('cases/$id/pin', {});
    await load(caseId: id);
  }

  Future<void> updatePriority(int id, String priority) async {
    await api.putJson('cases/$id/priority', {'priority': priority});
    await load(caseId: id);
  }

  Future<void> updateStatus(int id, String status) async {
    await api.putJson('cases/$id/status', {'status': status});
    await load(caseId: id);
  }

  Future<bool> updateMode(int id, String mode) async {
    await api.putJson('cases/$id/mode', {'mode': mode});
    final stays = (this.mode ?? 'personal') == mode;
    await load(caseId: stays ? id : null, keepSelection: false);
    return stays;
  }

  Future<void> updateHearing(int id, String? date) async {
    await api.putJson('cases/$id/hearing', {'next_hearing': date});
    await load(caseId: id);
  }

  Future<void> updateTasks(int id, String tasks) async {
    await api.putJson('cases/$id/tasks', {'tasks': tasks});
    await load(caseId: id);
  }

  Future<void> syncParties(int id, Map<String, dynamic> payload) async {
    final body = await api.putJson('cases/$id/parties', payload);
    final current = workspace;
    if (body is Map<String, dynamic> && current != null) {
      try {
        workspace = current.withActive(CaseDetail.fromJson(body));
        notifyListeners();
      } catch (_) {
        // The saved case is loaded again below.
      }
    }
    await load(caseId: id);
  }

  Future<void> saveEvent(
    int caseId,
    Map<String, String> fields, {
    int? eventId,
    List<int>? bytes,
    String? filename,
  }) async {
    final path = eventId == null
        ? 'cases/$caseId/events'
        : 'cases/$caseId/events/$eventId';
    final method = eventId == null ? 'POST' : 'PUT';
    await api.upload(method, path, fields, bytes: bytes, filename: filename);
    await load(caseId: caseId);
  }

  Future<void> deleteEvent(int caseId, int eventId) async {
    await api.deleteJson('cases/$caseId/events/$eventId');
    await load(caseId: caseId);
  }

  Future<void> saveDocument(
    int caseId,
    String folder,
    String name, {
    List<int>? bytes,
    String? filename,
  }) async {
    await api.upload(
      'POST',
      'cases/$caseId/documents',
      {'folder': folder, 'name': name},
      bytes: bytes,
      filename: filename,
    );
    await load(caseId: caseId);
  }

  Future<void> deleteDocument(int caseId, int documentId) async {
    await api.deleteJson('cases/$caseId/documents/$documentId');
    await load(caseId: caseId);
  }

  Future<CaseTemplate> createTemplate(String title) async {
    final created = CaseTemplate.fromJson(
      await api.postJson('templates', {'title': title}) as Map<String, dynamic>,
    );
    await load();
    return created;
  }

  Future<void> saveTemplate(int id, String content) async {
    await api.putJson('templates/$id', {'content': content});
    await load();
  }

  Future<File> download(String path, String filename) async {
    final bytes = await api.download(path);
    final file = File('${Directory.systemTemp.path}/$filename');
    await file.writeAsBytes(bytes, flush: true);
    return file;
  }

  Future<void> logout() async {
    try {
      await api.postJson('logout', {});
    } catch (_) {}
    await clearSession();
  }

  Future<void> _storeSession(Map<String, dynamic> body) async {
    api.token = body['token'] as String?;
    user = UserAccount.fromJson(body['user'] as Map<String, dynamic>);
    mode = null;
    api.mode = null;
    workspace = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('casebook_token', api.token ?? '');
    await prefs.remove('casebook_mode');
    await CasebookPush.login(user!.id);
    notifyListeners();
  }

  Future<void> clearSession() async {
    api.token = null;
    api.mode = null;
    user = null;
    workspace = null;
    mode = null;
    await CasebookPush.logout();
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('casebook_token');
    await prefs.remove('casebook_mode');
    notifyListeners();
  }
}
