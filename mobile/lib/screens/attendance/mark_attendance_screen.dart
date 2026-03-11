import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../models/attendance_model.dart';
import '../../models/teacher_class_model.dart';
import '../../models/teacher_model.dart';
import '../../providers/attendance_provider.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/custom_button.dart';
import '../auth/login_screen.dart';

class MarkAttendanceScreen extends StatefulWidget {
  const MarkAttendanceScreen({
    super.key,
    this.initialYear,
    this.initialClassId,
  });

  static const String routeName = '/attendance/mark';

  final int? initialYear;
  final String? initialClassId;

  @override
  State<MarkAttendanceScreen> createState() => _MarkAttendanceScreenState();
}

class _MarkAttendanceScreenState extends State<MarkAttendanceScreen> {
  int? _selectedYear;
  int _selectedSessionNumber = 1;
  String? _selectedClassId;
  DateTime _selectedDate = DateTime.now();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _initializeDefaults());
  }

  Future<void> _initializeDefaults() async {
    final TeacherModel? teacher = context.read<AuthProvider>().currentTeacher;
    if (teacher == null) {
      return;
    }

    final AttendanceProvider attendanceProvider = context
        .read<AttendanceProvider>();
    await attendanceProvider.loadAttendanceOptions();
    if (!mounted) {
      return;
    }

    final List<int> years = attendanceProvider.availableYears(
      attendanceOnly: true,
    );
    final int? selectedYear =
        widget.initialYear ?? (years.isEmpty ? null : years.first);
    final List<TeacherClassModel> classes = attendanceProvider.classesForYear(
      year: selectedYear,
      attendanceOnly: true,
    );
    final String? initialClassId =
        widget.initialClassId != null &&
            classes.any(
              (TeacherClassModel item) => item.id == widget.initialClassId,
            )
        ? widget.initialClassId
        : (classes.isEmpty ? null : classes.first.id);

    setState(() {
      _selectedYear = selectedYear;
      _selectedSessionNumber = attendanceProvider.sessionNumbers.first;
      _selectedClassId = initialClassId;
    });

    if (_selectedClassId != null) {
      await _loadDraft();
    }
  }

  Future<void> _loadDraft() async {
    if (_selectedClassId == null) {
      return;
    }

    final AttendanceProvider attendanceProvider = context
        .read<AttendanceProvider>();
    await attendanceProvider.prepareAttendanceDraft(
      classId: _selectedClassId!,
      date: _selectedDate,
      sessionNumber: _selectedSessionNumber,
    );

    if (!mounted) {
      return;
    }

    if (attendanceProvider.errorMessage case final String message) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
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

  Future<void> _saveAttendance() async {
    final AttendanceProvider attendanceProvider = context
        .read<AttendanceProvider>();
    final bool success = await attendanceProvider.submitDraft();
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          success
              ? 'Attendance saved successfully.'
              : (attendanceProvider.errorMessage ??
                    'Unable to save attendance.'),
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
    final String majorName =
        attendanceProvider.attendanceOptions?.majorName.isNotEmpty == true
        ? attendanceProvider.attendanceOptions!.majorName
        : teacher.majorName;
    final String subjectName =
        attendanceProvider.attendanceOptions?.subjectName.isNotEmpty == true
        ? attendanceProvider.attendanceOptions!.subjectName
        : teacher.subjectName;
    final String subjectId =
        attendanceProvider.attendanceOptions?.subjectId.isNotEmpty == true
        ? attendanceProvider.attendanceOptions!.subjectId
        : teacher.subjectId;
    final List<int> yearOptions = attendanceProvider.availableYears(
      attendanceOnly: true,
    );
    final List<TeacherClassModel> classOptions = attendanceProvider
        .classesForYear(year: _selectedYear, attendanceOnly: true);

    return Scaffold(
      appBar: AppBar(title: const Text('Mark Attendance')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          children: <Widget>[
            Text(
              'Create Attendance Session',
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            const SizedBox(height: 8),
            Text(
              'Choose the class, session number, and date. The student list will load from the teacher attendance endpoints.',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 18),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  children: <Widget>[
                    DropdownButtonFormField<String>(
                      initialValue: teacher.majorId,
                      decoration: const InputDecoration(
                        labelText: 'Major',
                        prefixIcon: Icon(Icons.account_tree_outlined),
                      ),
                      items: <DropdownMenuItem<String>>[
                        DropdownMenuItem<String>(
                          value: teacher.majorId,
                          child: Text(majorName),
                        ),
                      ],
                      onChanged: null,
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<int?>(
                      initialValue: _selectedYear,
                      decoration: const InputDecoration(
                        labelText: 'Year',
                        prefixIcon: Icon(Icons.calendar_view_month_outlined),
                      ),
                      items: yearOptions
                          .map(
                            (int year) => DropdownMenuItem<int?>(
                              value: year,
                              child: Text('Year $year'),
                            ),
                          )
                          .toList(growable: false),
                      onChanged: (int? value) {
                        final List<TeacherClassModel> availableClasses =
                            attendanceProvider.classesForYear(
                              year: value,
                              attendanceOnly: true,
                            );
                        setState(() {
                          _selectedYear = value;
                          _selectedClassId = availableClasses.isEmpty
                              ? null
                              : availableClasses.first.id;
                        });
                      },
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
                        if (value == null) {
                          return;
                        }
                        setState(() {
                          _selectedSessionNumber = value;
                        });
                      },
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String?>(
                      initialValue: _selectedClassId,
                      decoration: const InputDecoration(
                        labelText: 'Class',
                        prefixIcon: Icon(Icons.class_outlined),
                      ),
                      items: classOptions
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
                      initialValue: subjectId,
                      decoration: const InputDecoration(
                        labelText: 'Subject',
                        prefixIcon: Icon(Icons.menu_book_outlined),
                      ),
                      items: <DropdownMenuItem<String>>[
                        DropdownMenuItem<String>(
                          value: subjectId,
                          child: Text(subjectName),
                        ),
                      ],
                      onChanged: null,
                    ),
                    const SizedBox(height: 16),
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
                      label: 'Load Students',
                      icon: Icons.playlist_add_check_circle_outlined,
                      isLoading: attendanceProvider.isLoading,
                      onPressed: _selectedClassId == null ? null : _loadDraft,
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),
            if (attendanceProvider.draftRecords.isEmpty &&
                !attendanceProvider.isLoading)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Text(
                    'Select the filters above, then load the student list to mark attendance.',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ),
              ),
            ...attendanceProvider.draftRecords.map(
              (AttendanceModel record) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _AttendanceStudentTile(
                  record: record,
                  onChanged: (AttendanceStatus status) {
                    context.read<AttendanceProvider>().updateDraftStatus(
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
      bottomNavigationBar: attendanceProvider.draftRecords.isEmpty
          ? null
          : SafeArea(
              minimum: const EdgeInsets.fromLTRB(20, 12, 20, 20),
              child: CustomButton(
                label: 'Save Attendance',
                icon: Icons.save_outlined,
                isLoading: attendanceProvider.isLoading,
                onPressed: _saveAttendance,
              ),
            ),
    );
  }
}

class _AttendanceStudentTile extends StatelessWidget {
  const _AttendanceStudentTile({required this.record, required this.onChanged});

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
                      label: Text('${status.shortLabel} - ${status.label}'),
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
