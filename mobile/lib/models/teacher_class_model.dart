class TeacherClassModel {
  const TeacherClassModel({
    required this.id,
    required this.name,
    required this.majorId,
    this.yearLevel,
  });

  final String id;
  final String name;
  final String majorId;
  final int? yearLevel;

  String get displayName => name;

  factory TeacherClassModel.fromMap(Map<String, dynamic> map) {
    final Map<String, dynamic>? major = map['major'] as Map<String, dynamic>?;
    final String name =
        map['name'] as String? ??
        map['class_name'] as String? ??
        map['code'] as String? ??
        map['title'] as String? ??
        'Class';

    return TeacherClassModel(
      id: _stringValue(map['id']),
      name: name,
      majorId: _stringValue(map['major_id'] ?? major?['id']),
      yearLevel: _intValue(map['year_level'] ?? map['year']),
    );
  }

  static String _stringValue(dynamic value) {
    if (value == null) {
      return '';
    }
    return value.toString();
  }

  static int? _intValue(dynamic value) {
    if (value == null) {
      return null;
    }
    if (value is int) {
      return value;
    }
    return int.tryParse(value.toString());
  }
}
