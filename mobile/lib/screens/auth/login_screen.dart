import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';
import '../../widgets/custom_button.dart';
import '../../widgets/custom_textfield.dart';
import '../dashboard/teacher_dashboard.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  static const String routeName = '/login';

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _identifierController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();

  @override
  void dispose() {
    _identifierController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final AuthProvider authProvider = context.read<AuthProvider>();
    final bool success = await authProvider.login(
      _identifierController.text.trim(),
      _passwordController.text,
    );

    if (!mounted) {
      return;
    }

    final String message =
        authProvider.errorMessage ?? 'Unable to complete login.';
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(success ? 'Login successful.' : message)),
    );

    if (success) {
      Navigator.of(context).pushReplacementNamed(TeacherDashboard.routeName);
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Scaffold(
      body: SafeArea(
        child: Consumer<AuthProvider>(
          builder: (BuildContext context, AuthProvider authProvider, _) {
            return SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 20),
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 520),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      const SizedBox(height: 18),
                      Container(
                        padding: const EdgeInsets.all(24),
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: <Color>[
                              Color(0xFF2C7BE5),
                              Color(0xFF5B9BF0),
                            ],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          borderRadius: BorderRadius.circular(28),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Container(
                              height: 72,
                              width: 72,
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.16),
                                borderRadius: BorderRadius.circular(22),
                              ),
                              child: const Icon(
                                Icons.person_pin_circle_rounded,
                                color: Colors.white,
                                size: 36,
                              ),
                            ),
                            const SizedBox(height: 20),
                            Text(
                              'Teacher Login',
                              style: theme.textTheme.headlineMedium?.copyWith(
                                color: Colors.white,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              'Sign in with your email or username to access your teaching dashboard.',
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: Colors.white.withValues(alpha: 0.9),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 20),
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(22),
                          child: Form(
                            key: _formKey,
                            child: Column(
                              children: <Widget>[
                                Align(
                                  alignment: Alignment.centerLeft,
                                  child: Text(
                                    'Welcome back',
                                    style: theme.textTheme.titleLarge,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Container(
                                  width: double.infinity,
                                  padding: const EdgeInsets.all(14),
                                  decoration: BoxDecoration(
                                    color: theme.colorScheme.primary.withValues(
                                      alpha: 0.08,
                                    ),
                                    borderRadius: BorderRadius.circular(18),
                                  ),
                                  child: Text(
                                    'Use the email or username created from the teacher registration flow.',
                                    style: theme.textTheme.bodyMedium,
                                  ),
                                ),
                                const SizedBox(height: 20),
                                CustomTextField(
                                  label: 'Email or Username',
                                  controller: _identifierController,
                                  prefixIcon: Icons.person_outline_rounded,
                                  keyboardType: TextInputType.emailAddress,
                                  autofillHints: const <String>[
                                    AutofillHints.username,
                                    AutofillHints.email,
                                  ],
                                  validator: (String? value) {
                                    if (value == null || value.trim().isEmpty) {
                                      return 'Please enter your email or username.';
                                    }
                                    return null;
                                  },
                                ),
                                const SizedBox(height: 16),
                                CustomTextField(
                                  label: 'Password',
                                  controller: _passwordController,
                                  prefixIcon: Icons.lock_outline_rounded,
                                  obscureText: true,
                                  autofillHints: const <String>[
                                    AutofillHints.password,
                                  ],
                                  validator: (String? value) {
                                    if (value == null || value.isEmpty) {
                                      return 'Please enter your password.';
                                    }
                                    return null;
                                  },
                                ),
                                const SizedBox(height: 10),
                                Align(
                                  alignment: Alignment.centerRight,
                                  child: TextButton(
                                    onPressed: () {
                                      ScaffoldMessenger.of(
                                        context,
                                      ).showSnackBar(
                                        const SnackBar(
                                          content: Text(
                                            'Connect this action to your forgot-password API flow.',
                                          ),
                                        ),
                                      );
                                    },
                                    child: const Text('Forgot password?'),
                                  ),
                                ),
                                const SizedBox(height: 10),
                                CustomButton(
                                  label: 'Login Securely',
                                  icon: Icons.login_rounded,
                                  isLoading: authProvider.isLoading,
                                  onPressed: _submit,
                                ),
                                const SizedBox(height: 14),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: <Widget>[
                                    Text(
                                      'New teacher account?',
                                      style: theme.textTheme.bodyMedium,
                                    ),
                                    TextButton(
                                      onPressed: () {
                                        Navigator.of(
                                          context,
                                        ).pushNamed(RegisterScreen.routeName);
                                      },
                                      child: const Text('Register'),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}
