import 'package:flutter/foundation.dart';

import '../models/attendance_model.dart';
import '../models/attendance_session_model.dart';
import '../models/student_model.dart';
import '../models/teacher_model.dart';
import '../models/teacher_class_model.dart';
import '../services/attendance_service.dart';

class AttendanceProvider extends ChangeNotifier {
  AttendanceProvider({AttendanceService? attendanceService})
    : _attendanceService = attendanceService ?? AttendanceService();

  final AttendanceService _attendanceService;

  String? _accessToken;
  TeacherModel? _teacher;
  bool _isLoading = false;
  String? _errorMessage;
  AttendanceOptionsResult? _attendanceOptions;
  List<TeacherClassModel> _teacherClasses = <TeacherClassModel>[];
  List<StudentModel> _students = <StudentModel>[];
  List<AttendanceModel> _draftRecords = <AttendanceModel>[];
  List<AttendanceModel> _editableRecords = <AttendanceModel>[];
  List<AttendanceSessionModel> _historySessions = <AttendanceSessionModel>[];
  AttendanceSessionModel? _editableSession;

  String? _draftClassId;
  String? _draftSubjectId;
  DateTime? _draftDate;
  int? _draftSessionNumber;

  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  AttendanceOptionsResult? get attendanceOptions => _attendanceOptions;
  TeacherModel? get teacher => _teacher;
  List<TeacherClassModel> get teacherClasses =>
      List<TeacherClassModel>.unmodifiable(_teacherClasses);
  List<TeacherClassModel> get attendanceClasses =>
      List<TeacherClassModel>.unmodifiable(
        _attendanceOptions?.classes ?? const <TeacherClassModel>[],
      );
  List<StudentModel> get students => List<StudentModel>.unmodifiable(_students);
  List<AttendanceModel> get draftRecords =>
      List<AttendanceModel>.unmodifiable(_draftRecords);
  List<AttendanceModel> get editableRecords =>
      List<AttendanceModel>.unmodifiable(_editableRecords);
  List<AttendanceSessionModel> get historySessions =>
      List<AttendanceSessionModel>.unmodifiable(_historySessions);
  AttendanceSessionModel? get editableSession => _editableSession;
  List<int> get years => AttendanceService.years;
  List<int> get sessionNumbers => AttendanceService.sessionNumbers;

  void bindSession({
    required String? accessToken,
    required TeacherModel? teacher,
  }) {
    final bool changed =
        accessToken != _accessToken || teacher?.id != _teacher?.id;
    if (!changed) {
      return;
    }

    _accessToken = accessToken;
    _teacher = teacher;
    _attendanceOptions = null;
    _teacherClasses = <TeacherClassModel>[];
    _students = <StudentModel>[];
    _draftRecords = <AttendanceModel>[];
    _editableRecords = <AttendanceModel>[];
    _historySessions = <AttendanceSessionModel>[];
    _editableSession = null;
    _draftClassId = null;
    _draftSubjectId = null;
    _draftDate = null;
    _draftSessionNumber = null;
    _errorMessage = null;
    notifyListeners();
  }

  List<int> availableYears({bool attendanceOnly = false}) {
    final Iterable<TeacherClassModel> source = attendanceOnly
        ? attendanceClasses
        : teacherClasses;
    final List<int> values =
        source
            .map((TeacherClassModel item) => item.yearLevel)
            .whereType<int>()
            .toSet()
            .toList(growable: false)
          ..sort();
    return values.isEmpty ? years : values;
  }

  List<TeacherClassModel> classesForYear({
    int? year,
    bool attendanceOnly = false,
  }) {
    final List<TeacherClassModel> source =
        (attendanceOnly ? attendanceClasses : teacherClasses).toList(
          growable: false,
        );
    if (year == null) {
      return source;
    }
    if (source.every((TeacherClassModel item) => item.yearLevel == null)) {
      return source;
    }
    return source
        .where((TeacherClassModel item) => item.yearLevel == year)
        .toList(growable: false);
  }

  Future<void> loadAttendanceOptions({bool forceRefresh = false}) async {
    if (!forceRefresh && _attendanceOptions != null) {
      return;
    }

    final String accessToken = _requireAccessToken();
    _setLoading(true);
    _errorMessage = null;
    try {
      _attendanceOptions = await _attendanceService.getAttendanceOptions(
        accessToken,
      );
    } catch (error) {
      _errorMessage = error.toString().replaceFirst('Exception: ', '');
    } finally {
      _setLoading(false);
    }
  }

  Future<void> loadTeacherClasses({bool forceRefresh = false}) async {
    if (!forceRefresh && _teacherClasses.isNotEmpty) {
      return;
    }

    final String accessToken = _requireAccessToken();
    _setLoading(true);
    _errorMessage = null;
    try {
      _teacherClasses = await _attendanceService.getTeacherClasses(accessToken);
    } catch (error) {
      _errorMessage = error.toString().replaceFirst('Exception: ', '');
    } finally {
      _setLoading(false);
    }
  }

  Future<void> loadStudents({String? classId}) async {
    final String accessToken = _requireAccessToken();
    _setLoading(true);
    _errorMessage = null;
    try {
      _students = await _attendanceService.getStudents(
        accessToken,
        classId: classId,
      );
    } catch (error) {
      _errorMessage = error.toString().replaceFirst('Exception: ', '');
    } finally {
      _setLoading(false);
    }
  }

  Future<void> prepareAttendanceDraft({
    required String classId,
    required DateTime date,
    required int sessionNumber,
  }) async {
    final String accessToken = _requireAccessToken();
    final TeacherModel teacher = _requireTeacher();
    final String subjectId = _attendanceOptions?.subjectId.isNotEmpty == true
        ? _attendanceOptions!.subjectId
        : teacher.subjectId;
    final String subjectName =
        _attendanceOptions?.subjectName.isNotEmpty == true
        ? _attendanceOptions!.subjectName
        : teacher.subjectName;

    _setLoading(true);
    _errorMessage = null;
    try {
      final List<StudentModel> loadedStudents = await _attendanceService
          .getStudents(accessToken, classId: classId);
      final TeacherClassModel? selectedClass = _findClassById(classId);
      _students = loadedStudents;
      _draftClassId = classId;
      _draftSubjectId = subjectId;
      _draftDate = date;
      _draftSessionNumber = sessionNumber;
      _draftRecords = loadedStudents
          .map(
            (StudentModel student) => AttendanceModel(
              id: 'ATT-${date.microsecondsSinceEpoch}-${student.id}',
              attendanceSessionId: '',
              studentId: student.id,
              studentCode: student.studentCode,
              studentName: student.name,
              classId: student.classId.isNotEmpty ? student.classId : classId,
              className: student.className.isNotEmpty
                  ? student.className
                  : selectedClass?.name ?? 'Class',
              majorId: teacher.majorId,
              year: student.year,
              sessionNumber: sessionNumber,
              session: 'Session $sessionNumber',
              subjectId: subjectId,
              subjectName: subjectName,
              date: date,
              status: AttendanceStatus.present,
              studentPhotoUrl: student.photoUrl,
            ),
          )
          .toList(growable: false);
    } catch (error) {
      _errorMessage = error.toString().replaceFirst('Exception: ', '');
    } finally {
      _setLoading(false);
    }
  }

  void updateDraftStatus(String studentId, AttendanceStatus status) {
    _draftRecords = _draftRecords
        .map(
          (AttendanceModel record) => record.studentId == studentId
              ? record.copyWith(status: status)
              : record,
        )
        .toList(growable: false);
    notifyListeners();
  }

  Future<bool> submitDraft() async {
    if (_draftRecords.isEmpty ||
        _draftClassId == null ||
        _draftSubjectId == null ||
        _draftDate == null ||
        _draftSessionNumber == null) {
      _errorMessage = 'Load a class student list before saving attendance.';
      notifyListeners();
      return false;
    }

    final String accessToken = _requireAccessToken();
    _setLoading(true);
    _errorMessage = null;
    try {
      final AttendanceSessionModel createdSession = await _attendanceService
          .createAttendanceSession(
            accessToken,
            classId: _draftClassId!,
            subjectId: _draftSubjectId!,
            sessionDate: _draftDate!,
            sessionNumber: _draftSessionNumber!,
          );
      await _attendanceService.recordAttendance(
        accessToken,
        sessionId: createdSession.id,
        subjectId: _draftSubjectId!,
        sessionDate: _draftDate!,
        records: _draftRecords,
      );
      final List<AttendanceModel> savedRecords = _draftRecords
          .map(
            (AttendanceModel record) =>
                record.copyWith(attendanceSessionId: createdSession.id),
          )
          .toList(growable: false);
      _draftRecords = savedRecords;
      _upsertHistorySession(
        createdSession.copyWith(
          records: savedRecords,
          className: savedRecords.isNotEmpty
              ? savedRecords.first.className
              : createdSession.className,
          subjectName: savedRecords.isNotEmpty
              ? savedRecords.first.subjectName
              : createdSession.subjectName,
        ),
      );
      return true;
    } catch (error) {
      _errorMessage = error.toString().replaceFirst('Exception: ', '');
      return false;
    } finally {
      _setLoading(false);
    }
  }

  Future<void> loadEditableAttendance(String sessionId) async {
    final String accessToken = _requireAccessToken();
    _setLoading(true);
    _errorMessage = null;
    try {
      _editableSession = await _attendanceService.getAttendanceSessionDetail(
        accessToken,
        sessionId,
      );
      _editableRecords = _editableSession!.records;
      _upsertHistorySession(_editableSession!);
    } catch (error) {
      _errorMessage = error.toString().replaceFirst('Exception: ', '');
    } finally {
      _setLoading(false);
    }
  }

  void updateEditableStatus(String studentId, AttendanceStatus status) {
    _editableRecords = _editableRecords
        .map(
          (AttendanceModel record) => record.studentId == studentId
              ? record.copyWith(status: status)
              : record,
        )
        .toList(growable: false);
    notifyListeners();
  }

  Future<bool> saveEditedAttendance() async {
    final AttendanceSessionModel? editableSession = _editableSession;
    if (editableSession == null || _editableRecords.isEmpty) {
      _errorMessage = 'Load an attendance session before saving updates.';
      notifyListeners();
      return false;
    }

    final String accessToken = _requireAccessToken();
    _setLoading(true);
    _errorMessage = null;
    try {
      await _attendanceService.recordAttendance(
        accessToken,
        sessionId: editableSession.id,
        subjectId: editableSession.subjectId,
        sessionDate: editableSession.sessionDate,
        records: _editableRecords,
      );
      _editableSession = editableSession.copyWith(records: _editableRecords);
      _upsertHistorySession(_editableSession!);
      return true;
    } catch (error) {
      _errorMessage = error.toString().replaceFirst('Exception: ', '');
      return false;
    } finally {
      _setLoading(false);
    }
  }

  Future<void> loadHistory({bool forceRefresh = true}) async {
    if (!forceRefresh && _historySessions.isNotEmpty) {
      return;
    }

    final String accessToken = _requireAccessToken();
    _setLoading(true);
    _errorMessage = null;
    try {
      _historySessions = await _attendanceService.getAttendanceHistory(
        accessToken,
      );
    } catch (error) {
      _errorMessage = error.toString().replaceFirst('Exception: ', '');
    } finally {
      _setLoading(false);
    }
  }

  List<AttendanceSessionModel> filteredHistorySessions({
    DateTime? date,
    String? classId,
    String? subjectId,
    int? sessionNumber,
  }) {
    Iterable<AttendanceSessionModel> filtered = _historySessions;
    if (date != null) {
      filtered = filtered.where(
        (AttendanceSessionModel session) => _sameDay(session.sessionDate, date),
      );
    }
    if (classId != null && classId.isNotEmpty) {
      filtered = filtered.where(
        (AttendanceSessionModel session) => session.classId == classId,
      );
    }
    if (subjectId != null && subjectId.isNotEmpty) {
      filtered = filtered.where(
        (AttendanceSessionModel session) => session.subjectId == subjectId,
      );
    }
    if (sessionNumber != null) {
      filtered = filtered.where(
        (AttendanceSessionModel session) =>
            session.sessionNumber == sessionNumber,
      );
    }
    final List<AttendanceSessionModel> sessions =
        filtered.toList(growable: false)
          ..sort((AttendanceSessionModel a, AttendanceSessionModel b) {
            final int dateCompare = b.sessionDate.compareTo(a.sessionDate);
            if (dateCompare != 0) {
              return dateCompare;
            }
            return b.sessionNumber.compareTo(a.sessionNumber);
          });
    return sessions;
  }

  AttendanceSessionModel? findHistorySession({
    required DateTime date,
    required String classId,
    required String subjectId,
    required int sessionNumber,
  }) {
    final List<AttendanceSessionModel> matches = filteredHistorySessions(
      date: date,
      classId: classId,
      subjectId: subjectId,
      sessionNumber: sessionNumber,
    );
    if (matches.isEmpty) {
      return null;
    }
    return matches.first;
  }

  void reset() {
    _accessToken = null;
    _teacher = null;
    _attendanceOptions = null;
    _teacherClasses = <TeacherClassModel>[];
    _students = <StudentModel>[];
    _draftRecords = <AttendanceModel>[];
    _editableRecords = <AttendanceModel>[];
    _historySessions = <AttendanceSessionModel>[];
    _editableSession = null;
    _draftClassId = null;
    _draftSubjectId = null;
    _draftDate = null;
    _draftSessionNumber = null;
    _errorMessage = null;
    notifyListeners();
  }

  String _requireAccessToken() {
    final String? accessToken = _accessToken;
    if (accessToken == null || accessToken.isEmpty) {
      throw Exception('Your session expired. Please log in again.');
    }
    return accessToken;
  }

  TeacherModel _requireTeacher() {
    final TeacherModel? teacher = _teacher;
    if (teacher == null) {
      throw Exception('Teacher session is not available.');
    }
    return teacher;
  }

  TeacherClassModel? _findClassById(String classId) {
    final Iterable<TeacherClassModel> source = <TeacherClassModel>[
      ...attendanceClasses,
      ...teacherClasses,
    ];
    for (final TeacherClassModel item in source) {
      if (item.id == classId) {
        return item;
      }
    }
    return null;
  }

  void _upsertHistorySession(AttendanceSessionModel session) {
    final List<AttendanceSessionModel> updated = <AttendanceSessionModel>[
      session,
      ..._historySessions.where(
        (AttendanceSessionModel item) => item.id != session.id,
      ),
    ];
    updated.sort((AttendanceSessionModel a, AttendanceSessionModel b) {
      final int dateCompare = b.sessionDate.compareTo(a.sessionDate);
      if (dateCompare != 0) {
        return dateCompare;
      }
      return b.sessionNumber.compareTo(a.sessionNumber);
    });
    _historySessions = updated;
  }

  bool _sameDay(DateTime a, DateTime b) {
    return a.year == b.year && a.month == b.month && a.day == b.day;
  }

  void _setLoading(bool value) {
    _isLoading = value;
    notifyListeners();
  }
}
