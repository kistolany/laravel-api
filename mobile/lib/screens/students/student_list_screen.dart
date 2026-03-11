import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/teacher_class_model.dart';
import '../../models/teacher_model.dart';
import '../../providers/attendance_provider.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/custom_button.dart';
import '../../widgets/student_card.dart';
import '../attendance/mark_attendance_screen.dart';
import '../auth/login_screen.dart';

class StudentListScreen extends StatefulWidget {
  const StudentListScreen({super.key});

  static const String routeName = '/students';

  @override
  State<StudentListScreen> createState() => _StudentListScreenState();
}

class _StudentListScreenState extends State<StudentListScreen> {
  int? _selectedYear;
  String? _selectedClassId;

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
    if (!mounted) {
      return;
    }
    await _loadStudents();
  }

  Future<void> _loadStudents() async {
    final TeacherModel? teacher = context.read<AuthProvider>().currentTeacher;
    if (teacher == null) {
      return;
    }

    await context.read<AttendanceProvider>().loadStudents(
      classId: _selectedClassId,
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
    final List<TeacherClassModel> classOptions = attendanceProvider
        .classesForYear(year: _selectedYear);

    return Scaffold(
      appBar: AppBar(title: const Text('Students')),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _loadStudents,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
            children: <Widget>[
              Text(
                'Students in ${teacher.majorName}',
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              const SizedBox(height: 8),
              Text(
                "Only students from the teacher's teaching major are visible here.",
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 20),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    children: <Widget>[
                      DropdownButtonFormField<int?>(
                        initialValue: _selectedYear,
                        decoration: const InputDecoration(
                          labelText: 'Year',
                          prefixIcon: Icon(Icons.calendar_view_week_outlined),
                        ),
                        items: <DropdownMenuItem<int?>>[
                          const DropdownMenuItem<int?>(
                            value: null,
                            child: Text('All Years'),
                          ),
                          ...attendanceProvider.availableYears().map(
                            (int year) => DropdownMenuItem<int?>(
                              value: year,
                              child: Text('Year $year'),
                            ),
                          ),
                        ],
                        onChanged: (int? value) {
                          final List<TeacherClassModel> availableClasses =
                              attendanceProvider.classesForYear(year: value);
                          setState(() {
                            _selectedYear = value;
                            if (!availableClasses.any(
                              (TeacherClassModel item) =>
                                  item.id == _selectedClassId,
                            )) {
                              _selectedClassId = null;
                            }
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
                        items: <DropdownMenuItem<String?>>[
                          const DropdownMenuItem<String?>(
                            value: null,
                            child: Text('All Classes'),
                          ),
                          ...classOptions.map(
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
                        icon: Icons.filter_alt_outlined,
                        isLoading: attendanceProvider.isLoading,
                        onPressed: _loadStudents,
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 18),
              if (attendanceProvider.students.isEmpty &&
                  !attendanceProvider.isLoading)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Text(
                      'No students found for the current filter.',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ),
                ),
              ...attendanceProvider.students.map(
                (student) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: StudentCard(student: student),
                ),
              ),
            ],
          ),
        ),
      ),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.fromLTRB(20, 10, 20, 20),
        child: CustomButton(
          label: 'Proceed to Mark Attendance',
          icon: Icons.arrow_forward_rounded,
          onPressed: attendanceProvider.students.isEmpty
              ? null
              : () {
                  Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => MarkAttendanceScreen(
                        initialYear: _selectedYear,
                        initialClassId: _selectedClassId,
                      ),
                    ),
                  );
                },
        ),
      ),
    );
  }
}
