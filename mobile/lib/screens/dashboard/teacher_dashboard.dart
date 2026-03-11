import 'dart:io';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../config/api_service.dart';
import '../../models/teacher_model.dart';
import '../../providers/auth_provider.dart';
import '../attendance/attendance_history_screen.dart';
import '../attendance/mark_attendance_screen.dart';
import '../auth/login_screen.dart';
import '../profile/teacher_profile_screen.dart';
import '../students/student_list_screen.dart';

class TeacherDashboard extends StatelessWidget {
  const TeacherDashboard({super.key});

  static const String routeName = '/dashboard';

  @override
  Widget build(BuildContext context) {
    final TeacherModel? teacher = context.watch<AuthProvider>().currentTeacher;
    if (teacher == null) {
      return Scaffold(
        body: Center(
          child: FilledButton(
            onPressed: () {
              Navigator.of(
                context,
              ).pushNamedAndRemoveUntil(LoginScreen.routeName, (_) => false);
            },
            child: const Text('Return to Login'),
          ),
        ),
      );
    }

    final ThemeData theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Teacher Dashboard'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Profile',
            onPressed: () {
              Navigator.of(context).pushNamed(TeacherProfileScreen.routeName);
            },
            icon: const Icon(Icons.account_circle_outlined),
          ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Container(
                padding: const EdgeInsets.all(22),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: <Color>[Color(0xFF0F5FD7), Color(0xFF5D9EF6)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(28),
                ),
                child: Row(
                  children: <Widget>[
                    _TeacherAvatar(
                      imagePath: teacher.imagePath,
                      initials: teacher.initials,
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            'Welcome back, ${teacher.firstName}',
                            style: theme.textTheme.titleLarge?.copyWith(
                              color: Colors.white,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            teacher.subjectName,
                            style: theme.textTheme.bodyLarge?.copyWith(
                              color: Colors.white.withValues(alpha: 0.92),
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            '${teacher.majorName} - ${DateFormat('EEEE, dd MMM yyyy').format(DateTime.now())}',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: Colors.white.withValues(alpha: 0.88),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              Row(
                children: <Widget>[
                  Expanded(
                    child: _SummaryCard(
                      icon: Icons.groups_2_rounded,
                      label: 'Major',
                      value: teacher.majorName,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _SummaryCard(
                      icon: Icons.verified_user_outlined,
                      label: 'Status',
                      value: teacher.isVerified ? 'Verified' : 'Pending',
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),
              Text('Quick Actions', style: theme.textTheme.titleLarge),
              const SizedBox(height: 14),
              GridView.count(
                crossAxisCount: 2,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                mainAxisSpacing: 14,
                crossAxisSpacing: 14,
                childAspectRatio: 1.02,
                children: <Widget>[
                  _ActionCard(
                    title: 'View Students',
                    subtitle: 'Filter students by class and year.',
                    icon: Icons.badge_outlined,
                    onTap: () {
                      Navigator.of(
                        context,
                      ).pushNamed(StudentListScreen.routeName);
                    },
                  ),
                  _ActionCard(
                    title: 'Mark Attendance',
                    subtitle: 'Create a fresh attendance session.',
                    icon: Icons.fact_check_outlined,
                    onTap: () {
                      Navigator.of(
                        context,
                      ).pushNamed(MarkAttendanceScreen.routeName);
                    },
                  ),
                  _ActionCard(
                    title: 'Attendance History',
                    subtitle: 'Review previous attendance logs.',
                    icon: Icons.history_edu_outlined,
                    onTap: () {
                      Navigator.of(
                        context,
                      ).pushNamed(AttendanceHistoryScreen.routeName);
                    },
                  ),
                  _ActionCard(
                    title: 'Profile',
                    subtitle: 'Update contact info and image.',
                    icon: Icons.account_circle_outlined,
                    onTap: () {
                      Navigator.of(
                        context,
                      ).pushNamed(TeacherProfileScreen.routeName);
                    },
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ActionCard extends StatelessWidget {
  const _ActionCard({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(24),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Container(
                height: 52,
                width: 52,
                decoration: BoxDecoration(
                  color: Theme.of(
                    context,
                  ).colorScheme.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(icon, color: Theme.of(context).colorScheme.primary),
              ),
              const Spacer(),
              Text(title, style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 6),
              Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
            ],
          ),
        ),
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Row(
          children: <Widget>[
            Icon(icon, color: Theme.of(context).colorScheme.primary),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(label, style: Theme.of(context).textTheme.bodySmall),
                  const SizedBox(height: 4),
                  Text(value, style: Theme.of(context).textTheme.titleMedium),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TeacherAvatar extends StatelessWidget {
  const _TeacherAvatar({required this.imagePath, required this.initials});

  final String? imagePath;
  final String initials;

  @override
  Widget build(BuildContext context) {
    ImageProvider<Object>? imageProvider;
    if (imagePath != null &&
        imagePath!.isNotEmpty &&
        (imagePath!.startsWith('http') || imagePath!.startsWith('/'))) {
      imageProvider = CachedNetworkImageProvider(
        ApiService.resolveAssetUrl(imagePath!),
      );
    } else if (imagePath != null && imagePath!.isNotEmpty) {
      imageProvider = FileImage(File(imagePath!));
    }

    return CircleAvatar(
      radius: 32,
      backgroundColor: Colors.white.withValues(alpha: 0.2),
      backgroundImage: imageProvider,
      child: imageProvider == null
          ? Text(
              initials,
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(color: Colors.white),
            )
          : null,
    );
  }
}

