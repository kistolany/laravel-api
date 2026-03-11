import 'package:flutter/foundation.dart';

import '../models/teacher_model.dart';
import '../services/auth_service.dart';

class AuthProvider extends ChangeNotifier {
  AuthProvider({AuthService? authService})
    : _authService = authService ?? AuthService();

  final AuthService _authService;

  TeacherModel? _currentTeacher;
  String? _accessToken;
  String? _refreshToken;
  String? _pendingVerificationEmail;
  DateTime? _otpExpiresAt;
  bool _isLoading = false;
  String? _errorMessage;
  List<Map<String, String>> _majors = <Map<String, String>>[];
  final Map<String, List<Map<String, String>>> _subjectsByMajor =
      <String, List<Map<String, String>>>{};

  TeacherModel? get currentTeacher => _currentTeacher;
  String? get accessToken => _accessToken;
  String? get refreshToken => _refreshToken;
  String? get pendingVerificationEmail => _pendingVerificationEmail;
  String? get pendingVerificationOtp => null;
  DateTime? get otpExpiresAt => _otpExpiresAt;
  bool get isLoading => _isLoading;
  bool get isLoggedIn => _currentTeacher != null && _accessToken != null;
  String? get errorMessage => _errorMessage;
  bool get profileUpdateSupported => false;

  List<Map<String, String>> get majors =>
      List<Map<String, String>>.unmodifiable(_majors);

  List<Map<String, String>> subjectsForMajor(String majorId) {
    return List<Map<String, String>>.unmodifiable(
      _subjectsByMajor[majorId] ?? const <Map<String, String>>[],
    );
  }

  Future<void> loadRegistrationMajors() async {
    _errorMessage = null;
    _majors = await _authService.fetchMajors();
    if (_majors.isEmpty) {
      _errorMessage = 'Unable to load majors from the API.';
    }
    notifyListeners();
  }

  Future<void> loadSubjectsForMajor(String majorId) async {
    _errorMessage = null;
    _subjectsByMajor[majorId] = await _authService.fetchSubjectsForMajor(
      majorId,
    );
    if ((_subjectsByMajor[majorId] ?? const <Map<String, String>>[]).isEmpty) {
      _errorMessage = 'Unable to load subjects for the selected major.';
    }
    notifyListeners();
  }

  Future<bool> registerTeacher(TeacherModel teacher, String password) async {
    _setLoading(true);
    _errorMessage = null;
    try {
      final AuthRegistrationResult result = await _authService.registerTeacher(
        teacher,
        password,
      );
      _pendingVerificationEmail = result.email;
      _otpExpiresAt = result.expiresAt;
      return true;
    } catch (error) {
      _errorMessage = error.toString().replaceFirst('Exception: ', '');
      return false;
    } finally {
      _setLoading(false);
    }
  }

  Future<bool> verifyOtp(String otp) async {
    final String? email = _pendingVerificationEmail;
    if (email == null) {
      _errorMessage = 'No email is waiting for OTP verification.';
      notifyListeners();
      return false;
    }

    _setLoading(true);
    _errorMessage = null;
    try {
      await _authService.verifyOtp(email, otp);
      _otpExpiresAt = null;
      return true;
    } catch (error) {
      _errorMessage = error.toString().replaceFirst('Exception: ', '');
      return false;
    } finally {
      _setLoading(false);
    }
  }

  Future<bool> resendOtp() async {
    final String? email = _pendingVerificationEmail;
    if (email == null) {
      _errorMessage = 'No email is available for OTP resend.';
      notifyListeners();
      return false;
    }

    _setLoading(true);
    _errorMessage = null;
    try {
      final AuthRegistrationResult result = await _authService.resendOtp(email);
      _otpExpiresAt = result.expiresAt;
      return true;
    } catch (error) {
      _errorMessage = error.toString().replaceFirst('Exception: ', '');
      return false;
    } finally {
      _setLoading(false);
    }
  }

  Future<bool> login(String identifier, String password) async {
    _setLoading(true);
    _errorMessage = null;
    try {
      final AuthSessionResult session = await _authService.login(
        identifier,
        password,
      );
      _accessToken = session.accessToken;
      _refreshToken = session.refreshToken;
      _currentTeacher = session.teacher;
      return true;
    } catch (error) {
      _errorMessage = error.toString().replaceFirst('Exception: ', '');
      return false;
    } finally {
      _setLoading(false);
    }
  }

  Future<bool> refreshSession() async {
    final String? refreshToken = _refreshToken;
    if (refreshToken == null || refreshToken.isEmpty) {
      return false;
    }

    _setLoading(true);
    _errorMessage = null;
    try {
      final AuthSessionResult session = await _authService.refreshSession(
        refreshToken,
      );
      _accessToken = session.accessToken;
      _refreshToken = session.refreshToken;
      _currentTeacher = session.teacher;
      return true;
    } catch (error) {
      _errorMessage = error.toString().replaceFirst('Exception: ', '');
      return false;
    } finally {
      _setLoading(false);
    }
  }

  Future<bool> updateProfile(TeacherModel teacher) async {
    _setLoading(true);
    _errorMessage = null;
    try {
      _currentTeacher = await _authService.updateTeacherProfileLocal(teacher);
      return true;
    } catch (error) {
      _errorMessage = error.toString().replaceFirst('Exception: ', '');
      return false;
    } finally {
      _setLoading(false);
    }
  }

  Future<void> logout() async {
    _setLoading(true);
    try {
      if (_accessToken != null && _refreshToken != null) {
        await _authService.logout(
          accessToken: _accessToken!,
          refreshToken: _refreshToken!,
        );
      }
    } catch (_) {
    } finally {
      _currentTeacher = null;
      _accessToken = null;
      _refreshToken = null;
      _pendingVerificationEmail = null;
      _otpExpiresAt = null;
      _errorMessage = null;
      _setLoading(false);
    }
  }

  void _setLoading(bool value) {
    _isLoading = value;
    notifyListeners();
  }
}
