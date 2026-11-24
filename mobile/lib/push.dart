import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:onesignal_flutter/onesignal_flutter.dart';

class CasebookPush {
  static String get appId => dotenv.env['ONESIGNAL_APP_ID']?.trim() ?? '';

  static bool get enabled => appId.isNotEmpty;

  static Future<void> start() async {
    if (!enabled) {
      return;
    }
    OneSignal.initialize(appId);
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
