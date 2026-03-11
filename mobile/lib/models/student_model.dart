class StudentModel {
  const StudentModel({
    required this.id,
    required this.studentCode,
    required this.name,
    required this.classId,
    required this.className,
    required this.year,
    required this.majorId,
    required this.majorName,
    this.photoUrl,
  });

  final String id;
  final String studentCode;
  final String name;
  final String classId;
  final String className;
  final int year;
  final String majorId;
  final String majorName;
  final String? photoUrl;

  String get initials {
    final List<String> parts = name.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty) {
      return '';
    }
    if (parts.length == 1) {
      return parts.first.substring(0, 1).toUpperCase();
    }
    return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
  }

  Map<String, dynamic> toMap() {
    return <String, dynamic>{
      'id': id,
      'student_code': studentCode,
      'name': name,
      'class_id': classId,
      'class_name': className,
      'year': year,
      'major_id': majorId,
      'major_name': majorName,
      'photo_url': photoUrl,
    };
  }

  factory StudentModel.fromMap(Map<String, dynamic> map) {
    final Map<String, dynamic>? classMap =
        map['class'] as Map<String, dynamic>?;
    final Map<String, dynamic>? major = map['major'] as Map<String, dynamic>?;
    return StudentModel(
      id: _stringValue(map['id']),
      studentCode: _stringValue(
        map['student_code'] ?? map['code'] ?? map['student_id'],
      ),
      name:
          map['name'] as String? ??
          map['full_name'] as String? ??
          map['full_name_en'] as String? ??
          map['full_name_kh'] as String? ??
          '${map['first_name'] ?? ''} ${map['last_name'] ?? ''}'.trim(),
      classId: _stringValue(map['class_id'] ?? classMap?['id']),
      className:
          map['class_name'] as String? ??
          classMap?['name'] as String? ??
          classMap?['class_name'] as String? ??
          '',
      year: _intValue(map['year'] ?? classMap?['year_level']) ?? 1,
      majorId: _stringValue(map['major_id'] ?? major?['id']),
      majorName:
          map['major_name'] as String? ??
          major?['name'] as String? ??
          major?['major_name'] as String? ??
          '',
      photoUrl:
          map['photo_url'] as String? ??
          map['image_url'] as String? ??
          map['image'] as String?,
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
