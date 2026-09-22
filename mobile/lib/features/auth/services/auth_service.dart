import '../../../core/network/api_client.dart';
import '../../../core/network/api_response.dart';
import '../../../core/storage/token_storage.dart';

/// Provides authentication operations including login and logout with token storage.
class AuthService {
  final ApiClient _client;

  /// Creates an auth service with optional custom API client.
  AuthService({ApiClient? client}) : _client = client ?? ApiClient();

  /// Authenticates a user with email and password, and optionally an organization code.
  ///
  /// On success, saves the authentication token, organization ID, and role to local storage.
  Future<ApiResponse<Map<String, dynamic>>> login({
    required String email,
    required String password,
    String? organizationCode,
  }) async {
    final response = await _client.post<Map<String, dynamic>>(
      '/auth/login',
      body: {
        'email': email,
        'password': password,
        'organization_code': organizationCode,
      },
      fromJson: (json) => Map<String, dynamic>.from(json),
    );

    if (response.success && response.data != null) {
      final data = response.data!;
      if (data['token'] != null) {
        await TokenStorage.saveToken(data['token']);
      }
      if (data['organization'] != null && data['organization']['id'] != null) {
        await TokenStorage.saveOrganizationId(data['organization']['id'].toString());
      }
      if (data['role'] != null && data['role']['name'] != null) {
        await TokenStorage.saveRole(data['role']['name']);
      }
    }

    return response;
  }

  /// Logs out the current user by calling the logout endpoint and clearing local storage.
  Future<void> logout() async {
    try {
      await _client.post('/auth/logout');
    } catch (_) {}
    await TokenStorage.clear();
  }
}
