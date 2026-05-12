<?php

namespace App\Support;

final class RbacPermissionCatalog
{
    public static function all(): array
    {
        return [
            'user.view',
            'user.create',
            'user.update',
            'user.delete',
            'user.status.update',

            'permission.view',
            'permission.create',
            'permission.update',
            'permission.delete',

            'student.view',
            'student.active.view',
            'student.archive.view',
            'student.create',
            'student.update',
            'student.status.update',
            'student.delete',
            'student.disable',
            'student.image.update',
            'student.classes.view',
            'student.card.view',
            'student.card.by_major.view',

            'teacher.view',
            'teacher.active.view',
            'teacher.archive.view',
            'teacher.create',
            'teacher.update',
            'teacher.status.update',
            'teacher.delete',

            'staff.view',
            'staff.create',
            'staff.update',
            'staff.delete',

            'class.view',
            'class.create',
            'class.delete',
            'class.students.view',
            'class.students.add',
            'class.students.add_by_major',
            'class.subjects.view',
            'class.subjects.assign',

            'subject_classroom.view',
            'subject_classroom.create',
            'subject_classroom.update',
            'subject_classroom.delete',
            'subject_classroom.submit',
            'subject_classroom.grade',
            'subject_classroom.review',

            'attendance.view',
            'attendance.create',
            'attendance.record',
            'attendance.report.by_major_subject',

            'major.view',
            'major.create',
            'major.update',
            'major.delete',
            'major.by_faculty.view',

            'faculty.view',
            'faculty.create',
            'faculty.update',
            'faculty.delete',

            'subject.view',
            'subject.create',
            'subject.update',
            'subject.delete',

            'major_subject.view',
            'major_subject.create',
            'major_subject.update',
            'major_subject.delete',
            'major_subject.by_major.view',

            'academic_info.view',
            'academic_info.create',
            'academic_info.update',
            'academic_info.delete',
            'academic_info.by_major.view',
            'academic_info.by_shift.view',

            'shift.view',
            'shift.create',
            'shift.update',
            'shift.delete',

            'scholarship.view',
            'scholarship.create',
            'scholarship.update',
            'scholarship.delete',

            'student_registration.view',
            'student_registration.create',
            'student_registration.update',
            'student_registration.delete',

            'student_payment.view',
            'student_payment.create',
            'student_payment.update',
            'student_payment.delete',
            'student_payment.export',

            'province.view',
            'province.create',
            'province.update',
            'province.delete',

            'district.view',
            'district.create',
            'district.update',
            'district.delete',

            'commune.view',
            'commune.create',
            'commune.update',
            'commune.delete',

            'class_schedule.view',
            'class_schedule.create',
            'class_schedule.update',
            'class_schedule.delete',
            'class_schedule.archive.view',

            'teacher_attendance.view',
            'teacher_attendance.create',
            'teacher_attendance.update',
            'teacher_attendance.delete',
            'teacher_attendance.report',

            'staff_attendance.view',
            'staff_attendance.create',
            'staff_attendance.update',
            'staff_attendance.report',
            'staff_attendance.export',

            'leave_request.view',
            'leave_request.create',
            'leave_request.update',
            'leave_request.delete',
            'leave_request.approve',

            'role.view',
            'role.create',
            'role.update',
            'role.delete',

            'audit_log.view',
            'audit_log.delete',

            'exam.view',
            'exam.create',
            'exam.update',
            'exam.delete',

            'id_card.view',
            'chat.view',

            'report.export',
            'report_transcript.view',
            'report_student_progress.view',
            'report_class_performance.view',
            'report_attendance_summary.view',
            'report_financial.view',
            'report_enrollment_statistics.view',
            'report_gpa_standing.view',
            'report_teacher_workload.view',
            'report_graduation.view',
            'report_reexam.view',

            'holiday.view',
            'holiday.create',
            'holiday.update',
            'holiday.delete',

            'notification.view',
            'notification.create',
            'notification.delete',

            'tool.view',
        ];
    }

    public static function contains(string $permissionName): bool
    {
        return in_array($permissionName, self::all(), true);
    }
}
