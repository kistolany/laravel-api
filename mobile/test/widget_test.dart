import 'package:flutter_test/flutter_test.dart';

import 'package:sarona_teacher_module/main.dart';

void main() {
  testWidgets('shows login screen after splash', (WidgetTester tester) async {
    await tester.pumpWidget(const SaronaTeacherModuleApp());
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();

    expect(find.text('Teacher Login'), findsOneWidget);
  });
}
