<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;

require __DIR__ . '/../vendor/autoload.php';

final class PostmanProjectCollectionGenerator
{
    private const PROJECT_COLLECTION_FILE = 'postman/laravel-api-project.postman_collection.json';
    private const PROJECT_ENVIRONMENT_FILE = 'postman/laravel-api-project.postman_environment.json';
    private const ATTENDANCE_COLLECTION_FILE = 'postman/attendance.postman_collection.json';
    private const TEACHER_COLLECTION_FILE = 'postman/teacher-attendance.postman_collection.json';

    private const COLLECTION_INFO = [
        '_postman_id' => '0dc1045f-796a-4fe3-b2ae-fb15dff20885',
        'name' => 'Laravel API Project Collection',
        'description' => 'Full project collection generated from the live api/v1 routes plus the detailed attendance and teacher flows already maintained in this repository.',
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ];

    private const ENVIRONMENT_ID = 'f71fbc4d-6d4e-4b55-a446-0b21f6d7d8b7';

    private const MANUAL_DEFAULTS = [
        'baseUrl' => 'http://localhost:8000',
        'username' => 'admin',
        'password' => '123456',
        'accessToken' => '',
        'refreshToken' => '',
        'userId' => '',
        'roleId' => '',
        'permissionId' => '',
        'facultyId' => '',
        'majorId' => '',
        'subjectId' => '',
        'majorSubjectId' => '',
        'shiftId' => '',
        'classId' => '',
        'classSubjectAssignmentId' => '',
        'studentId' => '',
        'studentCardStudentId' => '',
        'provinceId' => '',
        'districtId' => '',
        'communeId' => '',
        'scholarshipId' => '',
        'studentRegistrationId' => '',
        'academicInfoId' => '',
        'attendanceSessionId' => '',
        'sessionDate' => '2026-03-10',
        'sessionNumber' => '1',
        'joinedDate' => '2026-03-10',
        'leftDate' => '',
        'studentDob' => '2005-01-01',
        'batchYear' => '2026',
        'academicYear' => '2026-2027',
        'yearLevel' => '1',
        'semester' => '1',
        'section' => 'A',
        'studentIdCardNumber' => 'ID-2026-001',
        'teacherId' => '',
        'teacherFirstName' => 'Auto',
        'teacherLastName' => 'Teacher',
        'teacherGender' => 'Male',
        'teacherMajorId' => '1',
        'teacherSubjectId' => '2',
        'teacherEmail' => 'teacher.auto.20260310@example.com',
        'teacherUsername' => 'teacher_auto_20260310',
        'teacherPassword' => 'secret123',
        'teacherPhoneNumber' => '0123456789',
        'teacherTelegram' => '@teacherauto',
        'teacherAddress' => 'Phnom Penh',
        'teacherImagePath' => '',
        'teacherOtpCode' => '',
        'teacherAccessToken' => '',
        'teacherRefreshToken' => '',
        'teacherClassId' => '',
        'teacherStudentId' => '',
        'teacherAttendanceSessionId' => '',
        'teacherSessionDate' => '2026-03-10',
        'teacherSessionNumber' => '1',
        'teacherAttendanceStatus' => 'Present',
        'studentImagePath' => '',
    ];

    private const INLINE_BODIES = [
        'App\Http\Controllers\ApiController\AuthController@register' => [
            'username' => '{{username}}',
            'password' => '{{password}}',
        ],
        'App\Http\Controllers\ApiController\AuthController@login' => [
            'username' => '{{username}}',
            'password' => '{{password}}',
        ],
        'App\Http\Controllers\ApiController\AuthController@refresh' => [
            'refresh_token' => '{{refreshToken}}',
        ],
        'App\Http\Controllers\ApiController\AuthController@logout' => [
            'refresh_token' => '{{refreshToken}}',
        ],
        'App\Http\Controllers\ApiController\AuthController@revoke' => [
            'refresh_token' => '{{refreshToken}}',
        ],
        'App\Http\Controllers\ApiController\AuthController@createUser' => [
            'username' => 'staff_user',
            'password' => 'secret123',
            'role_id' => '{{roleId}}',
            'status' => 'Active',
        ],
        'App\Http\Controllers\ApiController\AuthController@updateStatus' => [
            'status' => 'Active',
        ],
        'App\Http\Controllers\ApiController\RoleController@store' => [
            'name' => 'Teacher',
            'description' => 'Teacher role',
        ],
        'App\Http\Controllers\ApiController\RoleController@update' => [
            'name' => 'Teacher',
            'description' => 'Teacher role updated',
        ],
        'App\Http\Controllers\ApiController\RoleController@assignPermissions' => [
            'permission_ids' => ['{{permissionId}}'],
            'mode' => 'sync',
        ],
        'App\Http\Controllers\ApiController\PermissionController@store' => [
            'name' => 'view_report',
        ],
        'App\Http\Controllers\ApiController\PermissionController@update' => [
            'name' => 'view_report_updated',
        ],
        'App\Http\Controllers\ApiController\TeacherAuthController@login' => [
            'login' => '{{teacherEmail}}',
            'password' => '{{teacherPassword}}',
        ],
        'App\Http\Controllers\ApiController\TeacherAuthController@verifyOtp' => [
            'email' => '{{teacherEmail}}',
            'otp_code' => '{{teacherOtpCode}}',
        ],
        'App\Http\Controllers\ApiController\TeacherAuthController@resendOtp' => [
            'email' => '{{teacherEmail}}',
        ],
        'App\Http\Controllers\ApiController\TeacherAuthController@refresh' => [
            'refresh_token' => '{{teacherRefreshToken}}',
        ],
        'App\Http\Controllers\ApiController\TeacherAuthController@logout' => [
            'refresh_token' => '{{teacherRefreshToken}}',
        ],
        'App\Http\Controllers\ApiController\TeacherAuthController@revoke' => [
            'refresh_token' => '{{teacherRefreshToken}}',
        ],
    ];

    public function run(): void
    {
        $attendance = $this->loadJson(self::ATTENDANCE_COLLECTION_FILE);
        $teacher = $this->loadJson(self::TEACHER_COLLECTION_FILE);
        $routes = $this->loadRoutes();

        [$generatedRouteFolders, $generatedVariables] = $this->buildGeneratedRouteFolders($routes);

        $variables = $this->mergeVariables(
            self::MANUAL_DEFAULTS,
            $attendance['variable'] ?? [],
            $teacher['variable'] ?? [],
            $generatedVariables,
        );

        $collection = [
            'info' => self::COLLECTION_INFO,
            'variable' => $variables,
            'item' => [
                [
                    'name' => 'Admin Attendance & Setup',
                    'description' => 'Detailed admin attendance flow maintained manually for realistic setup and attendance recording.',
                    'item' => $attendance['item'] ?? [],
                ],
                [
                    'name' => 'Teacher Module',
                    'description' => 'Detailed teacher registration, OTP, login, and teacher attendance flow maintained manually.',
                    'item' => $teacher['item'] ?? [],
                ],
                [
                    'name' => 'All API Routes',
                    'description' => 'Auto-generated from php artisan route:list --json. Covers every declared api/v1 route, grouped by the first route segment.',
                    'item' => $generatedRouteFolders,
                ],
            ],
        ];

        $environment = [
            'id' => self::ENVIRONMENT_ID,
            'name' => 'Laravel API Project Local',
            'values' => array_map(
                static fn (array $variable): array => [
                    'key' => $variable['key'],
                    'value' => $variable['value'],
                    'type' => 'default',
                    'enabled' => true,
                ],
                $variables
            ),
            '_postman_variable_scope' => 'environment',
            '_postman_exported_at' => date(DATE_ATOM),
            '_postman_exported_using' => 'OpenAI Codex',
        ];

        $this->writeJson(self::PROJECT_COLLECTION_FILE, $collection);
        $this->writeJson(self::PROJECT_ENVIRONMENT_FILE, $environment);

        printf(
            "Generated %d grouped route folders from %d api/v1 routes.\n",
            count($generatedRouteFolders),
            count($routes)
        );
    }

    private function loadRoutes(): array
    {
        $command = escapeshellarg(PHP_BINARY) . ' artisan route:list --json';
        $output = shell_exec($command);

        if (!is_string($output) || trim($output) === '') {
            throw new RuntimeException('Unable to read routes from php artisan route:list --json.');
        }

        $routes = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        return array_values(array_filter($routes, static function (array $route): bool {
            return str_starts_with((string) ($route['uri'] ?? ''), 'api/v1/');
        }));
    }

    private function buildGeneratedRouteFolders(array $routes): array
    {
        $groupedItems = [];
        $variables = [];

        foreach ($routes as $route) {
            $groupKey = $this->routeGroupKey((string) $route['uri']);
            $groupedItems[$groupKey][] = $this->buildRouteItem($route, $variables);
        }

        ksort($groupedItems, SORT_NATURAL | SORT_FLAG_CASE);

        $folders = [];

        foreach ($groupedItems as $groupKey => $items) {
            usort($items, static function (array $left, array $right): int {
                return strcmp($left['name'], $right['name']);
            });

            $folders[] = [
                'name' => $this->folderLabel($groupKey),
                'item' => $items,
            ];
        }

        return [$folders, $variables];
    }

    private function buildRouteItem(array $route, array &$variables): array
    {
        $method = $this->normalizeMethod((string) ($route['method'] ?? 'GET'));
        $uri = (string) $route['uri'];
        $action = (string) ($route['action'] ?? '');
        $middleware = $route['middleware'] ?? [];

        [$rawUrl, $pathSegments] = $this->buildUrl($uri, $variables);
        $body = $this->buildRequestBody($route, $variables);

        $request = [
            'method' => $method,
            'header' => $this->buildHeaders($method, $middleware, $body),
            'url' => [
                'raw' => $rawUrl,
                'host' => ['{{baseUrl}}'],
                'path' => $pathSegments,
            ],
            'description' => $this->buildRouteDescription($action, $middleware),
        ];

        if ($body !== null) {
            $request['body'] = $body;
        }

        return [
            'name' => $this->requestName($method, $uri),
            'request' => $request,
            'response' => [],
        ];
    }

    private function buildHeaders(string $method, array $middleware, ?array $body): array
    {
        $headers = [
            [
                'key' => 'Accept',
                'value' => 'application/json',
            ],
        ];

        $authorization = $this->authorizationHeaderValue($middleware);

        if ($authorization !== null) {
            $headers[] = [
                'key' => 'Authorization',
                'value' => $authorization,
            ];
        }

        $bodyMode = $body['mode'] ?? null;

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && $bodyMode === 'raw') {
            $headers[] = [
                'key' => 'Content-Type',
                'value' => 'application/json',
            ];
        }

        return $headers;
    }

    private function authorizationHeaderValue(array $middleware): ?string
    {
        $flattened = implode(',', $middleware);

        if (str_contains($flattened, 'TeacherJwtMiddleware') || str_contains($flattened, 'auth.teacher')) {
            return 'Bearer {{teacherAccessToken}}';
        }

        if (str_contains($flattened, 'JwtMiddleware') || str_contains($flattened, 'auth.jwt')) {
            return 'Bearer {{accessToken}}';
        }

        return null;
    }

    private function buildRequestBody(array $route, array &$variables): ?array
    {
        $method = $this->normalizeMethod((string) ($route['method'] ?? 'GET'));
        $action = (string) ($route['action'] ?? '');
        $uri = (string) ($route['uri'] ?? '');

        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return null;
        }

        if (isset(self::INLINE_BODIES[$action])) {
            return $this->rawJsonBody(self::INLINE_BODIES[$action]);
        }

        $requestClass = $this->resolveFormRequestClass($action);

        if ($requestClass === \App\Http\Requests\TeacherRegisterRequest::class) {
            return $this->teacherRegisterBody();
        }

        if ($requestClass === \App\Http\Requests\StudentImageRequest::class || str_ends_with($uri, '/image')) {
            return $this->studentImageBody();
        }

        if ($requestClass !== null) {
            $payload = $this->samplePayloadFromRequest($requestClass, $action, $variables);

            return $this->rawJsonBody($payload);
        }

        return $this->rawJsonBody(new stdClass());
    }

    private function teacherRegisterBody(): array
    {
        return [
            'mode' => 'formdata',
            'formdata' => [
                ['key' => 'first_name', 'value' => '{{teacherFirstName}}', 'type' => 'text'],
                ['key' => 'last_name', 'value' => '{{teacherLastName}}', 'type' => 'text'],
                ['key' => 'gender', 'value' => '{{teacherGender}}', 'type' => 'text'],
                ['key' => 'major_id', 'value' => '{{teacherMajorId}}', 'type' => 'text'],
                ['key' => 'subject_id', 'value' => '{{teacherSubjectId}}', 'type' => 'text'],
                ['key' => 'email', 'value' => '{{teacherEmail}}', 'type' => 'text'],
                ['key' => 'username', 'value' => '{{teacherUsername}}', 'type' => 'text'],
                ['key' => 'password', 'value' => '{{teacherPassword}}', 'type' => 'text'],
                ['key' => 'phone_number', 'value' => '{{teacherPhoneNumber}}', 'type' => 'text'],
                ['key' => 'telegram', 'value' => '{{teacherTelegram}}', 'type' => 'text'],
                ['key' => 'address', 'value' => '{{teacherAddress}}', 'type' => 'text'],
                ['key' => 'image', 'type' => 'file', 'src' => '{{teacherImagePath}}', 'disabled' => true],
            ],
        ];
    }

    private function studentImageBody(): array
    {
        return [
            'mode' => 'formdata',
            'formdata' => [
                ['key' => 'image', 'type' => 'file', 'src' => '{{studentImagePath}}'],
            ],
        ];
    }

    private function rawJsonBody(array|stdClass $payload): array
    {
        return [
            'mode' => 'raw',
            'raw' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'options' => [
                'raw' => [
                    'language' => 'json',
                ],
            ],
        ];
    }

    private function samplePayloadFromRequest(string $requestClass, string $action, array &$variables): array
    {
        /** @var FormRequest $request */
        $request = new $requestClass();
        $rules = $request->rules();
        $payload = [];

        foreach ($rules as $key => $ruleSet) {
            $ruleTokens = $this->normalizeRules($ruleSet);
            $value = $this->exampleValue($key, $ruleTokens, $action, $variables);
            $this->insertValue($payload, explode('.', $key), $value);
        }

        return $payload;
    }

    private function normalizeRules(mixed $ruleSet): array
    {
        if (is_string($ruleSet)) {
            return array_values(array_filter(explode('|', $ruleSet)));
        }

        if (is_array($ruleSet)) {
            $tokens = [];

            foreach ($ruleSet as $rule) {
                if (is_string($rule)) {
                    array_push($tokens, ...array_filter(explode('|', $rule)));
                }
            }

            return $tokens;
        }

        return [];
    }

    private function exampleValue(string $key, array $ruleTokens, string $action, array &$variables): mixed
    {
        $field = strtolower(last(explode('.', $key)) ?: $key);
        $ruleText = strtolower(implode('|', $ruleTokens));

        $variable = $this->variablePlaceholderForField($key, $action);

        if ($variable !== null) {
            $variables[$variable] ??= self::MANUAL_DEFAULTS[$variable] ?? '';

            return '{{' . $variable . '}}';
        }

        if (preg_match('/\bin:([^|]+)/i', implode('|', $ruleTokens), $matches)) {
            $options = array_values(array_filter(array_map('trim', explode(',', $matches[1]))));
            if ($options !== []) {
                return $options[0];
            }
        }

        if (str_contains($ruleText, 'boolean')) {
            return true;
        }

        if (str_contains($ruleText, 'array')) {
            return [];
        }

        if (str_contains($ruleText, 'date')) {
            if (str_contains($field, 'dob')) {
                return self::MANUAL_DEFAULTS['studentDob'];
            }

            return self::MANUAL_DEFAULTS['sessionDate'];
        }

        if (str_contains($ruleText, 'integer') || str_contains($ruleText, 'numeric')) {
            if (str_contains($field, 'year')) {
                return (int) self::MANUAL_DEFAULTS['batchYear'];
            }

            if (str_contains($field, 'semester')) {
                return (int) self::MANUAL_DEFAULTS['semester'];
            }

            if (str_contains($field, 'session_number')) {
                return (int) self::MANUAL_DEFAULTS['sessionNumber'];
            }

            return 1;
        }

        if (str_contains($field, 'email')) {
            return str_contains($action, 'TeacherAuthController') ? self::MANUAL_DEFAULTS['teacherEmail'] : 'sample@example.com';
        }

        if (str_contains($field, 'username')) {
            return str_contains($action, 'TeacherAuthController') ? self::MANUAL_DEFAULTS['teacherUsername'] : 'sample_user';
        }

        if (str_contains($field, 'password')) {
            return str_contains($action, 'TeacherAuthController') ? self::MANUAL_DEFAULTS['teacherPassword'] : 'secret123';
        }

        if ($field === 'gender') {
            return 'Male';
        }

        if (str_contains($field, 'phone')) {
            return '0123456789';
        }

        if (str_contains($field, 'telegram')) {
            return '@telegram';
        }

        if (str_contains($field, 'address')) {
            return 'Sample address';
        }

        if ($field === 'study_days') {
            return 'Mon-Fri';
        }

        if ($field === 'stage') {
            return 'Year 1';
        }

        if ($field === 'subject_code') {
            return 'SUB001';
        }

        if ($field === 'code') {
            return 'CODE001';
        }

        if ($field === 'section') {
            return self::MANUAL_DEFAULTS['section'];
        }

        if ($field === 'academic_year') {
            return self::MANUAL_DEFAULTS['academicYear'];
        }

        if ($field === 'image') {
            return 'sample-image.jpg';
        }

        if (str_starts_with($field, 'full_name')) {
            return $field === 'full_name_kh' ? 'សិស្ស សាកល្បង' : 'Sample Student';
        }

        if (str_contains($field, 'name_kh')) {
            return 'ឈ្មោះសាកល្បង';
        }

        if (str_contains($field, 'name_en') || str_contains($field, 'name_eg') || $field === 'name') {
            return 'Sample Name';
        }

        if (str_contains($field, 'description')) {
            return 'Sample description';
        }

        if (str_contains($field, 'status')) {
            if (str_contains($ruleText, 'present')) {
                return 'Present';
            }

            if (str_contains($ruleText, 'active')) {
                return 'Active';
            }

            if (str_contains($ruleText, 'enable')) {
                return 'enable';
            }
        }

        if ($field === 'login') {
            return self::MANUAL_DEFAULTS['teacherEmail'];
        }

        if ($field === 'otp_code') {
            return self::MANUAL_DEFAULTS['teacherOtpCode'];
        }

        if ($field === 'id_card_number') {
            return self::MANUAL_DEFAULTS['studentIdCardNumber'];
        }

        return 'sample';
    }

    private function variablePlaceholderForField(string $key, string $action): ?string
    {
        return match ($key) {
            'major_id' => str_contains($action, 'Teacher') ? 'teacherMajorId' : 'majorId',
            'subject_id' => str_contains($action, 'Teacher') ? 'teacherSubjectId' : 'subjectId',
            'class_id' => str_contains($action, 'Teacher') ? 'teacherClassId' : 'classId',
            'shift_id' => 'shiftId',
            'faculty_id' => 'facultyId',
            'student_id', 'records.*.student_id' => str_contains($action, 'Teacher') ? 'teacherStudentId' : 'studentId',
            'role_id' => 'roleId',
            'session_date' => str_contains($action, 'Teacher') ? 'teacherSessionDate' : 'sessionDate',
            'session_number' => str_contains($action, 'Teacher') ? 'teacherSessionNumber' : 'sessionNumber',
            'joined_date' => 'joinedDate',
            'left_date' => 'leftDate',
            'dob' => 'studentDob',
            'batch_year' => 'batchYear',
            'academic_year' => 'academicYear',
            'year_level' => 'yearLevel',
            'semester' => 'semester',
            'province_id', 'addresses.*.province_id' => 'provinceId',
            'district_id', 'addresses.*.district_id' => 'districtId',
            'commune_id', 'addresses.*.commune_id' => 'communeId',
            default => null,
        };
    }

    private function insertValue(array &$payload, array $segments, mixed $value): void
    {
        $cursor =& $payload;
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            $isLast = $index === $lastIndex;
            $next = $segments[$index + 1] ?? null;

            if ($segment === '*') {
                if (!isset($cursor[0])) {
                    $cursor[0] = $isLast ? $value : [];
                }

                if (!$isLast) {
                    if (!is_array($cursor[0])) {
                        $cursor[0] = [];
                    }
                    $cursor =& $cursor[0];
                }

                continue;
            }

            if ($isLast) {
                $cursor[$segment] = $value;
                continue;
            }

            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = $next === '*' ? [] : [];
            }

            $cursor =& $cursor[$segment];
        }
    }

    private function resolveFormRequestClass(string $action): ?string
    {
        if ($action === '' || !str_contains($action, '@')) {
            return null;
        }

        [$className, $methodName] = explode('@', $action, 2);

        if (!class_exists($className) || !method_exists($className, $methodName)) {
            return null;
        }

        $reflection = new ReflectionMethod($className, $methodName);

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();

            if (is_subclass_of($typeName, FormRequest::class)) {
                return $typeName;
            }
        }

        return null;
    }

    private function buildRouteDescription(string $action, array $middleware): string
    {
        $parts = [];

        if ($action !== '') {
            $parts[] = 'Action: ' . $action;
        }

        if ($middleware !== []) {
            $parts[] = 'Middleware: ' . implode(', ', $middleware);
        }

        return implode("\n", $parts);
    }

    private function buildUrl(string $uri, array &$variables): array
    {
        $segments = explode('/', $uri);
        $postmanSegments = [];

        foreach ($segments as $index => $segment) {
            if (preg_match('/^\{(.+)\}$/', $segment, $matches) !== 1) {
                $postmanSegments[] = $segment;
                continue;
            }

            $variableName = $this->routeParameterVariableName($segments, $index, $matches[1]);
            $variables[$variableName] ??= self::MANUAL_DEFAULTS[$variableName] ?? '';
            $postmanSegments[] = '{{' . $variableName . '}}';
        }

        return ['{{baseUrl}}/' . implode('/', $postmanSegments), $postmanSegments];
    }

    private function routeParameterVariableName(array $segments, int $index, string $parameter): string
    {
        $manualByUri = [
            'auth/users:id' => 'userId',
            'roles:id' => 'roleId',
            'attendance-sessions:id' => 'attendanceSessionId',
            'teacher/attendance/history:id' => 'teacherAttendanceSessionId',
            'teacher/classes:id' => 'teacherClassId',
            'classes:id' => 'classId',
            'students:id' => 'studentId',
        ];

        $key = $this->pathKeyForParameter($segments, $index, $parameter);

        if (isset($manualByUri[$key])) {
            return $manualByUri[$key];
        }

        return match ($parameter) {
            'role' => 'roleId',
            'permission' => 'permissionId',
            'student' => 'studentId',
            'student_id' => 'studentCardStudentId',
            'major' => 'majorId',
            'majorId' => 'majorId',
            'facultyId' => 'facultyId',
            'subjectId' => 'subjectId',
            'major_subject' => 'majorSubjectId',
            'shiftId' => 'shiftId',
            'province' => 'provinceId',
            'district' => 'districtId',
            'commune' => 'communeId',
            'scholarship' => 'scholarshipId',
            'student_registration' => 'studentRegistrationId',
            'academic_info' => 'academicInfoId',
            default => $this->contextualIdVariableName($segments, $index, $parameter),
        };
    }

    private function pathKeyForParameter(array $segments, int $index, string $parameter): string
    {
        $relevant = [];

        foreach (array_slice($segments, 2, $index - 2) as $segment) {
            if (!preg_match('/^\{.+\}$/', $segment)) {
                $relevant[] = $segment;
            }
        }

        return implode('/', $relevant) . ':' . $parameter;
    }

    private function contextualIdVariableName(array $segments, int $index, string $parameter): string
    {
        if ($parameter !== 'id') {
            return $this->camelCase($parameter);
        }

        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            $segment = $segments[$cursor];

            if ($segment === 'api' || $segment === 'v1' || preg_match('/^\{.+\}$/', $segment)) {
                continue;
            }

            $singular = $this->singularize($segment);

            return $this->camelCase($singular . '_id');
        }

        return 'id';
    }

    private function routeGroupKey(string $uri): string
    {
        $segments = explode('/', $uri);

        return $segments[2] ?? 'misc';
    }

    private function folderLabel(string $groupKey): string
    {
        return implode(' ', array_map(static function (string $part): string {
            return ucfirst($part);
        }, explode('-', $groupKey)));
    }

    private function requestName(string $method, string $uri): string
    {
        $segments = explode('/', $uri);
        $formatted = [];

        foreach ($segments as $index => $segment) {
            if ($index < 2) {
                continue;
            }

            if (preg_match('/^\{(.+)\}$/', $segment, $matches) === 1) {
                $formatted[] = '{' . $this->routeParameterVariableName($segments, $index, $matches[1]) . '}';
                continue;
            }

            $formatted[] = $segment;
        }

        return $method . ' ' . implode('/', $formatted);
    }

    private function normalizeMethod(string $method): string
    {
        $parts = array_values(array_filter(explode('|', $method)));

        foreach (['POST', 'PATCH', 'PUT', 'DELETE', 'GET'] as $preferred) {
            if (in_array($preferred, $parts, true)) {
                return $preferred;
            }
        }

        return $parts[0] ?? 'GET';
    }

    private function mergeVariables(array $defaults, array ...$sources): array
    {
        $merged = [];

        foreach ($defaults as $key => $value) {
            $merged[$key] = [
                'key' => $key,
                'value' => $value,
            ];
        }

        foreach ($sources as $source) {
            foreach ($source as $key => $value) {
                if (is_array($value) && isset($value['key'])) {
                    $currentKey = (string) $value['key'];
                    $merged[$currentKey] = [
                        'key' => $currentKey,
                        'value' => (string) ($value['value'] ?? ''),
                    ];
                    continue;
                }

                if (is_string($key)) {
                    $merged[$key] = [
                        'key' => $key,
                        'value' => (string) $value,
                    ];
                }
            }
        }

        ksort($merged, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($merged);
    }

    private function loadJson(string $relativePath): array
    {
        $contents = file_get_contents($relativePath);

        if (!is_string($contents)) {
            throw new RuntimeException('Unable to read ' . $relativePath);
        }

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    private function writeJson(string $relativePath, array $data): void
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($encoded)) {
            throw new RuntimeException('Unable to encode JSON for ' . $relativePath);
        }

        file_put_contents($relativePath, $encoded . PHP_EOL);
    }

    private function singularize(string $value): string
    {
        return match (true) {
            str_ends_with($value, 'ies') => substr($value, 0, -3) . 'y',
            str_ends_with($value, 'sses') => substr($value, 0, -2),
            str_ends_with($value, 's') => substr($value, 0, -1),
            default => $value,
        };
    }

    private function camelCase(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = str_replace(' ', '', ucwords($value));

        return lcfirst($value);
    }
}

function last(array $items): mixed
{
    if ($items === []) {
        return null;
    }

    return $items[array_key_last($items)];
}

(new PostmanProjectCollectionGenerator())->run();
