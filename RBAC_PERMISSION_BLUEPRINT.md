# RBAC Permission Blueprint

This project already uses the `module.action` permission style, for example `user.view` and `class.create`.

The permissions below are the recommended standard names for the missing modules that now have frontend and backend compatibility support.

## Active Permission Names

### Settings / Access Control

- `user.view`
- `user.create`
- `user.update`
- `user.delete`
- `user.status.update`
- `role.view`
- `role.create`
- `role.update`
- `role.delete`
- `permission.view`
- `permission.create`
- `permission.update`
- `permission.delete`
- `audit_log.view`
- `audit_log.delete`

### Staff Attendance

- `staff_attendance.view`
- `staff_attendance.create`
- `staff_attendance.update`
- `staff_attendance.report`
- `staff_attendance.export`

### Student Payment

- `student_payment.view`
- `student_payment.create`
- `student_payment.update`
- `student_payment.delete`
- `student_payment.export`

### Reports

- `report.export`
- `report_transcript.view`
- `report_student_progress.view`
- `report_class_performance.view`
- `report_attendance_summary.view`
- `report_financial.view`
- `report_enrollment_statistics.view`
- `report_gpa_standing.view`
- `report_teacher_workload.view`
- `report_graduation.view`
- `report_reexam.view`

## Compatibility Layer

The app currently keeps backward compatibility with older broad permissions.

Examples:

- `student_payment.view` still falls back to `student.view`
- `staff_attendance.view` still falls back to `user.view`
- `permission.view` still falls back to `role.view` for the current Role Manage page
- report page access still falls back to the broader data permissions already used by the app

This lets existing roles continue working while new roles can start using the exact permission names above.

## Remaining Staff Gap

The staff directory and staff form still use the generic `/auth/users` endpoints.

That means true `staff.*` security is **not** fully enforceable yet, because a staff-only permission on a generic user endpoint would still expose the wider user API.

Recommended next backend step:

1. Add dedicated staff endpoints, or add backend-side staff filtering to the user service.
2. Then introduce:
   - `staff.view`
   - `staff.create`
   - `staff.update`
   - `staff.delete`
   - `staff.status.update`

Until that backend split exists, the frontend should keep staff pages mapped to the current `user.*` permissions.
