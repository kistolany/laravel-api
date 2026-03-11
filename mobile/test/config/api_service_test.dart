import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:sarona_teacher_module/config/api_service.dart';

void main() {
  tearDown(() {
    // ignore: deprecated_member_use
    debugDefaultTargetPlatformOverride = null;
  });

  test('rewrites localhost to the Android emulator host for lookup requests', () async {
    // ignore: deprecated_member_use
    debugDefaultTargetPlatformOverride = TargetPlatform.android;

    late Uri requestedUri;
    final ApiService apiService = ApiService(
      client: MockClient((http.Request request) async {
        requestedUri = request.url;
        return http.Response('{}', 200);
      }),
    );

    await apiService.getJson(
      '/v1/lookups/subjects',
      queryParameters: <String, String>{'major_id': '1'},
    );

    expect(
      requestedUri.toString(),
      'http://10.0.2.2/api/v1/lookups/subjects?major_id=1',
    );
  });

  test('keeps asset URLs rooted at the host origin when API base path includes /api', () {
    expect(
      ApiService.resolveAssetUrl('/storage/teachers/avatar.png'),
      'http://localhost/storage/teachers/avatar.png',
    );
  });
}
