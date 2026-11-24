import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:onesignal_flutter/onesignal_flutter.dart';

class CasebookPush {
  static void Function(int caseId)? onCaseOpened;
  static int? _pendingCaseId;

  static String get appId => dotenv.env['ONESIGNAL_APP_ID']?.trim() ?? '';

  static bool get enabled => appId.isNotEmpty;

  static int? takePendingCase() {
    final id = _pendingCaseId;
    _pendingCaseId = null;
    return id;
  }

  static Future<void> start() async {
    if (!enabled) {
      return;
    }
    OneSignal.initialize(appId);
    OneSignal.Notifications.addClickListener((event) {
      final raw = event.notification.additionalData?['case_id'];
      final id = raw is int ? raw : int.tryParse('${raw ?? ''}');
      if (id == null) {
        return;
      }
      if (onCaseOpened == null) {
        _pendingCaseId = id;
        return;
      }
      onCaseOpened!(id);
    });
    await OneSignal.Notifications.requestPermission(true);
  }

  static Future<void> login(int userId) async {
    if (!enabled) {
      return;
    }
    await OneSignal.login(userId.toString());
  }

  static Future<void> logout() async {
    if (!enabled) {
      return;
    }
    await OneSignal.logout();
  }
}
