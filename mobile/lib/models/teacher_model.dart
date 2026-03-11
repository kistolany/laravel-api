class TeacherModel {
  const TeacherModel({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.gender,
    required this.majorId,
    required this.majorName,
    required this.subjectId,
    required this.subjectName,
    required this.email,
    required this.username,
    required this.phoneNumber,
    required this.telegram,
    required this.address,
    required this.isVerified,
    required this.createdAt,
    this.imagePath,
    this.passwordHash,
  });

  final String id;
  final String firstName;
  final String lastName;
  final String gender;
  final String majorId;
  final String majorName;
  final String subjectId;
  final String subjectName;
  final String email;
  final String username;
  final String phoneNumber;
  final String telegram;
  final String address;
  final String? imagePath;
  final String? passwordHash;
  final bool isVerified;
  final DateTime createdAt;

  String get fullName => '$firstName $lastName';

  String get initials {
    final String first = firstName.isEmpty ? '' : firstName[0];
    final String last = lastName.isEmpty ? '' : lastName[0];
    return '$first$last'.toUpperCase();
  }

  TeacherModel copyWith({
    String? id,
    String? firstName,
    String? lastName,
    String? gender,
    String? majorId,
    String? majorName,
    String? subjectId,
    String? subjectName,
    String? email,
    String? username,
    String? phoneNumber,
    String? telegram,
    String? address,
    String? imagePath,
    String? passwordHash,
    bool? isVerified,
    DateTime? createdAt,
  }) {
    return TeacherModel(
      id: id ?? this.id,
      firstName: firstName ?? this.firstName,
      lastName: lastName ?? this.lastName,
      gender: gender ?? this.gender,
      majorId: majorId ?? this.majorId,
      majorName: majorName ?? this.majorName,
      subjectId: subjectId ?? this.subjectId,
      subjectName: subjectName ?? this.subjectName,
      email: email ?? this.email,
      username: username ?? this.username,
      phoneNumber: phoneNumber ?? this.phoneNumber,
      telegram: telegram ?? this.telegram,
      address: address ?? this.address,
      imagePath: imagePath ?? this.imagePath,
      passwordHash: passwordHash ?? this.passwordHash,
      isVerified: isVerified ?? this.isVerified,
      createdAt: createdAt ?? this.createdAt,
    );
  }

  Map<String, dynamic> toMap() {
    return <String, dynamic>{
      'id': id,
      'first_name': firstName,
      'last_name': lastName,
      'gender': gender,
      'major_id': majorId,
      'major_name': majorName,
      'subject_id': subjectId,
      'subject_name': subjectName,
      'email': email,
      'username': username,
      'phone_number': phoneNumber,
      'telegram': telegram,
      'address': address,
      'image_path': imagePath,
      'password_hash': passwordHash,
      'is_verified': isVerified,
      'created_at': createdAt.toIso8601String(),
    };
  }

  factory TeacherModel.fromMap(Map<String, dynamic> map) {
    final Map<String, dynamic>? major = map['major'] as Map<String, dynamic>?;
    final Map<String, dynamic>? subject =
        map['subject'] as Map<String, dynamic>?;

    return TeacherModel(
      id: _stringValue(map['id']),
      firstName: map['first_name'] as String? ?? '',
      lastName: map['last_name'] as String? ?? '',
      gender: map['gender'] as String? ?? '',
      majorId: _stringValue(map['major_id'] ?? major?['id']),
      majorName:
          map['major_name'] as String? ??
          major?['name'] as String? ??
          major?['name_en'] as String? ??
          major?['name_eg'] as String? ??
          major?['name_kh'] as String? ??
          major?['major_name'] as String? ??
          '',
      subjectId: _stringValue(map['subject_id'] ?? subject?['id']),
      subjectName:
          map['subject_name'] as String? ??
          subject?['name'] as String? ??
          subject?['name_en'] as String? ??
          subject?['name_eg'] as String? ??
          subject?['name_kh'] as String? ??
          subject?['subject_name'] as String? ??
          '',
      email: map['email'] as String? ?? '',
      username: map['username'] as String? ?? '',
      phoneNumber: map['phone_number'] as String? ?? '',
      telegram: map['telegram'] as String? ?? '',
      address: map['address'] as String? ?? '',
      imagePath:
          map['image_path'] as String? ??
          map['image_url'] as String? ??
          map['image'] as String? ??
          map['profile_image_url'] as String?,
      passwordHash: map['password_hash'] as String?,
      isVerified: map['is_verified'] as bool? ?? false,
      createdAt:
          DateTime.tryParse(map['created_at'] as String? ?? '') ??
          DateTime.now(),
    );
  }

  static String _stringValue(dynamic value) {
    if (value == null) {
      return '';
    }
    return value.toString();
  }
}
