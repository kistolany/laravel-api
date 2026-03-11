import 'attendance_model.dart';

class AttendanceSessionModel {
  const AttendanceSessionModel({
    required this.id,
    required this.classId,
    required this.className,
    required this.subjectId,
    required this.subjectName,
    required this.sessionDate,
    required this.sessionNumber,
    required this.records,
    this.studentCountValue,
    this.presentCountValue,
    this.absentCountValue,
    this.lateCountValue,
    this.excusedCountValue,
  });

  final String id;
  final String classId;
  final String className;
  final String subjectId;
  final String subjectName;
  final DateTime sessionDate;
  final int sessionNumber;
  final List<AttendanceModel> records;
  final int? studentCountValue;
  final int? presentCountValue;
  final int? absentCountValue;
  final int? lateCountValue;
  final int? excusedCountValue;

  String get sessionLabel => 'Session $sessionNumber';
  int get studentCount =>
      records.isNotEmpty ? records.length : studentCountValue ?? 0;

  int get presentCount => records.isNotEmpty
      ? records
            .where(
              (AttendanceModel record) =>
                  record.status == AttendanceStatus.present,
            )
            .length
      : presentCountValue ?? 0;
  int get absentCount => records.isNotEmpty
      ? records
            .where(
              (AttendanceModel record) =>
                  record.status == AttendanceStatus.absent,
            )
            .length
      : absentCountValue ?? 0;
  int get lateCount => records.isNotEmpty
      ? records
            .where(
              (AttendanceModel record) =>
                  record.status == AttendanceStatus.late,
            )
            .length
      : lateCountValue ?? 0;
  int get excusedCount => records.isNotEmpty
      ? records
            .where(
              (AttendanceModel record) =>
                  record.status == AttendanceStatus.excused,
            )
            .length
      : excusedCountValue ?? 0;

  AttendanceSessionModel copyWith({
    String? id,
    String? classId,
    String? className,
    String? subjectId,
    String? subjectName,
    DateTime? sessionDate,
    int? sessionNumber,
    List<AttendanceModel>? records,
    int? studentCountValue,
    int? presentCountValue,
    int? absentCountValue,
    int? lateCountValue,
    int? excusedCountValue,
  }) {
    return AttendanceSessionModel(
      id: id ?? this.id,
      classId: classId ?? this.classId,
      className: className ?? this.className,
      subjectId: subjectId ?? this.subjectId,
      subjectName: subjectName ?? this.subjectName,
      sessionDate: sessionDate ?? this.sessionDate,
      sessionNumber: sessionNumber ?? this.sessionNumber,
      records: records ?? this.records,
      studentCountValue: studentCountValue ?? this.studentCountValue,
      presentCountValue: presentCountValue ?? this.presentCountValue,
      absentCountValue: absentCountValue ?? this.absentCountValue,
      lateCountValue: lateCountValue ?? this.lateCountValue,
      excusedCountValue: excusedCountValue ?? this.excusedCountValue,
    );
  }

  factory AttendanceSessionModel.fromMap(Map<String, dynamic> map) {
    final Map<String, dynamic>? classMap =
        map['class'] as Map<String, dynamic>?;
    final Map<String, dynamic>? subject =
        map['subject'] as Map<String, dynamic>?;
    final List<dynamic> rawRecords =
        map['records'] as List<dynamic>? ??
        map['attendance_records'] as List<dynamic>? ??
        <dynamic>[];

    final String sessionId = _stringValue(map['id']);
    final int sessionNumber = _intValue(map['session_number']) ?? 1;
    final DateTime sessionDate =
        DateTime.tryParse(
          map['session_date'] as String? ?? map['date'] as String? ?? '',
        ) ??
        DateTime.now();
    final String classId = _stringValue(map['class_id'] ?? classMap?['id']);
    final String className =
        map['class_name'] as String? ??
        classMap?['name'] as String? ??
        classMap?['class_name'] as String? ??
        classMap?['code'] as String? ??
        '';
    final String subjectId = _stringValue(map['subject_id'] ?? subject?['id']);
    final String subjectName =
        map['subject_name'] as String? ??
        subject?['name'] as String? ??
        subject?['name_en'] as String? ??
        subject?['name_eg'] as String? ??
        '';

    final List<AttendanceModel> records = rawRecords
        .whereType<Map<String, dynamic>>()
        .map(
          (Map<String, dynamic> item) => AttendanceModel.fromMap(item).copyWith(
            attendanceSessionId: sessionId,
            classId: classId,
            className: className,
            subjectId: subjectId,
            subjectName: subjectName,
            date: sessionDate,
            sessionNumber: sessionNumber,
            session: 'Session $sessionNumber',
          ),
        )
        .toList(growable: false);

    return AttendanceSessionModel(
      id: sessionId,
      classId: classId,
      className: className,
      subjectId: subjectId,
      subjectName: subjectName,
      sessionDate: sessionDate,
      sessionNumber: sessionNumber,
      records: records,
      studentCountValue:
          _intValue(map['student_count']) ??
          _intValue(map['total_students']) ??
          _intValue(map['records_count']),
      presentCountValue:
          _intValue(map['present_count']) ?? _intValue(map['present']),
      absentCountValue:
          _intValue(map['absent_count']) ?? _intValue(map['absent']),
      lateCountValue: _intValue(map['late_count']) ?? _intValue(map['late']),
      excusedCountValue:
          _intValue(map['excused_count']) ?? _intValue(map['excused']),
    );
  }

  static String _stringValue(dynamic value) {
    if (value == null) {
      return '';
    }
    return value.toString();
  }

  static int? _intValue(dynamic value) {
    if (value == null) {
      return null;
    }
    if (value is int) {
      return value;
    }
    return int.tryParse(value.toString());
  }
}
