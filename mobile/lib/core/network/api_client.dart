import 'dart:convert';
import 'package:http/http.dart' as http;
import '../errors/app_exception.dart';
import '../storage/token_storage.dart';
import 'api_config.dart';
import 'api_response.dart';

/// HTTP client for making API requests with automatic token and organization ID injection.
class ApiClient {
  final http.Client _client;
  final String _baseUrl;

  /// Creates an API client with optional custom HTTP client and base URL.
  ApiClient({http.Client? client, String? baseUrl})
      : _client = client ?? http.Client(),
        _baseUrl = baseUrl ?? ApiConfig.localUrl;

  /// Builds request headers including authentication token and organization ID.
  Future<Map<String, String>> _getHeaders() async {
    final token = await TokenStorage.getToken();
    final orgId = await TokenStorage.getOrganizationId();
    final headers = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }
    if (orgId != null && orgId.isNotEmpty) {
      headers['X-Organization-ID'] = orgId;
    }
    return headers;
  }

  /// Performs an HTTP GET request to the specified endpoint.
  Future<ApiResponse<T>> get<T>(String endpoint, {T Function(dynamic)? fromJson}) async {
    try {
      final headers = await _getHeaders();
      final response = await _client.get(
        Uri.parse('$_baseUrl$endpoint'),
        headers: headers,
      ).timeout(ApiConfig.timeoutDuration);
      return _processResponse<T>(response, fromJson);
    } catch (e) {
      throw _handleError(e);
    }
  }

  /// Performs an HTTP POST request with optional body to the specified endpoint.
  Future<ApiResponse<T>> post<T>(String endpoint, {dynamic body, T Function(dynamic)? fromJson}) async {
    try {
      final headers = await _getHeaders();
      final response = await _client.post(
        Uri.parse('$_baseUrl$endpoint'),
        headers: headers,
        body: body != null ? jsonEncode(body) : null,
      ).timeout(ApiConfig.timeoutDuration);
      return _processResponse<T>(response, fromJson);
    } catch (e) {
      throw _handleError(e);
    }
  }

  /// Performs an HTTP PUT request with optional body to the specified endpoint.
  Future<ApiResponse<T>> put<T>(String endpoint, {dynamic body, T Function(dynamic)? fromJson}) async {
    try {
      final headers = await _getHeaders();
      final response = await _client.put(
        Uri.parse('$_baseUrl$endpoint'),
        headers: headers,
        body: body != null ? jsonEncode(body) : null,
      ).timeout(ApiConfig.timeoutDuration);
      return _processResponse<T>(response, fromJson);
    } catch (e) {
      throw _handleError(e);
    }
  }

  /// Performs an HTTP PATCH request with optional body to the specified endpoint.
  Future<ApiResponse<T>> patch<T>(String endpoint, {dynamic body, T Function(dynamic)? fromJson}) async {
    try {
      final headers = await _getHeaders();
      final response = await _client.patch(
        Uri.parse('$_baseUrl$endpoint'),
        headers: headers,
        body: body != null ? jsonEncode(body) : null,
      ).timeout(ApiConfig.timeoutDuration);
      return _processResponse<T>(response, fromJson);
    } catch (e) {
      throw _handleError(e);
    }
  }

  /// Performs an HTTP DELETE request to the specified endpoint.
  Future<ApiResponse<T>> delete<T>(String endpoint, {T Function(dynamic)? fromJson}) async {
    try {
      final headers = await _getHeaders();
      final response = await _client.delete(
        Uri.parse('$_baseUrl$endpoint'),
        headers: headers,
      ).timeout(ApiConfig.timeoutDuration);
      return _processResponse<T>(response, fromJson);
    } catch (e) {
      throw _handleError(e);
    }
  }

  /// Processes HTTP response and throws appropriate exceptions for error status codes.
  ApiResponse<T> _processResponse<T>(http.Response response, T Function(dynamic)? fromJson) {
    final body = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 200 && response.statusCode < 300) {
      return ApiResponse.fromJson(body, fromJson);
    } else if (response.statusCode == 401) {
      throw UnauthorizedException(body['message'] ?? 'Unauthorized');
    } else if (response.statusCode == 403) {
      throw ForbiddenException(body['message'] ?? 'Forbidden');
    } else if (response.statusCode == 404) {
      throw NotFoundException(body['message'] ?? 'Not Found');
    } else if (response.statusCode == 422) {
      throw ValidationException(body['errors'] ?? {}, body['message'] ?? 'Validation Failed');
    } else {
      throw AppException(body['message'] ?? 'Server error', response.statusCode);
    }
  }

  /// Wraps exceptions in appropriate app exception types.
  Exception _handleError(dynamic error) {
    if (error is AppException) return error;
    return NetworkException(error.toString());
  }
}
