import 'package:flutter_test/flutter_test.dart';
import 'package:sarona_teacher_module/config/api_service.dart';
import 'package:sarona_teacher_module/services/auth_service.dart';

void main() {
  group('AuthService lookup parsing', () {
    test('fetchMajors reads majors from a root-level majors collection', () async {
      final AuthService authService = AuthService(
        apiService: _FakeApiService(<String, dynamic>{
          '/v1/lookups/majors': <String, dynamic>{
            'majors': <Map<String, dynamic>>[
              <String, dynamic>{'major_id': 1, 'major_name': 'Computer Science'},
            ],
          },
        }),
      );

      final List<Map<String, String>> majors = await authService.fetchMajors();

      expect(majors, hasLength(1));
      expect(majors.first, <String, String>{'id': '1', 'name': 'Computer Science'});
    });

    test('fetchSubjectsForMajor reads subjects nested under data.subjects', () async {
      final AuthService authService = AuthService(
        apiService: _FakeApiService(<String, dynamic>{
          '/v1/lookups/subjects': <String, dynamic>{
            'data': <String, dynamic>{
              'subjects': <Map<String, dynamic>>[
                <String, dynamic>{'subject_id': 10, 'subject_name': 'Algorithms'},
              ],
            },
          },
        }),
      );

      final List<Map<String, String>> subjects = await authService.fetchSubjectsForMajor('1');

      expect(subjects, hasLength(1));
      expect(
        subjects.first,
        <String, String>{'id': '10', 'name': 'Algorithms', 'majorId': '1'},
      );
    });
  });
}

class _FakeApiService extends ApiService {
  _FakeApiService(this._responses);

  final Map<String, dynamic> _responses;

  @override
  Future<Map<String, dynamic>> getJson(
    String endpoint, {
    Map<String, String>? headers,
    Map<String, String>? queryParameters,
  }) async {
    final dynamic response = _responses[endpoint];
    if (response is Map<String, dynamic>) {
      return response;
    }
    throw Exception('Missing fake response for $endpoint');
  }
}
