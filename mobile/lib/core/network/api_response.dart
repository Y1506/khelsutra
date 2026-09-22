/// Represents a standardized API response with success status, message, data, and optional errors.
class ApiResponse<T> {
  final bool success;
  final String message;
  final T? data;
  final Map<String, dynamic>? errors;

  /// Creates an API response with the given success status, message, and optional data/errors.
  ApiResponse({
    required this.success,
    required this.message,
    this.data,
    this.errors,
  });

  /// Creates an API response from JSON with optional custom deserialization for data field.
  factory ApiResponse.fromJson(
    Map<String, dynamic> json,
    T Function(dynamic json)? fromJsonT,
  ) {
    return ApiResponse<T>(
      success: json['success'] ?? false,
      message: json['message'] ?? '',
      data: json['data'] != null && fromJsonT != null
          ? fromJsonT(json['data'])
          : json['data'] as T?,
      errors: json['errors'] != null ? Map<String, dynamic>.from(json['errors']) : null,
    );
  }
}
