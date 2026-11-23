import 'package:material_ui/material_ui.dart';

import '../app_state.dart';
import '../theme.dart';

String avatarLetter(AppState state) {
  final name = state.user?.name.trim() ?? '';
  final email = state.user?.email.trim() ?? '';
  final source = name.isNotEmpty ? name : (email.isNotEmpty ? email : 'A');
  return source[0].toUpperCase();
}

String formatDay(String? value) {
  if (value == null || value.isEmpty) {
    return '';
  }
  final date = DateTime.tryParse(value);
  if (date == null) {
    return value;
  }
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];
  return '${date.day} ${months[date.month - 1]} ${date.year}';
}

class CasebookTopBar extends StatelessWidget {
  const CasebookTopBar({
    super.key,
    required this.title,
    required this.onMenu,
    this.onAvatar,
    this.avatarLetter = 'A',
    this.searching = false,
    this.searchController,
    this.searchHint = 'Search',
    this.onStartSearch,
    this.onCloseSearch,
    this.onSearchChanged,
  });

  final String title;
  final VoidCallback onMenu;
  final VoidCallback? onAvatar;
  final String avatarLetter;
  final bool searching;
  final TextEditingController? searchController;
  final String searchHint;
  final VoidCallback? onStartSearch;
  final VoidCallback? onCloseSearch;
  final ValueChanged<String>? onSearchChanged;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 4, 12, 4),
      child: Row(
        children: [
          IconButton(
            onPressed: onMenu,
            icon: const Icon(Icons.menu, size: 26, color: casebookText),
          ),
          if (searching && searchController != null)
            Expanded(
              child: Container(
                height: 45,
                margin: const EdgeInsets.only(left: 4),
                padding: const EdgeInsets.symmetric(horizontal: 12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF3F4F6),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.search, size: 18, color: casebookMuted),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextField(
                        controller: searchController,
                        autofocus: true,
                        decoration: InputDecoration(
                          hintText: searchHint,
                          border: InputBorder.none,
                          enabledBorder: InputBorder.none,
                          focusedBorder: InputBorder.none,
                          filled: false,
                          contentPadding: EdgeInsets.zero,
                          isDense: true,
                        ),
                        onChanged: onSearchChanged,
                      ),
                    ),
                    IconButton(
                      onPressed: onCloseSearch,
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(
                        minWidth: 32,
                        minHeight: 32,
                      ),
                      icon: const Icon(
                        Icons.close,
                        size: 20,
                        color: casebookText,
                      ),
                    ),
                  ],
                ),
              ),
            )
          else ...[
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                  color: casebookText,
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            if (onStartSearch != null)
              IconButton(
                onPressed: onStartSearch,
                icon: const Icon(Icons.search, size: 22, color: casebookText),
              ),
            if (onAvatar != null)
              GestureDetector(
                onTap: onAvatar,
                child: CircleAvatar(
                  radius: 16,
                  backgroundColor: casebookPurple,
                  child: Text(
                    avatarLetter,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                    ),
                  ),
                ),
              ),
          ],
        ],
      ),
    );
  }
}

class PurpleButton extends StatelessWidget {
  const PurpleButton({
    super.key,
    required this.icon,
    required this.label,
    required this.onPressed,
  });

  final IconData icon;
  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 20),
      child: FilledButton.icon(
        onPressed: onPressed,
        icon: Icon(icon, size: 16),
        label: Text(label),
      ),
    );
  }
}

class WhiteCard extends StatelessWidget {
  const WhiteCard({
    super.key,
    required this.child,
    this.margin = const EdgeInsets.only(bottom: 10),
    this.padding = const EdgeInsets.all(16),
    this.radius = 12,
  });

  final Widget child;
  final EdgeInsets margin;
  final EdgeInsets padding;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: casebookLine),
      ),
      child: child,
    );
  }
}

class SectionLabel extends StatelessWidget {
  const SectionLabel({super.key, required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Text(
        text.toUpperCase(),
        style: const TextStyle(
          color: casebookPurple,
          fontSize: 14,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}
