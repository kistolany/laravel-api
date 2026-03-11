import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';
import '../../widgets/custom_button.dart';
import 'login_screen.dart';

class OtpScreen extends StatefulWidget {
  const OtpScreen({super.key});

  static const String routeName = '/otp';

  @override
  State<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends State<OtpScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _otpController = TextEditingController();
  Timer? _timer;
  int _remainingSeconds = 0;

  @override
  void initState() {
    super.initState();
    _syncRemainingTime();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) {
        return;
      }
      _syncRemainingTime();
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _otpController.dispose();
    super.dispose();
  }

  void _syncRemainingTime() {
    final DateTime? expiresAt = context.read<AuthProvider>().otpExpiresAt;
    if (expiresAt == null) {
      setState(() {
        _remainingSeconds = 0;
      });
      return;
    }

    final int seconds = expiresAt.difference(DateTime.now()).inSeconds;
    setState(() {
      _remainingSeconds = seconds > 0 ? seconds : 0;
    });
  }

  Future<void> _verify(AuthProvider authProvider) async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final bool success = await authProvider.verifyOtp(
      _otpController.text.trim(),
    );
    if (!mounted) {
      return;
    }

    final String message = success
        ? 'Email verified. You can log in now.'
        : (authProvider.errorMessage ?? 'OTP verification failed.');
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));

    if (success) {
      Navigator.of(context).pushReplacementNamed(LoginScreen.routeName);
    }
  }

  Future<void> _resend(AuthProvider authProvider) async {
    final bool success = await authProvider.resendOtp();
    if (!mounted) {
      return;
    }
    _syncRemainingTime();
    final String message = success
        ? 'A new OTP has been generated.'
        : (authProvider.errorMessage ?? 'Unable to resend OTP.');
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (BuildContext context, AuthProvider authProvider, _) {
        final String email =
            authProvider.pendingVerificationEmail ?? 'your email';
        return Scaffold(
          appBar: AppBar(title: const Text('OTP Verification')),
          body: SafeArea(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(24, 16, 24, 24),
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 480),
                  child: Card(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Container(
                              height: 64,
                              width: 64,
                              decoration: BoxDecoration(
                                color: Theme.of(
                                  context,
                                ).colorScheme.primary.withValues(alpha: 0.12),
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: Icon(
                                Icons.verified_user_rounded,
                                color: Theme.of(context).colorScheme.primary,
                                size: 32,
                              ),
                            ),
                            const SizedBox(height: 18),
                            Text(
                              'Verify Your Email',
                              style: Theme.of(context).textTheme.headlineMedium,
                            ),
                            const SizedBox(height: 8),
                            Text(
                              'Enter the OTP sent to your email. A 6 digit code was sent to $email and expires in 5 minutes.',
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                            const SizedBox(height: 20),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 16,
                                vertical: 6,
                              ),
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(22),
                                border: Border.all(
                                  color: Theme.of(context).colorScheme.outline,
                                ),
                                color: Theme.of(context).colorScheme.surface,
                              ),
                              child: TextFormField(
                                controller: _otpController,
                                keyboardType: TextInputType.number,
                                textAlign: TextAlign.center,
                                style: Theme.of(context).textTheme.headlineSmall
                                    ?.copyWith(letterSpacing: 10),
                                maxLength: 6,
                                inputFormatters: <TextInputFormatter>[
                                  FilteringTextInputFormatter.digitsOnly,
                                ],
                                decoration: const InputDecoration(
                                  labelText: 'OTP',
                                  prefixIcon: Icon(Icons.pin_outlined),
                                  counterText: '',
                                  border: InputBorder.none,
                                ),
                                validator: (String? value) {
                                  if (value == null ||
                                      value.trim().length != 6) {
                                    return 'Please enter a valid 6 digit OTP.';
                                  }
                                  return null;
                                },
                              ),
                            ),
                            const SizedBox(height: 18),
                            Row(
                              children: <Widget>[
                                Icon(
                                  Icons.timer_outlined,
                                  color: Theme.of(
                                    context,
                                  ).colorScheme.secondary,
                                ),
                                const SizedBox(width: 8),
                                Text(
                                  _formatSeconds(_remainingSeconds),
                                  style: Theme.of(
                                    context,
                                  ).textTheme.titleMedium,
                                ),
                              ],
                            ),
                            const SizedBox(height: 24),
                            CustomButton(
                              label: 'Verify OTP',
                              icon: Icons.check_circle_outline_rounded,
                              isLoading: authProvider.isLoading,
                              onPressed: () => _verify(authProvider),
                            ),
                            const SizedBox(height: 12),
                            SizedBox(
                              width: double.infinity,
                              child: OutlinedButton.icon(
                                onPressed:
                                    _remainingSeconds == 0 &&
                                        !authProvider.isLoading
                                    ? () => _resend(authProvider)
                                    : null,
                                icon: const Icon(Icons.refresh_rounded),
                                label: const Text('Resend OTP'),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  String _formatSeconds(int totalSeconds) {
    final int minutes = totalSeconds ~/ 60;
    final int seconds = totalSeconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
  }
}
