import '../config/api_service.dart';
import '../models/teacher_model.dart';

class AuthRegistrationResult {
  const AuthRegistrationResult({
    required this.email,
    required this.expiresAt,
    this.teacher,
  });

  final String email;
  final DateTime expiresAt;
  final TeacherModel? teacher;
}

class AuthSessionResult {
  const AuthSessionResult({
    required this.accessToken,
    required this.refreshToken,
    required this.teacher,
  });

  final String accessToken;
  final String refreshToken;
  final TeacherModel teacher;
}

class AuthService {
  AuthService({ApiService? apiService})
    : _apiService = apiService ?? ApiService();

  final ApiService _apiService;
  static const List<String> _registerEndpoints = <String>[
    '/teacher/register',
    '/v1/teacher-auth/register',
  ];
  static const List<String> _verifyOtpEndpoints = <String>[
    '/teacher/verify',
    '/v1/teacher-auth/verify-otp',
  ];
  static const List<String> _resendOtpEndpoints = <String>[
    '/teacher/resend-otp',
    '/v1/teacher-auth/resend-otp',
  ];

  static const List<Map<String, String>> _fallbackMajors =
      <Map<String, String>>[
        {'id': '1', 'name': 'Computer Science'},
        {'id': '2', 'name': 'Business Administration'},
        {'id': '3', 'name': 'English Education'},
      ];

  static const List<Map<String, String>> _fallbackSubjects =
      <Map<String, String>>[
        {'id': '1', 'name': 'Introduction to Computing', 'majorId': '1'},
        {'id': '2', 'name': 'Programming Fundamentals', 'majorId': '1'},
        {'id': '3', 'name': 'Database Systems', 'majorId': '1'},
        {'id': '4', 'name': 'Financial Accounting', 'majorId': '2'},
        {'id': '5', 'name': 'Marketing Strategy', 'majorId': '2'},
        {'id': '6', 'name': 'English Literature', 'majorId': '3'},
      ];

  List<Map<String, String>> get fallbackMajors =>
      List<Map<String, String>>.unmodifiable(_fallbackMajors);

  List<Map<String, String>> fallbackSubjectsForMajor(String majorId) {
    return _fallbackSubjects
        .where((Map<String, String> subject) => subject['majorId'] == majorId)
        .toList(growable: false);
  }

  Future<List<Map<String, String>>> fetchMajors() async {
    try {
      final Map<String, dynamic> response = await _apiService.getJson(
        '/v1/lookups/majors',
      );
      final List<dynamic> items = _collectionFrom(
        response,
        keys: <String>['majors'],
      );
      final List<Map<String, String>> majors = items
          .whereType<Map<String, dynamic>>()
          .map((Map<String, dynamic> item) {
            final Map<String, dynamic>? major =
                item['major'] as Map<String, dynamic>?;
            return <String, String>{
              'id': _coalesceString(<dynamic>[
                item['id'],
                item['major_id'],
                item['majorId'],
                major?['id'],
                major?['major_id'],
              ]),
              'name': _coalesceString(
                <dynamic>[
                  item['name'],
                  item['label'],
                  item['title'],
                  item['name_en'],
                  item['name_eg'],
                  item['major_name'],
                  item['majorName'],
                  major?['name'],
                  major?['label'],
                  major?['title'],
                  major?['name_en'],
                  major?['name_eg'],
                  major?['major_name'],
                ],
                fallback: 'Major',
              ),
            };
          })
          .where((Map<String, String> item) => item['id']!.isNotEmpty)
          .toList(growable: false);
      return majors;
    } catch (_) {
      return <Map<String, String>>[];
    }
  }

  Future<List<Map<String, String>>> fetchSubjectsForMajor(
    String majorId,
  ) async {
    try {
      final Map<String, dynamic> response = await _apiService.getJson(
        '/v1/lookups/subjects',
        queryParameters: <String, String>{'major_id': majorId},
      );
      final List<Map<String, String>> subjects = _parseSubjectItems(
        response,
        majorId,
      );
      if (subjects.isNotEmpty) {
        return subjects;
      }
    } catch (_) {}

    try {
      final Map<String, dynamic> response = await _apiService.getJson(
        '/v1/major-subjects/major/$majorId',
      );
      final List<Map<String, String>> subjects = _parseSubjectItems(
        response,
        majorId,
      );
      if (subjects.isNotEmpty) {
        return subjects;
      }
    } catch (_) {}

    return <Map<String, String>>[];
  }

  List<Map<String, String>> _parseSubjectItems(dynamic data, String majorId) {
    final List<dynamic> items = _collectionFrom(
      data,
      keys: <String>['subjects'],
    );
    return items
        .whereType<Map<String, dynamic>>()
        .map((Map<String, dynamic> item) {
          final Map<String, dynamic>? subject =
              item['subject'] as Map<String, dynamic>?;
          return <String, String>{
            'id': _coalesceString(<dynamic>[
              item['subject_id'],
              item['subjectId'],
              item['id'],
              subject?['id'],
              subject?['subject_id'],
            ]),
            'name': _coalesceString(
              <dynamic>[
                item['subject_name'],
                item['subjectName'],
                item['name'],
                item['label'],
                item['title'],
                item['name_en'],
                item['name_eg'],
                subject?['name'],
                subject?['label'],
                subject?['title'],
                subject?['name_en'],
                subject?['name_eg'],
                subject?['subject_name'],
              ],
              fallback: 'Subject',
            ),
            'majorId': _coalesceString(
              <dynamic>[
                item['major_id'],
                item['majorId'],
                subject?['major_id'],
                subject?['majorId'],
                majorId,
              ],
              fallback: majorId,
            ),
          };
        })
        .where((Map<String, String> item) => item['id']!.isNotEmpty)
        .toList(growable: false);
  }

  Future<AuthRegistrationResult> registerTeacher(
    TeacherModel teacher,
    String password,
  ) async {
    final Map<String, String> fields = <String, String>{
      'first_name': teacher.firstName,
      'last_name': teacher.lastName,
      'gender': teacher.gender,
      'major_id': teacher.majorId,
      'subject_id': teacher.subjectId,
      'email': teacher.email,
      'username': teacher.username,
      'password': password,
      'phone_number': teacher.phoneNumber,
      'telegram': teacher.telegram,
      'address': teacher.address,
    };
    final Map<String, dynamic> response = await _postMultipartWithFallback(
      _registerEndpoints,
      fields: fields,
      fileField: 'image',
      filePath: teacher.imagePath,
    );

    final Map<String, dynamic>? data =
        response['data'] as Map<String, dynamic>?;
    final TeacherModel? registeredTeacher = _teacherFrom(data?['teacher']);
    return AuthRegistrationResult(
      email: teacher.email.trim(),
      teacher: registeredTeacher,
      expiresAt: DateTime.now().add(const Duration(minutes: 5)),
    );
  }

  Future<TeacherModel> verifyOtp(String email, String otp) async {
    final Map<String, dynamic> response = await _postJsonWithFallback(
      <_AuthRequestVariant>[
        _AuthRequestVariant(
          endpoint: _verifyOtpEndpoints.first,
          body: <String, dynamic>{'email': email.trim(), 'otp': otp.trim()},
        ),
        _AuthRequestVariant(
          endpoint: _verifyOtpEndpoints.last,
          body: <String, dynamic>{
            'email': email.trim(),
            'otp_code': otp.trim(),
          },
        ),
      ],
    );

    final Map<String, dynamic>? data =
        response['data'] as Map<String, dynamic>?;
    final TeacherModel? teacher = _teacherFrom(data?['teacher']);
    if (teacher == null) {
      throw Exception('OTP verified but teacher payload was missing.');
    }
    return teacher;
  }

  Future<AuthRegistrationResult> resendOtp(String email) async {
    await _postJsonWithFallback(
      _resendOtpEndpoints
          .map(
            (String endpoint) => _AuthRequestVariant(
              endpoint: endpoint,
              body: <String, dynamic>{'email': email.trim()},
            ),
          )
          .toList(growable: false),
    );

    return AuthRegistrationResult(
      email: email.trim(),
      expiresAt: DateTime.now().add(const Duration(minutes: 5)),
    );
  }

  Future<AuthSessionResult> login(String identifier, String password) async {
    final Map<String, dynamic> response = await _apiService.postJson(
      '/v1/teacher-auth/login',
      body: <String, dynamic>{'login': identifier.trim(), 'password': password},
    );
    return _sessionFromResponse(response);
  }

  Future<TeacherModel> fetchMe(String accessToken) async {
    final Map<String, dynamic> response = await _apiService.getJson(
      '/v1/teacher-auth/me',
      headers: _apiService.authHeaders(accessToken),
    );
    final TeacherModel? teacher = _teacherFrom(response['data']);
    if (teacher == null) {
      throw Exception('Teacher profile response was empty.');
    }
    return teacher;
  }

  Future<AuthSessionResult> refreshSession(String refreshToken) async {
    final Map<String, dynamic> response = await _apiService.postJson(
      '/v1/teacher-auth/refresh',
      body: <String, dynamic>{'refresh_token': refreshToken},
    );
    return _sessionFromResponse(response);
  }

  Future<void> logout({
    required String accessToken,
    required String refreshToken,
  }) async {
    await _apiService.postJson(
      '/v1/teacher-auth/logout',
      headers: _apiService.authHeaders(accessToken),
      body: <String, dynamic>{'refresh_token': refreshToken},
    );
  }

  Future<TeacherModel> updateTeacherProfileLocal(TeacherModel teacher) async {
    return teacher;
  }

  AuthSessionResult _sessionFromResponse(Map<String, dynamic> response) {
    final Map<String, dynamic>? data =
        response['data'] as Map<String, dynamic>?;
    final String accessToken = _stringValue(data?['access_token']);
    final String refreshToken = _stringValue(data?['refresh_token']);
    final TeacherModel? teacher = _teacherFrom(data?['teacher']);

    if (accessToken.isEmpty || refreshToken.isEmpty || teacher == null) {
      throw Exception('Login response is missing tokens or teacher data.');
    }

    return AuthSessionResult(
      accessToken: accessToken,
      refreshToken: refreshToken,
      teacher: teacher,
    );
  }

  TeacherModel? _teacherFrom(dynamic raw) {
    if (raw is Map<String, dynamic>) {
      return TeacherModel.fromMap(raw);
    }
    return null;
  }

  List<dynamic> _collectionFrom(
    dynamic data, {
    List<String> keys = const <String>[],
  }) {
    if (data is List<dynamic>) {
      return data;
    }
    if (data is! Map<String, dynamic>) {
      return <dynamic>[];
    }

    final List<String> candidateKeys = <String>[
      ...keys,
      'data',
      'items',
      'results',
    ];
    for (final String key in candidateKeys) {
      final dynamic nested = data[key];
      if (nested is List<dynamic>) {
        return nested;
      }
      final List<dynamic> nestedItems = _collectionFrom(nested, keys: keys);
      if (nestedItems.isNotEmpty) {
        return nestedItems;
      }
    }
    return <dynamic>[];
  }

  static String _stringValue(dynamic value) {
    if (value == null) {
      return '';
    }
    return value.toString();
  }

  static String _coalesceString(
    List<dynamic> values, {
    String fallback = '',
  }) {
    for (final dynamic value in values) {
      final String text = _stringValue(value).trim();
      if (text.isNotEmpty) {
        return text;
      }
    }
    return fallback;
  }

  Future<Map<String, dynamic>> _postMultipartWithFallback(
    List<String> endpoints, {
    required Map<String, String> fields,
    String? fileField,
    String? filePath,
  }) async {
    Object? lastError;
    for (final String endpoint in endpoints) {
      try {
        return await _apiService.postMultipart(
          endpoint,
          fields: fields,
          fileField: fileField,
          filePath: filePath,
        );
      } catch (error) {
        lastError = error;
      }
    }
    throw lastError ?? Exception('Unable to complete the request.');
  }

  Future<Map<String, dynamic>> _postJsonWithFallback(
    List<_AuthRequestVariant> variants,
  ) async {
    Object? lastError;
    for (final _AuthRequestVariant variant in variants) {
      try {
        return await _apiService.postJson(variant.endpoint, body: variant.body);
      } catch (error) {
        lastError = error;
      }
    }
    throw lastError ?? Exception('Unable to complete the request.');
  }
}

class _AuthRequestVariant {
  const _AuthRequestVariant({required this.endpoint, required this.body});

  final String endpoint;
  final Map<String, dynamic> body;
}

