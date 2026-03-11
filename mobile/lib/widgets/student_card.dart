import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../config/api_service.dart';
import '../models/student_model.dart';

class StudentCard extends StatelessWidget {
  const StudentCard({
    required this.student,
    super.key,
    this.trailing,
    this.onTap,
  });

  final StudentModel student;
  final Widget? trailing;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final ImageProvider<Object>? imageProvider =
        student.photoUrl != null && student.photoUrl!.isNotEmpty
        ? CachedNetworkImageProvider(ApiService.resolveAssetUrl(student.photoUrl!))
        : null;

    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(24),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Row(
            children: <Widget>[
              CircleAvatar(
                radius: 28,
                backgroundColor: Theme.of(
                  context,
                ).colorScheme.primary.withValues(alpha: 0.12),
                backgroundImage: imageProvider,
                child: imageProvider == null
                    ? Text(
                        student.initials,
                        style: Theme.of(context).textTheme.titleMedium,
                      )
                    : null,
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      student.name,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'ID: ${student.studentCode}',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${student.className} - Year ${student.year}',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              if (trailing case final Widget trailingWidget) trailingWidget,
            ],
          ),
        ),
      ),
    );
  }
}

