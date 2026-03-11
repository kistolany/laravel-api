import 'dart:io';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../config/api_service.dart';
import '../../models/teacher_model.dart';
import '../../providers/attendance_provider.dart';
import '../../providers/auth_provider.dart';
import '../auth/login_screen.dart';

class TeacherProfileScreen extends StatelessWidget {
  const TeacherProfileScreen({super.key});

  static const String routeName = '/profile';

  @override
  Widget build(BuildContext context) {
    final AuthProvider authProvider = context.watch<AuthProvider>();
    final TeacherModel? teacher = authProvider.currentTeacher;
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

    final ImageProvider<Object>? imageProvider = _resolveImageProvider(
      teacher.imagePath,
    );

    return Scaffold(
      appBar: AppBar(title: const Text('Teacher Profile')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(22),
                  child: Column(
                    children: <Widget>[
                      CircleAvatar(
                        radius: 46,
                        backgroundColor: Theme.of(
                          context,
                        ).colorScheme.primary.withValues(alpha: 0.12),
                        backgroundImage: imageProvider,
                        child: imageProvider == null
                            ? Text(
                                teacher.initials,
                                style: Theme.of(
                                  context,
                                ).textTheme.headlineMedium,
                              )
                            : null,
                      ),
                      const SizedBox(height: 14),
                      Text(
                        teacher.fullName,
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 6),
                      Text(
                        '${teacher.majorName} - ${teacher.subjectName}',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 18),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(22),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        'Teacher Information',
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 18),
                      _ProfileInfoRow(label: 'Email', value: teacher.email),
                      _ProfileInfoRow(
                        label: 'Username',
                        value: teacher.username,
                      ),
                      _ProfileInfoRow(label: 'Gender', value: teacher.gender),
                      _ProfileInfoRow(label: 'Major', value: teacher.majorName),
                      _ProfileInfoRow(
                        label: 'Subject',
                        value: teacher.subjectName,
                      ),
                      _ProfileInfoRow(
                        label: 'Phone',
                        value: teacher.phoneNumber,
                      ),
                      _ProfileInfoRow(
                        label: 'Telegram',
                        value: teacher.telegram,
                      ),
                      _ProfileInfoRow(label: 'Address', value: teacher.address),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 18),
              if (!authProvider.profileUpdateSupported)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Icon(
                          Icons.info_outline_rounded,
                          color: Theme.of(context).colorScheme.primary,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'Profile editing is disabled because the provided API collection does not expose a teacher profile update endpoint.',
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              const SizedBox(height: 20),
              OutlinedButton.icon(
                onPressed: () => _logout(context),
                icon: const Icon(Icons.logout_rounded),
                label: const Text('Logout'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _logout(BuildContext context) async {
    await context.read<AuthProvider>().logout();
    if (!context.mounted) {
      return;
    }
    context.read<AttendanceProvider>().reset();
    Navigator.of(
      context,
    ).pushNamedAndRemoveUntil(LoginScreen.routeName, (_) => false);
  }

  ImageProvider<Object>? _resolveImageProvider(String? imagePath) {
    if (imagePath == null || imagePath.isEmpty) {
      return null;
    }
    if (imagePath.startsWith('http') || imagePath.startsWith('/')) {
      return CachedNetworkImageProvider(ApiService.resolveAssetUrl(imagePath));
    }
    return FileImage(File(imagePath));
  }
}

class _ProfileInfoRow extends StatelessWidget {
  const _ProfileInfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          SizedBox(
            width: 88,
            child: Text(label, style: Theme.of(context).textTheme.bodySmall),
          ),
          Expanded(
            child: Text(value, style: Theme.of(context).textTheme.bodyMedium),
          ),
        ],
      ),
    );
  }
}

