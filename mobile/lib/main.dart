import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'config/app_theme.dart';
import 'providers/attendance_provider.dart';
import 'providers/auth_provider.dart';
import 'screens/attendance/attendance_history_screen.dart';
import 'screens/attendance/edit_attendance_screen.dart';
import 'screens/attendance/mark_attendance_screen.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/otp_screen.dart';
import 'screens/auth/register_screen.dart';
import 'screens/dashboard/teacher_dashboard.dart';
import 'screens/profile/teacher_profile_screen.dart';
import 'screens/splash_screen.dart';
import 'screens/students/student_list_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const SaronaTeacherModuleApp());
}

class SaronaTeacherModuleApp extends StatelessWidget {
  const SaronaTeacherModuleApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider<AuthProvider>(create: (_) => AuthProvider()),
        ChangeNotifierProxyProvider<AuthProvider, AttendanceProvider>(
          create: (_) => AttendanceProvider(),
          update:
              (_, AuthProvider authProvider, AttendanceProvider? attendance) {
                final AttendanceProvider provider =
                    attendance ?? AttendanceProvider();
                provider.bindSession(
                  accessToken: authProvider.accessToken,
                  teacher: authProvider.currentTeacher,
                );
                return provider;
              },
        ),
      ],
      child: MaterialApp(
        title: 'Sarona Teacher Module',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.lightTheme,
        initialRoute: SplashScreen.routeName,
        routes: <String, WidgetBuilder>{
          SplashScreen.routeName: (_) => const SplashScreen(),
          LoginScreen.routeName: (_) => const LoginScreen(),
          RegisterScreen.routeName: (_) => const RegisterScreen(),
          OtpScreen.routeName: (_) => const OtpScreen(),
          TeacherDashboard.routeName: (_) => const TeacherDashboard(),
          StudentListScreen.routeName: (_) => const StudentListScreen(),
          MarkAttendanceScreen.routeName: (_) => const MarkAttendanceScreen(),
          EditAttendanceScreen.routeName: (_) => const EditAttendanceScreen(),
          AttendanceHistoryScreen.routeName: (_) =>
              const AttendanceHistoryScreen(),
          TeacherProfileScreen.routeName: (_) => const TeacherProfileScreen(),
        },
      ),
    );
  }
}
