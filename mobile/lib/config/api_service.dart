import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

class ApiService {
  ApiService({http.Client? client}) : _client = client ?? http.Client();

  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://localhost/api',
  );

  final http.Client _client;

  static Uri get _baseUri {
    final Uri parsedBaseUri = Uri.parse(baseUrl);
    if (kIsWeb) {
      return parsedBaseUri;
    }

    final String host = parsedBaseUri.host.toLowerCase();
    final bool isAndroidLocalhost =
        defaultTargetPlatform == TargetPlatform.android &&
        (host == 'localhost' || host == '127.0.0.1');
    if (!isAndroidLocalhost) {
      return parsedBaseUri;
    }

    return parsedBaseUri.replace(host: '10.0.2.2');
  }

  static String resolveUrl(String path) {
    if (path.isEmpty ||
        path.startsWith('http://') ||
        path.startsWith('https://')) {
      return path;
    }

    final String effectiveBaseUrl = _baseUri.toString();
    final String normalizedBase = effectiveBaseUrl.endsWith('/')
        ? effectiveBaseUrl.substring(0, effectiveBaseUrl.length - 1)
        : effectiveBaseUrl;
    final String normalizedPath = path.startsWith('/') ? path : '/$path';
    return '$normalizedBase$normalizedPath';
  }

  static String resolveAssetUrl(String path) {
    if (path.isEmpty ||
        path.startsWith('http://') ||
        path.startsWith('https://')) {
      return path;
    }

    final Uri originUri = _baseUri.replace(path: '', query: null, fragment: null);
    final String normalizedPath = path.startsWith('/') ? path : '/$path';
    return originUri.replace(path: normalizedPath).toString();
  }

  Future<Map<String, dynamic>> getJson(
    String endpoint, {
    Map<String, String>? headers,
    Map<String, String>? queryParameters,
  }) async {
    final http.Response response = await _client.get(
      _buildUri(endpoint, queryParameters),
      headers: _headers(headers),
    );
    return _decode(response);
  }

  Future<Map<String, dynamic>> postJson(
    String endpoint, {
    Map<String, dynamic>? body,
    Map<String, String>? headers,
  }) async {
    final http.Response response = await _client.post(
      _buildUri(endpoint),
      headers: _headers(<String, String>{
        'Content-Type': 'application/json',
        ...?headers,
      }),
      body: jsonEncode(body ?? <String, dynamic>{}),
    );
    return _decode(response);
  }

  Future<Map<String, dynamic>> putJson(
    String endpoint, {
    Map<String, dynamic>? body,
    Map<String, String>? headers,
  }) async {
    final http.Response response = await _client.put(
      _buildUri(endpoint),
      headers: _headers(<String, String>{
        'Content-Type': 'application/json',
        ...?headers,
      }),
      body: jsonEncode(body ?? <String, dynamic>{}),
    );
    return _decode(response);
  }

  Future<Map<String, dynamic>> postMultipart(
    String endpoint, {
    required Map<String, String> fields,
    Map<String, String>? headers,
    String? fileField,
    String? filePath,
  }) async {
    final http.MultipartRequest request = http.MultipartRequest(
      'POST',
      _buildUri(endpoint),
    );
    request.headers.addAll(_headers(headers));
    request.fields.addAll(fields);

    if (fileField != null &&
        fileField.isNotEmpty &&
        filePath != null &&
        filePath.isNotEmpty) {
      request.files.add(await http.MultipartFile.fromPath(fileField, filePath));
    }

    final http.StreamedResponse streamedResponse = await request.send();
    final http.Response response = await http.Response.fromStream(
      streamedResponse,
    );
    return _decode(response);
  }

  Map<String, String> authHeaders(String accessToken) {
    return _headers(<String, String>{'Authorization': 'Bearer $accessToken'});
  }

  Map<String, dynamic> _decode(http.Response response) {
    final dynamic data = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body);
    if (response.statusCode >= 200 && response.statusCode < 300) {
      return data is Map<String, dynamic>
          ? data
          : <String, dynamic>{'data': data};
    }

    if (data is Map<String, dynamic>) {
      final dynamic message = data['message'] ?? data['error'];
      if (message is String && message.isNotEmpty) {
        throw Exception(message);
      }
      final dynamic errors = data['errors'];
      if (errors is Map<String, dynamic> && errors.isNotEmpty) {
        final String firstError = errors.values
            .expand<dynamic>(
              (dynamic value) => value is List ? value : <dynamic>[value],
            )
            .map((dynamic value) => value.toString())
            .first;
        throw Exception(firstError);
      }
    }

    throw Exception('Request failed with status ${response.statusCode}.');
  }

  Uri _buildUri(String endpoint, [Map<String, String>? queryParameters]) {
    final Uri uri = Uri.parse(resolveUrl(endpoint));
    if (queryParameters == null || queryParameters.isEmpty) {
      return uri;
    }
    return uri.replace(
      queryParameters: <String, String>{
        ...uri.queryParameters,
        ...queryParameters,
      },
    );
  }

  Map<String, String> _headers(Map<String, String>? headers) {
    return <String, String>{'Accept': 'application/json', ...?headers};
  }
}
