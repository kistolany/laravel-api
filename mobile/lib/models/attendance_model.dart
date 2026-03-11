enum AttendanceStatus { present, absent, late, excused }

extension AttendanceStatusX on AttendanceStatus {
  String get label {
    switch (this) {
      case AttendanceStatus.present:
        return 'Present';
      case AttendanceStatus.absent:
        return 'Absent';
      case AttendanceStatus.late:
        return 'Late';
      case AttendanceStatus.excused:
        return 'Excused';
    }
  }

  String get shortLabel {
    switch (this) {
      case AttendanceStatus.present:
        return 'P';
      case AttendanceStatus.absent:
        return 'A';
      case AttendanceStatus.late:
        return 'L';
      case AttendanceStatus.excused:
        return 'E';
    }
  }

  static AttendanceStatus fromValue(String value) {
    final String normalized = value.trim().toLowerCase();
    switch (normalized) {
      case 'present':
      case 'p':
        return AttendanceStatus.present;
      case 'absent':
      case 'a':
        return AttendanceStatus.absent;
      case 'late':
      case 'l':
        return AttendanceStatus.late;
      case 'excused':
      case 'e':
        return AttendanceStatus.excused;
      default:
        return AttendanceStatus.present;
    }
  }
}

class AttendanceModel {
  const AttendanceModel({
    required this.id,
    required this.attendanceSessionId,
    required this.studentId,
    required this.studentCode,
    required this.studentName,
    required this.classId,
    required this.className,
    required this.majorId,
    required this.year,
    required this.sessionNumber,
    required this.session,
    required this.subjectId,
    required this.subjectName,
    required this.date,
    required this.status,
    this.studentPhotoUrl,
  });

  final String id;
  final String attendanceSessionId;
  final String studentId;
  final String studentCode;
  final String studentName;
  final String classId;
  final String className;
  final String majorId;
  final int year;
  final int sessionNumber;
  final String session;
  final String subjectId;
  final String subjectName;
  final DateTime date;
  final AttendanceStatus status;
  final String? studentPhotoUrl;

  AttendanceModel copyWith({
    String? id,
    String? attendanceSessionId,
    String? studentId,
    String? studentCode,
    String? studentName,
    String? classId,
    String? className,
    String? majorId,
    int? year,
    int? sessionNumber,
    String? session,
    String? subjectId,
    String? subjectName,
    DateTime? date,
    AttendanceStatus? status,
    String? studentPhotoUrl,
  }) {
    return AttendanceModel(
      id: id ?? this.id,
      attendanceSessionId: attendanceSessionId ?? this.attendanceSessionId,
      studentId: studentId ?? this.studentId,
      studentCode: studentCode ?? this.studentCode,
      studentName: studentName ?? this.studentName,
      classId: classId ?? this.classId,
      className: className ?? this.className,
      majorId: majorId ?? this.majorId,
      year: year ?? this.year,
      sessionNumber: sessionNumber ?? this.sessionNumber,
      session: session ?? this.session,
      subjectId: subjectId ?? this.subjectId,
      subjectName: subjectName ?? this.subjectName,
      date: date ?? this.date,
      status: status ?? this.status,
      studentPhotoUrl: studentPhotoUrl ?? this.studentPhotoUrl,
    );
  }

  Map<String, dynamic> toMap() {
    return <String, dynamic>{
      'id': id,
      'attendance_session_id': attendanceSessionId,
      'student_id': studentId,
      'student_code': studentCode,
      'student_name': studentName,
      'class_id': classId,
      'class_name': className,
      'major_id': majorId,
      'year': year,
      'session_number': sessionNumber,
      'session': session,
      'subject_id': subjectId,
      'subject_name': subjectName,
      'date': date.toIso8601String(),
      'status': status.name,
      'student_photo_url': studentPhotoUrl,
    };
  }

  factory AttendanceModel.fromMap(Map<String, dynamic> map) {
    final Map<String, dynamic>? student =
        map['student'] as Map<String, dynamic>?;
    final Map<String, dynamic>? classMap =
        map['class'] as Map<String, dynamic>?;
    final Map<String, dynamic>? subject =
        map['subject'] as Map<String, dynamic>?;
    return AttendanceModel(
      id: _stringValue(map['id']),
      attendanceSessionId: _stringValue(
        map['attendance_session_id'] ?? map['session_id'],
      ),
      studentId: _stringValue(map['student_id'] ?? student?['id']),
      studentCode:
          map['student_code'] as String? ??
          student?['student_code'] as String? ??
          student?['code'] as String? ??
          '',
      studentName:
          map['student_name'] as String? ?? student?['name'] as String? ?? '',
      classId: _stringValue(map['class_id'] ?? classMap?['id']),
      className:
          map['class_name'] as String? ??
          classMap?['name'] as String? ??
          classMap?['class_name'] as String? ??
          '',
      majorId: _stringValue(map['major_id']),
      year: _intValue(map['year'] ?? classMap?['year_level']) ?? 1,
      sessionNumber: _intValue(map['session_number']) ?? 1,
      session:
          map['session'] as String? ??
          'Session ${_intValue(map['session_number']) ?? 1}',
      subjectId: _stringValue(map['subject_id'] ?? subject?['id']),
      subjectName:
          map['subject_name'] as String? ?? subject?['name'] as String? ?? '',
      date: DateTime.tryParse(map['date'] as String? ?? '') ?? DateTime.now(),
      status: AttendanceStatusX.fromValue(map['status'] as String? ?? ''),
      studentPhotoUrl:
          map['student_photo_url'] as String? ??
          student?['image_url'] as String? ??
          student?['image'] as String?,
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
