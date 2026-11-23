import 'package:material_ui/material_ui.dart';

const casebookPurple = Color(0xFF7C3AED);
const casebookBlue = Color(0xFF3B82F6);
const casebookText = Color(0xFF111827);
const casebookMuted = Color(0xFF6B7280);
const casebookLine = Color(0xFFE5E7EB);
const casebookDanger = Color(0xFFEF4444);
const casebookAmber = Color(0xFFB45309);
const casebookSave = Color(0xFF10B981);

ThemeData casebookTheme() {
  final scheme = ColorScheme.fromSeed(
    seedColor: casebookPurple,
    brightness: Brightness.light,
  );
  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme.copyWith(
      surface: Colors.white,
      primary: casebookPurple,
    ),
    scaffoldBackgroundColor: Colors.white,
    canvasColor: Colors.white,
    fontFamily: 'Roboto',
    appBarTheme: const AppBarTheme(
      backgroundColor: Colors.white,
      foregroundColor: casebookText,
      elevation: 0,
      scrolledUnderElevation: 0,
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      labelStyle: const TextStyle(
        color: casebookMuted,
        fontWeight: FontWeight.w600,
        fontSize: 12,
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFD1D5DB)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFD1D5DB)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: casebookPurple),
      ),
    ),
    bottomSheetTheme: const BottomSheetThemeData(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.white,
    ),
    drawerTheme: const DrawerThemeData(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.white,
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: casebookPurple,
        foregroundColor: Colors.white,
        minimumSize: const Size.fromHeight(46),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        textStyle: const TextStyle(fontWeight: FontWeight.w600),
      ),
    ),
  );
}

InputDecoration casebookField(String label, {String? hint}) {
  return InputDecoration(labelText: label, hintText: hint);
}
