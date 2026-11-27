import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:http/http.dart' as http;

String apiBaseUrl() {
  const define = String.fromEnvironment('API_BASE_URL');
  if (define.isNotEmpty) {
    return _normalizeBaseUrl(define);
  }

  final fromEnv = dotenv.maybeGet('API_BASE_URL')?.trim() ?? '';
  if (fromEnv.isNotEmpty) {
    return _normalizeBaseUrl(fromEnv);
  }

  if (!kIsWeb && Platform.isAndroid) {
    return 'http://10.0.2.2:8000/api';
  }

  return 'http://127.0.0.1:8000/api';
}

String _normalizeBaseUrl(String value) {
  return value.endsWith('/') ? value.substring(0, value.length - 1) : value;
}

class ApiException implements Exception {
  ApiException(this.message);
  final String message;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({String? baseUrl}) : baseUrl = baseUrl ?? apiBaseUrl();

  final String baseUrl;
  String? token;
  String? mode;

  Map<String, String> _headers({bool json = true, String method = 'GET'}) {
    return {
      if (json) 'Accept': 'application/json',
      if (json) 'Content-Type': 'application/json',
      if (!json) 'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
      if (mode != null && mode!.isNotEmpty) 'X-Case-Mode': mode!,
      // The live host rejects PUT and DELETE before they reach Laravel.
      if (_spoof(method)) 'X-HTTP-Method-Override': method,
    };
  }

  bool _spoof(String method) =>
      method == 'PUT' || method == 'PATCH' || method == 'DELETE';

  String _wireMethod(String method) => _spoof(method) ? 'POST' : method;

  Future<dynamic> getJson(String path) async {
    final response = await http.get(
      Uri.parse('$baseUrl/$path'),
      headers: _headers(),
    );
    return _decode(response);
  }

  Future<dynamic> sendJson(
    String method,
    String path, [
    Map<String, dynamic>? body,
  ]) async {
    final uri = Uri.parse('$baseUrl/$path');
    final payload = body == null ? null : jsonEncode(body);
    final response = await http.Request(_wireMethod(method), uri)
        .replace(
          headers: _headers(method: method),
          body: payload,
        )
        .send();
    final resolved = await http.Response.fromStream(response);
    return _decode(resolved);
  }

  Future<dynamic> postJson(String path, Map<String, dynamic> body) =>
      sendJson('POST', path, body);

  Future<dynamic> putJson(String path, Map<String, dynamic> body) =>
      sendJson('PUT', path, body);

  Future<dynamic> deleteJson(String path) => sendJson('DELETE', path);

  Future<dynamic> upload(
    String method,
    String path,
    Map<String, String> fields, {
    List<int>? bytes,
    String? filename,
  }) async {
    final request = http.MultipartRequest(
      _wireMethod(method),
      Uri.parse('$baseUrl/$path'),
    );
    request.headers.addAll(_headers(json: false, method: method));
    request.fields.addAll(fields);
    if (bytes != null && filename != null) {
      request.files.add(
        http.MultipartFile.fromBytes('file', bytes, filename: filename),
      );
    }
    final response = await http.Response.fromStream(await request.send());
    return _decode(response);
  }

  Future<List<int>> download(String path) async {
    final response = await http.get(
      Uri.parse('$baseUrl/$path'),
      headers: _headers(json: false),
    );
    if (response.statusCode >= 400) {
      throw ApiException('Could not open this file.');
    }
    return response.bodyBytes;
  }

  dynamic _decode(http.Response response) {
    dynamic body;
    try {
      body = response.body.isEmpty ? null : jsonDecode(response.body);
    } catch (_) {
      body = response.body;
    }
    if (response.statusCode >= 400) {
      throw ApiException(_message(body, response.statusCode));
    }
    return body;
  }

  String _message(dynamic body, int status) {
    if (body is Map) {
      final errors = body['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        if (first is List && first.isNotEmpty) {
          return first.first.toString();
        }
        return first.toString();
      }
      if (body['message'] != null) {
        return body['message'].toString();
      }
    }
    if (status == 401) {
      return 'Sign in to continue.';
    }
    return 'Something went wrong.';
  }
}

extension on http.Request {
  http.Request replace({Map<String, String>? headers, String? body}) {
    if (headers != null) {
      this.headers.addAll(headers);
    }
    if (body != null) {
      this.body = body;
    }
    return this;
  }
}
