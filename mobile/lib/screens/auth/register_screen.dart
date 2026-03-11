import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../models/teacher_model.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/custom_button.dart';
import '../../widgets/custom_textfield.dart';
import 'otp_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  static const String routeName = '/register';

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final ImagePicker _imagePicker = ImagePicker();
  final TextEditingController _firstNameController = TextEditingController();
  final TextEditingController _lastNameController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _usernameController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _telegramController = TextEditingController();
  final TextEditingController _addressController = TextEditingController();

  XFile? _selectedImage;
  String? _selectedGender;
  String? _selectedMajorId;
  String? _selectedSubjectId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AuthProvider>().loadRegistrationMajors();
    });
  }

  @override
  void dispose() {
    _firstNameController.dispose();
    _lastNameController.dispose();
    _emailController.dispose();
    _usernameController.dispose();
    _passwordController.dispose();
    _phoneController.dispose();
    _telegramController.dispose();
    _addressController.dispose();
    super.dispose();
  }

  Future<void> _pickImage() async {
    final XFile? file = await _imagePicker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
    );
    if (file == null) {
      return;
    }
    setState(() {
      _selectedImage = file;
    });
  }

  Future<void> _submit(AuthProvider authProvider) async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final List<Map<String, String>> majors = authProvider.majors;
    final List<Map<String, String>> subjects = authProvider.subjectsForMajor(
      _selectedMajorId!,
    );
    if (majors.isEmpty || subjects.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Load valid major and subject data from the API first.',
          ),
        ),
      );
      return;
    }

    final Map<String, String> selectedMajor = majors.firstWhere(
      (Map<String, String> major) => major['id'] == _selectedMajorId,
    );
    final Map<String, String> selectedSubject = subjects.firstWhere(
      (Map<String, String> subject) => subject['id'] == _selectedSubjectId,
    );

    final TeacherModel teacher = TeacherModel(
      id: '',
      firstName: _firstNameController.text.trim(),
      lastName: _lastNameController.text.trim(),
      gender: _selectedGender!,
      majorId: _selectedMajorId!,
      majorName: selectedMajor['name']!,
      subjectId: _selectedSubjectId!,
      subjectName: selectedSubject['name']!,
      email: _emailController.text.trim(),
      username: _usernameController.text.trim(),
      phoneNumber: _phoneController.text.trim(),
      telegram: _telegramController.text.trim(),
      address: _addressController.text.trim(),
      imagePath: _selectedImage?.path,
      isVerified: false,
      createdAt: DateTime.now(),
    );

    final bool success = await authProvider.registerTeacher(
      teacher,
      _passwordController.text,
    );

    if (!mounted) {
      return;
    }

    final String message = success
        ? 'Registration completed. Continue with OTP verification.'
        : (authProvider.errorMessage ?? 'Registration failed.');
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));

    if (success) {
      Navigator.of(context).pushReplacementNamed(OtpScreen.routeName);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (BuildContext context, AuthProvider authProvider, _) {
        final List<Map<String, String>> subjects = _selectedMajorId == null
            ? const <Map<String, String>>[]
            : authProvider.subjectsForMajor(_selectedMajorId!);
        return Scaffold(
          appBar: AppBar(title: const Text('Teacher Registration')),
          body: SafeArea(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(24, 12, 24, 28),
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 560),
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          'Create Teacher Account',
                          style: Theme.of(context).textTheme.headlineMedium,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Register your profile, teaching major, and contact information before verifying your email with OTP.',
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                        if (authProvider.errorMessage
                            case final String message) ...<Widget>[
                          const SizedBox(height: 12),
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: Theme.of(
                                context,
                              ).colorScheme.errorContainer,
                              borderRadius: BorderRadius.circular(18),
                            ),
                            child: Text(
                              message,
                              style: Theme.of(context).textTheme.bodyMedium
                                  ?.copyWith(
                                    color: Theme.of(
                                      context,
                                    ).colorScheme.onErrorContainer,
                                  ),
                            ),
                          ),
                        ],
                        const SizedBox(height: 20),
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(22),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Center(
                                  child: Column(
                                    children: <Widget>[
                                      CircleAvatar(
                                        radius: 42,
                                        backgroundColor: Theme.of(context)
                                            .colorScheme
                                            .primary
                                            .withValues(alpha: 0.12),
                                        backgroundImage: _selectedImage == null
                                            ? null
                                            : FileImage(
                                                File(_selectedImage!.path),
                                              ),
                                        child: _selectedImage == null
                                            ? const Icon(
                                                Icons.camera_alt_rounded,
                                                size: 28,
                                              )
                                            : null,
                                      ),
                                      const SizedBox(height: 12),
                                      OutlinedButton.icon(
                                        onPressed: _pickImage,
                                        icon: const Icon(Icons.upload_rounded),
                                        label: const Text('Upload Image'),
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(height: 20),
                                _ResponsiveFieldRow(
                                  children: <Widget>[
                                    CustomTextField(
                                      label: 'First Name',
                                      controller: _firstNameController,
                                      prefixIcon: Icons.badge_outlined,
                                      validator: _requiredValidator,
                                    ),
                                    CustomTextField(
                                      label: 'Last Name',
                                      controller: _lastNameController,
                                      prefixIcon: Icons.badge_outlined,
                                      validator: _requiredValidator,
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 16),
                                _ResponsiveFieldRow(
                                  children: <Widget>[
                                    DropdownButtonFormField<String>(
                                      initialValue: _selectedGender,
                                      decoration: const InputDecoration(
                                        labelText: 'Gender',
                                        prefixIcon: Icon(
                                          Icons.person_2_outlined,
                                        ),
                                      ),
                                      items:
                                          const <String>[
                                                'Male',
                                                'Female',
                                                'Other',
                                              ]
                                              .map(
                                                (String value) =>
                                                    DropdownMenuItem<String>(
                                                      value: value,
                                                      child: Text(value),
                                                    ),
                                              )
                                              .toList(growable: false),
                                      onChanged: (String? value) {
                                        setState(() {
                                          _selectedGender = value;
                                        });
                                      },
                                      validator: _requiredDropdownValidator,
                                    ),
                                    DropdownButtonFormField<String>(
                                      initialValue: _selectedMajorId,
                                      decoration: const InputDecoration(
                                        labelText: 'Major',
                                        prefixIcon: Icon(
                                          Icons.account_tree_outlined,
                                        ),
                                      ),
                                      items: authProvider.majors
                                          .map(
                                            (Map<String, String> major) =>
                                                DropdownMenuItem<String>(
                                                  value: major['id'],
                                                  child: Text(major['name']!),
                                                ),
                                          )
                                          .toList(growable: false),
                                      onChanged: (String? value) {
                                        setState(() {
                                          _selectedMajorId = value;
                                          _selectedSubjectId = null;
                                        });
                                        if (value != null && value.isNotEmpty) {
                                          context
                                              .read<AuthProvider>()
                                              .loadSubjectsForMajor(value);
                                        }
                                      },
                                      validator: _requiredDropdownValidator,
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 16),
                                DropdownButtonFormField<String>(
                                  initialValue: _selectedSubjectId,
                                  decoration: const InputDecoration(
                                    labelText: 'Subject',
                                    prefixIcon: Icon(Icons.menu_book_outlined),
                                  ),
                                  items: subjects
                                      .map(
                                        (Map<String, String> subject) =>
                                            DropdownMenuItem<String>(
                                              value: subject['id'],
                                              child: Text(subject['name']!),
                                            ),
                                      )
                                      .toList(growable: false),
                                  onChanged: (String? value) {
                                    setState(() {
                                      _selectedSubjectId = value;
                                    });
                                  },
                                  validator: _requiredDropdownValidator,
                                ),
                                if (authProvider.majors.isEmpty) ...<Widget>[
                                  const SizedBox(height: 12),
                                  Align(
                                    alignment: Alignment.centerLeft,
                                    child: Text(
                                      'Major options are loaded from the API. Check your base URL or backend if this list stays empty.',
                                      style: Theme.of(
                                        context,
                                      ).textTheme.bodySmall,
                                    ),
                                  ),
                                ],
                                const SizedBox(height: 16),
                                CustomTextField(
                                  label: 'Email',
                                  controller: _emailController,
                                  prefixIcon: Icons.email_outlined,
                                  keyboardType: TextInputType.emailAddress,
                                  validator: (String? value) {
                                    if (value == null || value.trim().isEmpty) {
                                      return 'Please enter an email address.';
                                    }
                                    final bool isValid = RegExp(
                                      r'^[^@]+@[^@]+\.[^@]+$',
                                    ).hasMatch(value.trim());
                                    return isValid
                                        ? null
                                        : 'Please enter a valid email address.';
                                  },
                                ),
                                const SizedBox(height: 16),
                                _ResponsiveFieldRow(
                                  children: <Widget>[
                                    CustomTextField(
                                      label: 'Username',
                                      controller: _usernameController,
                                      prefixIcon: Icons.alternate_email_rounded,
                                      validator: _requiredValidator,
                                    ),
                                    CustomTextField(
                                      label: 'Password',
                                      controller: _passwordController,
                                      prefixIcon: Icons.lock_outline_rounded,
                                      obscureText: true,
                                      validator: (String? value) {
                                        if (value == null || value.isEmpty) {
                                          return 'Please enter a password.';
                                        }
                                        if (value.length < 8) {
                                          return 'Use at least 8 characters.';
                                        }
                                        return null;
                                      },
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 16),
                                _ResponsiveFieldRow(
                                  children: <Widget>[
                                    CustomTextField(
                                      label: 'Phone Number',
                                      controller: _phoneController,
                                      prefixIcon: Icons.phone_outlined,
                                      keyboardType: TextInputType.phone,
                                      validator: _requiredValidator,
                                    ),
                                    CustomTextField(
                                      label: 'Telegram',
                                      controller: _telegramController,
                                      prefixIcon: Icons.send_outlined,
                                      validator: _requiredValidator,
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 16),
                                CustomTextField(
                                  label: 'Address',
                                  controller: _addressController,
                                  prefixIcon: Icons.location_on_outlined,
                                  maxLines: 3,
                                  validator: _requiredValidator,
                                ),
                                const SizedBox(height: 24),
                                CustomButton(
                                  label: 'Register',
                                  icon: Icons.app_registration_rounded,
                                  isLoading: authProvider.isLoading,
                                  onPressed: () => _submit(authProvider),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
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

  String? _requiredValidator(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'This field is required.';
    }
    return null;
  }

  String? _requiredDropdownValidator(String? value) {
    if (value == null || value.isEmpty) {
      return 'Please make a selection.';
    }
    return null;
  }
}

class _ResponsiveFieldRow extends StatelessWidget {
  const _ResponsiveFieldRow({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints constraints) {
        final bool stacked = constraints.maxWidth < 420;
        if (stacked) {
          return Column(
            children: children
                .map(
                  (Widget child) => Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: child,
                  ),
                )
                .toList(growable: false),
          );
        }

        return Row(
          children: children
              .map(
                (Widget child) => Expanded(
                  child: Padding(
                    padding: EdgeInsets.only(
                      right: child == children.last ? 0 : 12,
                    ),
                    child: child,
                  ),
                ),
              )
              .toList(growable: false),
        );
      },
    );
  }
}
