import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../models/attendance_model.dart';
import '../../models/attendance_session_model.dart';
import '../../models/teacher_class_model.dart';
import '../../models/teacher_model.dart';
import '../../providers/attendance_provider.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/custom_button.dart';
import '../auth/login_screen.dart';

class EditAttendanceScreen extends StatefulWidget {
  const EditAttendanceScreen({super.key, this.initialSessionId});

  static const String routeName = '/attendance/edit';

  final String? initialSessionId;

  @override
  State<EditAttendanceScreen> createState() => _EditAttendanceScreenState();
}

class _EditAttendanceScreenState extends State<EditAttendanceScreen> {
  DateTime _selectedDate = DateTime.now();
  String? _selectedClassId;
  String? _selectedSubjectId;
  int? _selectedSessionNumber;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _initialize());
  }

  Future<void> _initialize() async {
    final AttendanceProvider attendanceProvider = context
        .read<AttendanceProvider>();
    final TeacherModel? teacher = context.read<AuthProvider>().currentTeacher;
    if (teacher == null) {
      return;
    }

    await attendanceProvider.loadTeacherClasses();
    await attendanceProvider.loadHistory();

    if (!mounted) {
      return;
    }

    setState(() {
      _selectedSubjectId = teacher.subjectId;
      _selectedSessionNumber = attendanceProvider.sessionNumbers.first;
    });

    if (widget.initialSessionId != null &&
        widget.initialSessionId!.isNotEmpty) {
      await attendanceProvider.loadEditableAttendance(widget.initialSessionId!);
      final AttendanceSessionModel? session =
          attendanceProvider.editableSession;
      if (!mounted || session == null) {
        return;
      }
      setState(() {
        _selectedDate = session.sessionDate;
        _selectedClassId = session.classId;
        _selectedSubjectId = session.subjectId;
        _selectedSessionNumber = session.sessionNumber;
      });
    }
  }

  Future<void> _pickDate() async {
    final DateTime? date = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime(2024),
      lastDate: DateTime(2030),
    );
    if (date == null) {
      return;
    }
    setState(() {
      _selectedDate = date;
    });
  }

  Future<void> _loadRecords() async {
    final TeacherModel? teacher = context.read<AuthProvider>().currentTeacher;
    if (teacher == null ||
        _selectedClassId == null ||
        _selectedSubjectId == null ||
        _selectedSessionNumber == null) {
      return;
    }

    final AttendanceProvider attendanceProvider = context
        .read<AttendanceProvider>();
    final AttendanceSessionModel? session = attendanceProvider
        .findHistorySession(
          date: _selectedDate,
          classId: _selectedClassId!,
          subjectId: _selectedSubjectId!,
          sessionNumber: _selectedSessionNumber!,
        );

    if (session == null) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No attendance session matched the selected filters.'),
        ),
      );
      return;
    }

    await attendanceProvider.loadEditableAttendance(session.id);
    if (!mounted) {
      return;
    }

    if (attendanceProvider.errorMessage case final String message) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    }
  }

  Future<void> _saveChanges() async {
    final AttendanceProvider attendanceProvider = context
        .read<AttendanceProvider>();
    final bool success = await attendanceProvider.saveEditedAttendance();
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          success
              ? 'Attendance updated successfully.'
              : (attendanceProvider.errorMessage ??
                    'Unable to update attendance.'),
        ),
      ),
    );
  }

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

    final AttendanceProvider attendanceProvider = context
        .watch<AttendanceProvider>();
    final List<TeacherClassModel> classes = attendanceProvider.teacherClasses;

    return Scaffold(
      appBar: AppBar(title: const Text('Edit Attendance')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          children: <Widget>[
            Text(
              'Update Attendance Records',
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            const SizedBox(height: 18),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  children: <Widget>[
                    InkWell(
                      onTap: _pickDate,
                      borderRadius: BorderRadius.circular(20),
                      child: InputDecorator(
                        decoration: const InputDecoration(
                          labelText: 'Date',
                          prefixIcon: Icon(Icons.date_range_outlined),
                        ),
                        child: Text(
                          DateFormat('dd MMM yyyy').format(_selectedDate),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String?>(
                      initialValue: _selectedClassId,
                      decoration: const InputDecoration(
                        labelText: 'Class',
                        prefixIcon: Icon(Icons.class_outlined),
                      ),
                      items: classes
                          .map(
                            (TeacherClassModel classItem) =>
                                DropdownMenuItem<String?>(
                                  value: classItem.id,
                                  child: Text(classItem.displayName),
                                ),
                          )
                          .toList(growable: false),
                      onChanged: (String? value) {
                        setState(() {
                          _selectedClassId = value;
                        });
                      },
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String>(
                      initialValue: _selectedSubjectId ?? teacher.subjectId,
                      decoration: const InputDecoration(
                        labelText: 'Subject',
                        prefixIcon: Icon(Icons.menu_book_outlined),
                      ),
                      items: <DropdownMenuItem<String>>[
                        DropdownMenuItem<String>(
                          value: teacher.subjectId,
                          child: Text(teacher.subjectName),
                        ),
                      ],
                      onChanged: null,
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<int>(
                      initialValue: _selectedSessionNumber,
                      decoration: const InputDecoration(
                        labelText: 'Session',
                        prefixIcon: Icon(Icons.schedule_outlined),
                      ),
                      items: attendanceProvider.sessionNumbers
                          .map(
                            (int sessionNumber) => DropdownMenuItem<int>(
                              value: sessionNumber,
                              child: Text('Session $sessionNumber'),
                            ),
                          )
                          .toList(growable: false),
                      onChanged: (int? value) {
                        setState(() {
                          _selectedSessionNumber = value;
                        });
                      },
                    ),
                    if (attendanceProvider.errorMessage
                        case final String message) ...<Widget>[
                      const SizedBox(height: 16),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          message,
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(
                                color: Theme.of(context).colorScheme.error,
                              ),
                        ),
                      ),
                    ],
                    const SizedBox(height: 18),
                    CustomButton(
                      label: 'Load Records',
                      icon: Icons.search_rounded,
                      isLoading: attendanceProvider.isLoading,
                      onPressed:
                          _selectedClassId == null ||
                              _selectedSessionNumber == null
                          ? null
                          : _loadRecords,
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),
            if (attendanceProvider.editableRecords.isEmpty &&
                !attendanceProvider.isLoading)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Text(
                    'No records are loaded for editing.',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ),
              ),
            ...attendanceProvider.editableRecords.map(
              (AttendanceModel record) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _EditableAttendanceTile(
                  record: record,
                  onChanged: (AttendanceStatus status) {
                    context.read<AttendanceProvider>().updateEditableStatus(
                      record.studentId,
                      status,
                    );
                  },
                ),
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: attendanceProvider.editableRecords.isEmpty
          ? null
          : SafeArea(
              minimum: const EdgeInsets.fromLTRB(20, 12, 20, 20),
              child: CustomButton(
                label: 'Update Attendance',
                icon: Icons.edit_calendar_outlined,
                isLoading: attendanceProvider.isLoading,
                onPressed: _saveChanges,
              ),
            ),
    );
  }
}

class _EditableAttendanceTile extends StatelessWidget {
  const _EditableAttendanceTile({
    required this.record,
    required this.onChanged,
  });

  final AttendanceModel record;
  final ValueChanged<AttendanceStatus> onChanged;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              record.studentName,
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 4),
            Text(
              '${record.studentCode} - ${record.className}',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: AttendanceStatus.values
                  .map(
                    (AttendanceStatus status) => ChoiceChip(
                      label: Text(status.label),
                      selected: record.status == status,
                      onSelected: (_) => onChanged(status),
                    ),
                  )
                  .toList(growable: false),
            ),
          ],
        ),
      ),
    );
  }
}
