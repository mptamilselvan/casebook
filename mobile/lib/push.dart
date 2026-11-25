import 'dart:async';

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

  static String externalId(int userId) => 'casebook-$userId';

  static Future<void> login(int userId) async {
    if (!enabled) {
      return;
    }
    // OneSignal rejects bare ids such as "1". Do not log out first: that
    // leaves a local user the server does not have, and the next login waits
    // on it until it times out.
    unawaited(OneSignal.login(externalId(userId)));
  }

  static Future<void> logout() async {
    if (!enabled) {
      return;
    }
    unawaited(OneSignal.logout());
  }
}
