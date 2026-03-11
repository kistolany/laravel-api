import '../config/api_service.dart';
import '../models/attendance_model.dart';
import '../models/attendance_session_model.dart';
import '../models/student_model.dart';
import '../models/teacher_class_model.dart';

class AttendanceOptionsResult {
  const AttendanceOptionsResult({
    required this.majorId,
    required this.majorName,
    required this.subjectId,
    required this.subjectName,
    required this.classes,
  });

  final String majorId;
  final String majorName;
  final String subjectId;
  final String subjectName;
  final List<TeacherClassModel> classes;
}

class AttendanceService {
  AttendanceService({ApiService? apiService})
    : _apiService = apiService ?? ApiService();

  final ApiService _apiService;

  static const List<int> years = <int>[1, 2, 3, 4];
  static const List<int> sessionNumbers = <int>[1, 2];

  Future<AttendanceOptionsResult> getAttendanceOptions(
    String accessToken,
  ) async {
    final Map<String, dynamic> response = await _apiService.getJson(
      '/v1/teacher/attendance/options',
      headers: _apiService.authHeaders(accessToken),
    );

    final Map<String, dynamic> data = _mapFrom(response['data']);
    final Map<String, dynamic> major = _mapFrom(data['major']);
    final Map<String, dynamic> subject = _mapFrom(data['subject']);
    final List<TeacherClassModel> classes = _listFrom(data['classes'])
        .whereType<Map<String, dynamic>>()
        .map(TeacherClassModel.fromMap)
        .toList(growable: false);

    return AttendanceOptionsResult(
      majorId: _stringValue(major['id']),
      majorName:
          major['name'] as String? ?? major['major_name'] as String? ?? '',
      subjectId: _stringValue(subject['id']),
      subjectName:
          subject['name'] as String? ??
          subject['subject_name'] as String? ??
          '',
      classes: classes,
    );
  }

  Future<List<TeacherClassModel>> getTeacherClasses(String accessToken) async {
    final Map<String, dynamic> response = await _apiService.getJson(
      '/v1/teacher/classes',
      headers: _apiService.authHeaders(accessToken),
      queryParameters: const <String, String>{'size': '100'},
    );

    return _listFrom(response['data'], fallbackKey: 'items')
        .whereType<Map<String, dynamic>>()
        .map(TeacherClassModel.fromMap)
        .toList(growable: false);
  }

  Future<List<StudentModel>> getStudents(
    String accessToken, {
    String? classId,
  }) async {
    final Map<String, dynamic> response = classId != null && classId.isNotEmpty
        ? await _apiService.getJson(
            '/v1/teacher/classes/$classId/students',
            headers: _apiService.authHeaders(accessToken),
          )
        : await _apiService.getJson(
            '/v1/teacher/students',
            headers: _apiService.authHeaders(accessToken),
            queryParameters: const <String, String>{'size': '100'},
          );

    return _listFrom(response['data'], fallbackKey: 'items')
        .whereType<Map<String, dynamic>>()
        .map(StudentModel.fromMap)
        .toList(growable: false);
  }

  Future<AttendanceSessionModel> createAttendanceSession(
    String accessToken, {
    required String classId,
    required String subjectId,
    required DateTime sessionDate,
    required int sessionNumber,
  }) async {
    final Map<String, dynamic> response = await _apiService.postJson(
      '/v1/teacher/attendance/sessions',
      headers: _apiService.authHeaders(accessToken),
      body: <String, dynamic>{
        'class_id': int.tryParse(classId) ?? classId,
        'subject_id': int.tryParse(subjectId) ?? subjectId,
        'session_date': _dateString(sessionDate),
        'session_number': sessionNumber,
      },
    );

    final Map<String, dynamic> data = _mapFrom(response['data']);
    final Map<String, dynamic> sessionData = _mapFrom(data['data']).isEmpty
        ? data
        : _mapFrom(data['data']);
    return AttendanceSessionModel.fromMap(sessionData);
  }

  Future<void> recordAttendance(
    String accessToken, {
    required String sessionId,
    required String subjectId,
    required DateTime sessionDate,
    required List<AttendanceModel> records,
  }) async {
    await _apiService.postJson(
      '/v1/teacher/attendance/sessions/$sessionId/records',
      headers: _apiService.authHeaders(accessToken),
      body: <String, dynamic>{
        'subject_id': int.tryParse(subjectId) ?? subjectId,
        'session_date': _dateString(sessionDate),
        'records': records
            .map(
              (AttendanceModel item) => <String, dynamic>{
                'student_id': int.tryParse(item.studentId) ?? item.studentId,
                'status': item.status.label,
              },
            )
            .toList(growable: false),
      },
    );
  }

  Future<List<AttendanceSessionModel>> getAttendanceHistory(
    String accessToken,
  ) async {
    final Map<String, dynamic> response = await _apiService.getJson(
      '/v1/teacher/attendance/history',
      headers: _apiService.authHeaders(accessToken),
      queryParameters: const <String, String>{'size': '100'},
    );

    return _listFrom(response['data'], fallbackKey: 'items')
        .whereType<Map<String, dynamic>>()
        .map(AttendanceSessionModel.fromMap)
        .toList(growable: false);
  }

  Future<AttendanceSessionModel> getAttendanceSessionDetail(
    String accessToken,
    String sessionId,
  ) async {
    final Map<String, dynamic> response = await _apiService.getJson(
      '/v1/teacher/attendance/history/$sessionId',
      headers: _apiService.authHeaders(accessToken),
    );
    final Map<String, dynamic> data = _mapFrom(response['data']);
    final Map<String, dynamic> sessionData = _mapFrom(data['data']).isEmpty
        ? data
        : _mapFrom(data['data']);
    return AttendanceSessionModel.fromMap(sessionData);
  }

  List<dynamic> _listFrom(dynamic data, {String? fallbackKey}) {
    if (data is List<dynamic>) {
      return data;
    }
    if (data is Map<String, dynamic> && fallbackKey != null) {
      final dynamic nested = data[fallbackKey];
      if (nested is List<dynamic>) {
        return nested;
      }
    }
    return <dynamic>[];
  }

  Map<String, dynamic> _mapFrom(dynamic data) {
    if (data is Map<String, dynamic>) {
      return data;
    }
    return <String, dynamic>{};
  }

  String _stringValue(dynamic value) {
    if (value == null) {
      return '';
    }
    return value.toString();
  }

  String _dateString(DateTime date) {
    final String month = date.month.toString().padLeft(2, '0');
    final String day = date.day.toString().padLeft(2, '0');
    return '${date.year}-$month-$day';
  }
}

