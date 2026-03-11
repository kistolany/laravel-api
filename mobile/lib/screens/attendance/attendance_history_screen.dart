import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../models/attendance_session_model.dart';
import '../../models/teacher_class_model.dart';
import '../../models/teacher_model.dart';
import '../../providers/attendance_provider.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/custom_button.dart';
import 'edit_attendance_screen.dart';
import '../auth/login_screen.dart';

class AttendanceHistoryScreen extends StatefulWidget {
  const AttendanceHistoryScreen({super.key});

  static const String routeName = '/attendance/history';

  @override
  State<AttendanceHistoryScreen> createState() =>
      _AttendanceHistoryScreenState();
}

class _AttendanceHistoryScreenState extends State<AttendanceHistoryScreen> {
  DateTime? _selectedDate;
  String? _selectedClassId;
  String? _selectedSubjectId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _initialize());
  }

  Future<void> _initialize() async {
    final TeacherModel? teacher = context.read<AuthProvider>().currentTeacher;
    if (teacher == null) {
      return;
    }

    final AttendanceProvider attendanceProvider = context
        .read<AttendanceProvider>();
    await attendanceProvider.loadTeacherClasses();
    await attendanceProvider.loadHistory();
  }

  Future<void> _loadHistory() async {
    await context.read<AttendanceProvider>().loadHistory();
  }

  Future<void> _pickDate() async {
    final DateTime? date = await showDatePicker(
      context: context,
      initialDate: _selectedDate ?? DateTime.now(),
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
    final List<AttendanceSessionModel> sessions = attendanceProvider
        .filteredHistorySessions(
          date: _selectedDate,
          classId: _selectedClassId,
          subjectId: _selectedSubjectId,
        );

    return Scaffold(
      appBar: AppBar(
        title: const Text('Attendance History'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Edit attendance',
            onPressed: () {
              Navigator.of(context).pushNamed(EditAttendanceScreen.routeName);
            },
            icon: const Icon(Icons.edit_note_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          children: <Widget>[
            Text(
              'Past Attendance Sessions',
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
                          labelText: 'Date Filter',
                          prefixIcon: Icon(Icons.date_range_outlined),
                        ),
                        child: Text(
                          _selectedDate == null
                              ? 'All Dates'
                              : DateFormat(
                                  'dd MMM yyyy',
                                ).format(_selectedDate!),
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
                      items: <DropdownMenuItem<String?>>[
                        const DropdownMenuItem<String?>(
                          value: null,
                          child: Text('All Classes'),
                        ),
                        ...classes.map(
                          (TeacherClassModel classItem) =>
                              DropdownMenuItem<String?>(
                                value: classItem.id,
                                child: Text(classItem.displayName),
                              ),
                        ),
                      ],
                      onChanged: (String? value) {
                        setState(() {
                          _selectedClassId = value;
                        });
                      },
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String?>(
                      initialValue: _selectedSubjectId,
                      decoration: const InputDecoration(
                        labelText: 'Subject',
                        prefixIcon: Icon(Icons.menu_book_outlined),
                      ),
                      items: <DropdownMenuItem<String?>>[
                        const DropdownMenuItem<String?>(
                          value: null,
                          child: Text('All Subjects'),
                        ),
                        DropdownMenuItem<String?>(
                          value: teacher.subjectId,
                          child: Text(teacher.subjectName),
                        ),
                      ],
                      onChanged: (String? value) {
                        setState(() {
                          _selectedSubjectId = value;
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
                      label: 'Refresh History',
                      icon: Icons.refresh_rounded,
                      isLoading: attendanceProvider.isLoading,
                      onPressed: _loadHistory,
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 18),
            if (sessions.isEmpty && !attendanceProvider.isLoading)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Text(
                    'No attendance history matches the selected filters.',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ),
              ),
            ...sessions.map(
              (AttendanceSessionModel session) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Card(
                  child: InkWell(
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => EditAttendanceScreen(
                            initialSessionId: session.id,
                          ),
                        ),
                      );
                    },
                    borderRadius: BorderRadius.circular(24),
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            '${DateFormat('dd MMM yyyy').format(session.sessionDate)} - ${session.className}',
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${session.subjectName} - ${session.sessionLabel}',
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                          const SizedBox(height: 14),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: <Widget>[
                              _HistoryChip(
                                label: 'Students',
                                value: session.studentCount.toString(),
                              ),
                              _HistoryChip(
                                label: 'Present',
                                value: session.presentCount.toString(),
                              ),
                              _HistoryChip(
                                label: 'Absent',
                                value: session.absentCount.toString(),
                              ),
                              _HistoryChip(
                                label: 'Late',
                                value: session.lateCount.toString(),
                              ),
                              _HistoryChip(
                                label: 'Excused',
                                value: session.excusedCount.toString(),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HistoryChip extends StatelessWidget {
  const _HistoryChip({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Chip(label: Text('$label: $value'));
  }
}
