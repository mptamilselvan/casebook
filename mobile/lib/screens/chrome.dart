import 'package:material_ui/material_ui.dart';

import '../app_state.dart';
import '../models.dart';
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
    this.notifications = const [],
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
  final List<AppNotification> notifications;

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
          ],
          _NotificationButton(items: notifications),
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
      ),
    );
  }
}

class _NotificationButton extends StatelessWidget {
  const _NotificationButton({required this.items});

  final List<AppNotification> items;

  @override
  Widget build(BuildContext context) {
    final count = items.length;

    return IconButton(
      tooltip: 'Notifications',
      onPressed: () {
        showModalBottomSheet<void>(
          context: context,
          backgroundColor: Colors.white,
          shape: const RoundedRectangleBorder(
            borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
          ),
          builder: (context) => _NotificationSheet(items: items),
        );
      },
      icon: Stack(
        clipBehavior: Clip.none,
        children: [
          const Icon(Icons.notifications_none, size: 22, color: casebookText),
          if (count > 0)
            Positioned(
              right: -6,
              top: -4,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                decoration: BoxDecoration(
                  color: const Color(0xFFEF4444),
                  borderRadius: BorderRadius.circular(8),
                ),
                alignment: Alignment.center,
                child: Text(
                  count > 99 ? '99+' : '$count',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _NotificationSheet extends StatelessWidget {
  const _NotificationSheet({required this.items});

  final List<AppNotification> items;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Padding(
            padding: EdgeInsets.fromLTRB(20, 16, 20, 8),
            child: Text(
              'Notifications',
              style: TextStyle(
                color: casebookText,
                fontSize: 18,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          if (items.isEmpty)
            const Padding(
              padding: EdgeInsets.fromLTRB(20, 8, 20, 24),
              child: Text(
                'No notifications',
                style: TextStyle(color: casebookMuted),
              ),
            )
          else
            Flexible(
              child: ListView.separated(
                shrinkWrap: true,
                itemCount: items.length,
                separatorBuilder: (context, index) =>
                    const Divider(height: 1, color: casebookLine),
                itemBuilder: (context, index) {
                  final item = items[index];
                  return ListTile(
                    title: Text(
                      item.title,
                      style: const TextStyle(
                        color: casebookText,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    subtitle: Text(
                      item.date,
                      style: const TextStyle(color: casebookMuted),
                    ),
                  );
                },
              ),
            ),
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
