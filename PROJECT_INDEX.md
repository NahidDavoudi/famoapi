# راهنمای پروژه (ایندکس خودکار)

- مسیر: `/opt/apps/famo/api`
- پوشه ماژول‌ها: `app/Modules`
- تعداد فایل: **171** | مجموع خط: **23,403** | ماژول: **26**
- بخش‌هایی که داخل `[ ]` هستن (مثل `[core]`) بیرون از پوشه ماژول‌ها هستن.

## ۱. خلاصه ماژول‌ها

| ماژول | فایل | کل خط | خط کد | کامنت | کلاس | مدل | جدول‌ها | وابسته به | مورد استفاده در |
|---|---:|---:|---:|---:|---:|---:|---:|---|---|
| **Admins** | 1 | 30 | 20 | 5 | 1 | 0 | 1 | [app](1) | Linking(1) |
| **Appointments** | 3 | 323 | 265 | 17 | 3 | 0 | 2 | [app](5) | [routes](2) |
| **Assignments** | 4 | 778 | 553 | 114 | 4 | 0 | 3 | [app](7), Supporters(2), Students(2) | [tests](6), [routes](2), Linking(1) |
| **Attendance** | 3 | 564 | 406 | 74 | 3 | 0 | 2 | [app](5), Bot(2) | [routes](2) |
| **Auth** | 8 | 1,414 | 1,148 | 43 | 8 | 0 | 5 | [app](31), Bot(1), Linking(1) | [tests](6), [routes](5), Bot(1), Tutoring(1) |
| **Blog** | 3 | 589 | 492 | 19 | 3 | 0 | 2 | [app](6) | [routes](1), [tests](1) |
| **Bot** | 6 | 787 | 559 | 109 | 6 | 0 | 3 | [app](6), Students(2), Auth(1) | [tests](12), [routes](3), Attendance(2), Linking(2), Stats(2), Supporters(2), Tutoring(2), Auth(1), Broadcasts(1), Outbox(1), Threads(1) |
| **Broadcasts** | 4 | 525 | 396 | 52 | 4 | 0 | 3 | [app](6), Threads(4), Bot(1), Outbox(1), Students(1) | [routes](2), Outbox(1) |
| **Courses** | 3 | 375 | 322 | 1 | 3 | 0 | 1 | [app](7) | [routes](2), [tests](1) |
| **Dashboard** | 1 | 51 | 34 | 7 | 1 | 0 | 3 | [app](1) | [routes](1) |
| **Exams** | 3 | 398 | 329 | 23 | 3 | 0 | 3 | [app](5) | [routes](2), [tests](1) |
| **Files** | 3 | 338 | 283 | 14 | 3 | 0 | 2 | [app](7) | [routes](2) |
| **Instructors** | 3 | 366 | 312 | 1 | 3 | 0 | 1 | [app](7) | [routes](2), [tests](1) |
| **Linking** | 2 | 365 | 292 | 16 | 2 | 0 | 0 | [app](2), Bot(2), Assignments(1), Admins(1), Supporters(1), Students(1) | [routes](2), Auth(1) |
| **Outbox** | 3 | 548 | 408 | 80 | 3 | 0 | 1 | [app](4), Bot(1), Broadcasts(1), Students(1) | [routes](2), Broadcasts(1), Threads(1), [tests](1) |
| **ParentContacts** | 3 | 345 | 303 | 4 | 3 | 0 | 2 | [app](4) | [routes](2) |
| **Public** | 5 | 190 | 165 | 0 | 5 | 0 | 5 | [app](4) | [routes](1) |
| **Reports** | 3 | 127 | 92 | 13 | 3 | 0 | 3 | [app](2) | [routes](2) |
| **Stats** | 4 | 799 | 549 | 140 | 4 | 0 | 4 | [app](5), Threads(3), Bot(2), Students(1) | [routes](3) |
| **Students** | 3 | 1,013 | 821 | 73 | 3 | 0 | 6 | [app](7), Supporters(1) | Assignments(2), Bot(2), [routes](2), Broadcasts(1), Linking(1), Outbox(1), Stats(1), Threads(1), WeeklyPlans(1) |
| **Subscription** | 3 | 48 | 36 | 6 | 2 | 0 | 1 | [app](1) | - |
| **Supporters** | 3 | 812 | 628 | 71 | 3 | 0 | 4 | [app](7), Bot(2) | [tests](3), Assignments(2), [routes](2), Linking(1), Students(1) |
| **Threads** | 7 | 1,218 | 883 | 163 | 7 | 0 | 4 | [app](14), Bot(1), Outbox(1), Students(1) | Broadcasts(4), Stats(3), [routes](3), [tests](3) |
| **Topics** | 3 | 177 | 149 | 0 | 3 | 0 | 2 | [app](2) | [routes](2) |
| **Tutoring** | 3 | 753 | 509 | 139 | 3 | 0 | 6 | [app](5), Bot(2), Auth(1) | [routes](2) |
| **WeeklyPlans** | 4 | 831 | 670 | 66 | 4 | 0 | 5 | [app](7), Students(1) | [routes](2) |
| **[app]** | 23 | 1,876 | 1,515 | 64 | 23 | 0 | 2 | - | [tests](33), Auth(31), Threads(14), Assignments(7), Courses(7), Files(7), Instructors(7), Students(7), Supporters(7), WeeklyPlans(7), Blog(6), Bot(6), Broadcasts(6), Appointments(5), Attendance(5), Exams(5), Stats(5), Tutoring(5), Outbox(4), ParentContacts(4), Public(4), [bootstrap](4), Linking(2), Reports(2), Topics(2), [routes](2), Admins(1), Dashboard(1), Subscription(1) |
| **[bootstrap]** | 1 | 122 | 100 | 2 | 0 | 0 | 0 | [app](4) | - |
| **[config]** | 2 | 32 | 30 | 0 | 0 | 0 | 0 | - | - |
| **[database]** | 16 | 606 | 606 | 0 | 0 | 0 | 0 | - | - |
| **[public]** | 1 | 7 | 4 | 0 | 0 | 0 | 0 | - | - |
| **[root]** | 2 | 516 | 516 | 0 | 0 | 0 | 0 | - | - |
| **[routes]** | 1 | 325 | 255 | 37 | 0 | 0 | 0 | Auth(5), Stats(3), Bot(3), Threads(3), WeeklyPlans(2), Broadcasts(2), Outbox(2), Reports(2), Courses(2), Topics(2), Linking(2), Supporters(2), Appointments(2), Attendance(2), Students(2), Instructors(2), Tutoring(2), ParentContacts(2), Exams(2), Assignments(2), [app](2), Files(2), Blog(1), Public(1), Dashboard(1) | - |
| **[tests]** | 34 | 6,155 | 4,994 | 124 | 32 | 0 | 26 | [app](33), Bot(12), Auth(6), Assignments(6), Supporters(3), Threads(3), Blog(1), Exams(1), Courses(1), Instructors(1), Outbox(1) | - |

> عدد داخل پرانتز = تعداد وابستگی‌های فایل‌به‌کلاس بین دو ماژول.

## ۲. نگاشت مدل ← جدول

هیچ مدلی پیدا نشد (مدل = فایل داخل پوشه‌ای با اسم Model(s)، یا فایل *Model.php، یا کلاسی که از *Model ارث می‌بره).

## ۳. ایندکس معکوس جدول‌ها ← فایل‌ها

(شامل جدول‌هایی که در SQL خام یا query builder پیدا شدن)

- `admins`: `tests/Modules/Auth/TelegramAdminLinkTest.php`
- `appointments`: `app/Modules/Appointments/Appointment.php`
- `auth_throttle`: `app/Core/RateLimiter.php`, `tests/Core/RateLimiterTest.php`, `tests/Modules/Assignments/AutoAssignmentLinkTest.php`, `tests/Modules/Auth/AuthThrottleTest.php`, `tests/Modules/Auth/TelegramAdminLinkTest.php`, `tests/Modules/Auth/TelegramAuthTest.php`
- `blog_categories`: `tests/Core/BlogPostTest.php`
- `blog_category`: `app/Modules/Blog/BlogPost.php`
- `blog_posts`: `app/Modules/Blog/BlogPost.php`, `tests/Core/BlogPostTest.php`
- `bot_outbox`: `app/Modules/Outbox/OutboxItem.php`, `tests/Modules/Broadcasts/BroadcastTest.php`, `tests/Modules/Outbox/OutboxTest.php`
- `course_features`: `app/Modules/Public/Course.php`
- `courses`: `app/Modules/Courses/Course.php`, `app/Modules/Public/Course.php`, `tests/Core/PaginationTest.php`
- `daily_attendance`: `app/Modules/Attendance/Attendance.php`, `app/Modules/Tutoring/Tutoring.php`, `tests/Modules/Attendance/AttendanceTest.php`, `tests/Modules/Tutoring/TutoringTest.php`
- `event_topics`: `app/Modules/WeeklyPlans/WeeklyPlan.php`
- `events`: `app/Modules/Students/Student.php`, `app/Modules/Students/StudentService.php`, `app/Modules/Topics/Topic.php`, `app/Modules/WeeklyPlans/WeeklyPlan.php`
- `exam_results`: `app/Modules/Dashboard/DashboardController.php`, `app/Modules/Exams/ExamResult.php`, `app/Modules/Students/StudentService.php`, `tests/Core/ExamServiceTest.php`
- `files`: `app/Modules/Exams/ExamResult.php`, `app/Modules/Files/File.php`
- `instructor_social_links`: `app/Modules/Public/Instructor.php`
- `instructors`: `app/Modules/Instructors/Instructor.php`, `app/Modules/Public/Instructor.php`, `app/Modules/Tutoring/Tutoring.php`, `tests/Core/PaginationTest.php`, `tests/Modules/Auth/TeacherAuthTest.php`, `tests/Modules/Tutoring/TutoringTest.php`
- `login_codes`: `app/Modules/Auth/AuthService.php`, `app/Modules/Auth/TelegramAuthService.php`, `tests/Modules/Auth/AuthTest.php`, `tests/Modules/Auth/AuthThrottleTest.php`, `tests/Modules/Auth/TeacherAuthTest.php`, `tests/Modules/Auth/TelegramAdminLinkTest.php`, `tests/Modules/Auth/TelegramAuthTest.php`
- `migrations`: `app/Core/Migrator.php`
- `notes`: `tests/Core/MigratorTest.php`
- `parent_contacts`: `app/Modules/ParentContacts/ParentContact.php`
- `plan_templates`: `app/Modules/WeeklyPlans/PlanTemplate.php`
- `report_broadcast_recipients`: `app/Modules/Broadcasts/Broadcast.php`, `app/Modules/Broadcasts/BroadcastRecipient.php`, `tests/Modules/Broadcasts/BroadcastTest.php`
- `report_broadcasts`: `app/Modules/Broadcasts/Broadcast.php`, `tests/Modules/Broadcasts/BroadcastTest.php`
- `report_message_attachments`: `app/Modules/Threads/MessageAttachment.php`
- `report_messages`: `app/Modules/Stats/Stats.php`, `app/Modules/Threads/Message.php`, `tests/Modules/Broadcasts/BroadcastTest.php`, `tests/Modules/Stats/StatsTest.php`
- `report_threads`: `app/Modules/Stats/Stats.php`, `app/Modules/Threads/Message.php`, `app/Modules/Threads/Thread.php`, `tests/Modules/Broadcasts/BroadcastTest.php`
- `reports_status`: `app/Modules/Dashboard/DashboardController.php`, `app/Modules/Reports/Report.php`, `app/Modules/Supporters/Supporter.php`
- `student_supporter_assignments`: `app/Modules/Assignments/Assignment.php`, `tests/Modules/Assignments/AssignmentTest.php`, `tests/Modules/Assignments/AutoAssignmentLinkTest.php`, `tests/Modules/Assignments/SupporterScopeTest.php`, `tests/Modules/Bot/BotGatewayTest.php`, `tests/Modules/Broadcasts/BroadcastTest.php`, `tests/Modules/Outbox/OutboxTest.php`, `tests/Modules/Stats/StatsTest.php`, `tests/Modules/Supporters/SupporterContactTest.php`, `tests/Modules/Threads/ThreadTest.php`
- `students`: `app/Modules/Appointments/Appointment.php`, `app/Modules/Assignments/Assignment.php`, `app/Modules/Attendance/Attendance.php`, `app/Modules/Auth/AuthService.php`, `app/Modules/Bot/TelegramLink.php`, `app/Modules/Broadcasts/BroadcastRecipient.php`, `app/Modules/Dashboard/DashboardController.php`, `app/Modules/Exams/ExamResult.php`, `app/Modules/Files/File.php`, `app/Modules/ParentContacts/ParentContactService.php`, `app/Modules/Reports/Report.php`, `app/Modules/Stats/Stats.php` … (+20)
- `subscriptions`: `app/Modules/Subscription/Subscription.php`
- `supporter_scopes`: `app/Modules/Supporters/Supporter.php`, `tests/Modules/Assignments/AutoAssignmentLinkTest.php`, `tests/Modules/Assignments/SupporterScopeTest.php`
- `supporters`: `app/Modules/Assignments/Assignment.php`, `app/Modules/Public/Supporter.php`, `app/Modules/Reports/Report.php`, `app/Modules/Stats/Stats.php`, `app/Modules/Students/Student.php`, `app/Modules/Supporters/Supporter.php`, `tests/Core/PaginationTest.php`, `tests/Modules/Assignments/AssignmentTest.php`, `tests/Modules/Assignments/AutoAssignmentLinkTest.php`, `tests/Modules/Assignments/SupporterScopeTest.php`, `tests/Modules/Auth/TelegramAuthTest.php`, `tests/Modules/Bot/BotGatewayTest.php` … (+5)
- `telegram_2fa_challenges`: `app/Modules/Auth/TelegramAuthService.php`, `tests/Modules/Assignments/AutoAssignmentLinkTest.php`, `tests/Modules/Auth/TelegramAdminLinkTest.php`, `tests/Modules/Auth/TelegramAuthTest.php`
- `telegram_auth_nonces`: `app/Modules/Auth/TelegramLoginVerifier.php`, `app/Modules/Auth/TelegramTicket.php`, `tests/Modules/Assignments/AutoAssignmentLinkTest.php`, `tests/Modules/Auth/TelegramAdminLinkTest.php`, `tests/Modules/Auth/TelegramAuthTest.php`
- `telegram_links`: `app/Modules/Bot/TelegramLink.php`, `tests/Modules/Assignments/AutoAssignmentLinkTest.php`, `tests/Modules/Auth/TelegramAdminLinkTest.php`, `tests/Modules/Auth/TelegramAuthTest.php`, `tests/Modules/Bot/BotGatewayTest.php`, `tests/Modules/Broadcasts/BroadcastTest.php`, `tests/Modules/Outbox/OutboxTest.php`, `tests/Modules/Threads/ThreadTest.php`
- `topic_tree`: `app/Modules/Topics/Topic.php`
- `tutoring_classrooms`: `app/Modules/Tutoring/Tutoring.php`, `tests/Modules/Tutoring/TutoringTest.php`
- `tutoring_sessions`: `app/Modules/Tutoring/Tutoring.php`, `tests/Modules/Tutoring/TutoringTest.php`
- `tutoring_teacher_status_log`: `app/Modules/Tutoring/Tutoring.php`, `tests/Modules/Tutoring/TutoringTest.php`
- `users`: `app/Modules/Admins/Admin.php`, `app/Modules/Auth/AuthService.php`, `app/Modules/Auth/User.php`, `app/Modules/Bot/TelegramLink.php`, `app/Modules/Students/Student.php`, `app/Modules/Students/StudentService.php`, `app/Modules/Supporters/Supporter.php`, `app/Modules/Supporters/SupporterService.php`, `tests/Modules/Assignments/AssignmentTest.php`, `tests/Modules/Assignments/AutoAssignmentLinkTest.php`, `tests/Modules/Assignments/SupporterScopeTest.php`, `tests/Modules/Attendance/AttendanceTest.php` … (+13)
- `weekly_plans`: `app/Modules/Students/Student.php`, `app/Modules/WeeklyPlans/WeeklyPlan.php`

## ۴. جزئیات هر ماژول

### Admins

- فایل: 1 | کل خط: 30 | کد: 20 | کامنت: 5 | خالی: 5
- به تفکیک پسوند: `.php`=30
- namespaceها: `App\Modules\Admins`
- وابسته به ماژول: **[app]**
- مورد استفاده توسط: **Linking**

```
Admins/  (1 files, 30 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Admin.php` | 30 | class Admin | `Database`@[app] | `users` |

### Appointments

- فایل: 3 | کل خط: 323 | کد: 265 | کامنت: 17 | خالی: 41
- به تفکیک پسوند: `.php`=323
- namespaceها: `App\Modules\Appointments`
- وابسته به ماژول: **[app]**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Appointments/  (3 files, 323 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Appointment.php` | 134 | class Appointment | `Database`@[app] | `appointments`, `students` |
| `AppointmentController.php` | 119 | class AppointmentController | `ApiException`@[app] | - |
| `AppointmentService.php` | 70 | class AppointmentService | `ApiException`@[app], `Pagination`@[app], `SmsService`@[app], `Appointment` | - |

### Assignments

- فایل: 4 | کل خط: 778 | کد: 553 | کامنت: 114 | خالی: 111
- به تفکیک پسوند: `.php`=778
- namespaceها: `App\Modules\Assignments`
- وابسته به ماژول: **[app]**, **Supporters**, **Students**
- مورد استفاده توسط: **Linking**, **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Assignments/  (4 files, 778 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Assignment.php` | 261 | class Assignment | `Database`@[app] | `student_supporter_assignments`, `students`, `supporters` |
| `AssignmentController.php` | 100 | class AssignmentController | `ApiException`@[app], `ResponseHelper`@[app] | - |
| `AssignmentService.php` | 247 | class AssignmentService | `ApiException`@[app], `Database`@[app], `Pagination`@[app], `Assignment`, `Student`@Students, `Supporter`@Supporters | - |
| `AutoAssignmentService.php` | 170 | class AutoAssignmentService | `Database`@[app], `Assignment`, `Student`@Students, `Supporter`@Supporters | - |

### Attendance

- فایل: 3 | کل خط: 564 | کد: 406 | کامنت: 74 | خالی: 84
- به تفکیک پسوند: `.php`=564
- namespaceها: `App\Modules\Attendance`
- وابسته به ماژول: **[app]**, **Bot**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `PDO`, `DateTimeZone`, `DateTimeImmutable`

```
Attendance/  (3 files, 564 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Attendance.php` | 279 | class Attendance | `ApiException`@[app], `Database`@[app], `IranDay`@Bot | `daily_attendance`, `students` |
| `AttendanceController.php` | 128 | class AttendanceController | `ApiException`@[app], `Validator`@[app] | - |
| `AttendanceService.php` | 157 | class AttendanceService | `ApiException`@[app], `Attendance`, `IranDay`@Bot | - |

### Auth

- فایل: 8 | کل خط: 1,414 | کد: 1,148 | کامنت: 43 | خالی: 223
- به تفکیک پسوند: `.php`=1,414
- namespaceها: `App\Modules\Auth`
- وابسته به ماژول: **[app]**, **Bot**, **Linking**
- مورد استفاده توسط: **Bot**, **Tutoring**, **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `Slim\Psr7`, `App\Modules`

```
Auth/  (8 files, 1,414 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `AuthController.php` | 312 | class AuthController | `ApiException`@[app], `Auth`@[app], `AuthCookie`@[app], `ClientIp`@[app], `RateLimitedException`@[app], `RateLimiter`@[app], `Validator`@[app] | - |
| `AuthMiddleware.php` | 54 | class AuthMiddleware | `Auth`@[app], `AuthCookie`@[app] | - |
| `AuthService.php` | 278 | class AuthService | `ApiException`@[app], `Auth`@[app], `Database`@[app], `RateLimitedException`@[app], `RateLimiter`@[app], `SmsService`@[app], `User` | `login_codes`, `students`, `users` |
| `TelegramAuthController.php` | 139 | class TelegramAuthController | `AuthCookie`@[app], `ClientIp`@[app], `RateLimitedException`@[app], `ResponseHelper`@[app], `Validator`@[app] | - |
| `TelegramAuthService.php` | 333 | class TelegramAuthService | `ApiException`@[app], `Database`@[app], `RateLimitedException`@[app], `RateLimiter`@[app], `SmsService`@[app], `AuthService`, `TelegramLoginVerifier`, `TelegramTicket`, `User`, `TelegramLink`@Bot, `LinkService`@Linking | `login_codes`, `telegram_2fa_challenges` |
| `TelegramLoginVerifier.php` | 113 | class TelegramLoginVerifier | `ApiException`@[app], `Database`@[app] | `telegram_auth_nonces` |
| `TelegramTicket.php` | 96 | class TelegramTicket | `ApiException`@[app], `Auth`@[app], `Database`@[app] | `telegram_auth_nonces` |
| `User.php` | 89 | class User | `Database`@[app] | `users` |

### Blog

- فایل: 3 | کل خط: 589 | کد: 492 | کامنت: 19 | خالی: 78
- به تفکیک پسوند: `.php`=589
- namespaceها: `App\Modules\Blog`
- وابسته به ماژول: **[app]**
- مورد استفاده توسط: **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Blog/  (3 files, 589 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `BlogController.php` | 202 | class BlogController | `ApiException`@[app], `BlogService` | - |
| `BlogPost.php` | 248 | class BlogPost | `Database`@[app], `Pagination`@[app] | `blog_category`, `blog_posts` |
| `BlogService.php` | 139 | class BlogService | `ApiException`@[app], `Cache`@[app], `Pagination`@[app], `BlogPost` | - |

### Bot

- فایل: 6 | کل خط: 787 | کد: 559 | کامنت: 109 | خالی: 119
- به تفکیک پسوند: `.php`=787
- namespaceها: `App\Modules\Bot`
- وابسته به ماژول: **[app]**, **Students**, **Auth**
- مورد استفاده توسط: **Attendance**, **Auth**, **Broadcasts**, **Linking**, **Outbox**, **Stats**, **Supporters**, **Threads**, **Tutoring**, **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `Slim\Psr7`, `DateTimeZone`, `DateTimeImmutable`

```
Bot/  (6 files, 787 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `BotAuth.php` | 66 | class BotAuth | `ResponseHelper`@[app], `TelegramLink` | - |
| `BotController.php` | 271 | class BotController | `ApiException`@[app], `Logger`@[app], `ResponseHelper`@[app], `User`@Auth, `IranDay`, `TelegramLink`, `Student`@Students, `StudentService`@Students | - |
| `BotServiceMiddleware.php` | 65 | class BotServiceMiddleware | `ResponseHelper`@[app] | - |
| `IranDay.php` | 235 | class IranDay | - | - |
| `PhoneNormalizer.php` | 79 | class PhoneNormalizer | - | - |
| `TelegramLink.php` | 71 | class TelegramLink | `Database`@[app] | `students`, `telegram_links`, `users` |

### Broadcasts

- فایل: 4 | کل خط: 525 | کد: 396 | کامنت: 52 | خالی: 77
- به تفکیک پسوند: `.php`=525
- namespaceها: `App\Modules\Broadcasts`
- وابسته به ماژول: **[app]**, **Threads**, **Bot**, **Outbox**, **Students**
- مورد استفاده توسط: **Outbox**, **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Broadcasts/  (4 files, 525 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Broadcast.php` | 94 | class Broadcast | `Database`@[app] | `report_broadcast_recipients`, `report_broadcasts` |
| `BroadcastController.php` | 53 | class BroadcastController | `ResponseHelper`@[app] | - |
| `BroadcastRecipient.php` | 101 | class BroadcastRecipient | `Database`@[app] | `report_broadcast_recipients`, `students` |
| `BroadcastService.php` | 277 | class BroadcastService | `ApiException`@[app], `Database`@[app], `Pagination`@[app], `IranDay`@Bot, `Broadcast`, `BroadcastRecipient`, `OutboxService`@Outbox, `Student`@Students, `Message`@Threads, `MessageAttachment`@Threads, `MessageContent`@Threads, `Thread`@Threads | - |

### Courses

- فایل: 3 | کل خط: 375 | کد: 322 | کامنت: 1 | خالی: 52
- به تفکیک پسوند: `.php`=375
- namespaceها: `App\Modules\Courses`
- وابسته به ماژول: **[app]**
- مورد استفاده توسط: **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Courses/  (3 files, 375 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Course.php` | 87 | class Course | `Database`@[app] | `courses` |
| `CourseController.php` | 183 | class CourseController | `ApiException`@[app], `Validator`@[app] | - |
| `CourseService.php` | 105 | class CourseService | `ApiException`@[app], `Cache`@[app], `Pagination`@[app], `Storage`@[app], `Course` | - |

### Dashboard

- فایل: 1 | کل خط: 51 | کد: 34 | کامنت: 7 | خالی: 10
- به تفکیک پسوند: `.php`=51
- namespaceها: `App\Modules\Dashboard`
- وابسته به ماژول: **[app]**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Dashboard/  (1 files, 51 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `DashboardController.php` | 51 | class DashboardController | `Database`@[app] | `exam_results`, `reports_status`, `students` |

### Exams

- فایل: 3 | کل خط: 398 | کد: 329 | کامنت: 23 | خالی: 46
- به تفکیک پسوند: `.php`=398
- namespaceها: `App\Modules\Exams`
- وابسته به ماژول: **[app]**
- مورد استفاده توسط: **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Exams/  (3 files, 398 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `ExamController.php` | 129 | class ExamController | `ApiException`@[app], `StudentScope`@[app] | - |
| `ExamResult.php` | 190 | class ExamResult | `Database`@[app] | `exam_results`, `files`, `students` |
| `ExamService.php` | 79 | class ExamService | `ApiException`@[app], `Pagination`@[app], `ExamResult` | - |

### Files

- فایل: 3 | کل خط: 338 | کد: 283 | کامنت: 14 | خالی: 41
- به تفکیک پسوند: `.php`=338
- namespaceها: `App\Modules\Files`
- وابسته به ماژول: **[app]**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Files/  (3 files, 338 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `File.php` | 83 | class File | `Database`@[app] | `files`, `students` |
| `FileController.php` | 158 | class FileController | `ApiException`@[app], `ResponseHelper`@[app], `StudentScope`@[app] | - |
| `FileService.php` | 97 | class FileService | `ApiException`@[app], `Pagination`@[app], `Storage`@[app], `File` | - |

### Instructors

- فایل: 3 | کل خط: 366 | کد: 312 | کامنت: 1 | خالی: 53
- به تفکیک پسوند: `.php`=366
- namespaceها: `App\Modules\Instructors`
- وابسته به ماژول: **[app]**
- مورد استفاده توسط: **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Instructors/  (3 files, 366 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Instructor.php` | 78 | class Instructor | `Database`@[app] | `instructors` |
| `InstructorController.php` | 177 | class InstructorController | `ApiException`@[app], `Validator`@[app] | - |
| `InstructorService.php` | 111 | class InstructorService | `ApiException`@[app], `Cache`@[app], `Pagination`@[app], `Storage`@[app], `Instructor` | - |

### Linking

- فایل: 2 | کل خط: 365 | کد: 292 | کامنت: 16 | خالی: 57
- به تفکیک پسوند: `.php`=365
- namespaceها: `App\Modules\Linking`
- وابسته به ماژول: **[app]**, **Bot**, **Assignments**, **Admins**, **Supporters**, **Students**
- مورد استفاده توسط: **Auth**, **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `App\Modules`

```
Linking/  (2 files, 365 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `LinkController.php` | 75 | class LinkController | `ResponseHelper`@[app] | - |
| `LinkService.php` | 290 | class LinkService | `ApiException`@[app], `Admin`@Admins, `AutoAssignmentService`@Assignments, `PhoneNormalizer`@Bot, `TelegramLink`@Bot, `Student`@Students, `Supporter`@Supporters | - |

### Outbox

- فایل: 3 | کل خط: 548 | کد: 408 | کامنت: 80 | خالی: 60
- به تفکیک پسوند: `.php`=548
- namespaceها: `App\Modules\Outbox`
- وابسته به ماژول: **[app]**, **Bot**, **Broadcasts**, **Students**
- مورد استفاده توسط: **Broadcasts**, **Threads**, **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Outbox/  (3 files, 548 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `OutboxController.php` | 44 | class OutboxController | `ApiException`@[app], `ResponseHelper`@[app] | - |
| `OutboxItem.php` | 199 | class OutboxItem | `Database`@[app] | `bot_outbox` |
| `OutboxService.php` | 305 | class OutboxService | `Database`@[app], `TelegramLink`@Bot, `BroadcastRecipient`@Broadcasts, `OutboxItem`, `Student`@Students | - |

### ParentContacts

- فایل: 3 | کل خط: 345 | کد: 303 | کامنت: 4 | خالی: 38
- به تفکیک پسوند: `.php`=345
- namespaceها: `App\Modules\ParentContacts`
- وابسته به ماژول: **[app]**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
ParentContacts/  (3 files, 345 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `ParentContact.php` | 99 | class ParentContact | `Database`@[app] | `parent_contacts` |
| `ParentContactController.php` | 159 | class ParentContactController | `ApiException`@[app] | - |
| `ParentContactService.php` | 87 | class ParentContactService | `ApiException`@[app], `Database`@[app], `ParentContact` | `students` |

### Public

- فایل: 5 | کل خط: 190 | کد: 165 | کامنت: 0 | خالی: 25
- به تفکیک پسوند: `.php`=190
- namespaceها: `App\Modules\Public`
- وابسته به ماژول: **[app]**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Public/  (5 files, 190 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Course.php` | 41 | class Course | `Database`@[app] | `course_features`, `courses` |
| `Instructor.php` | 32 | class Instructor | `Database`@[app] | `instructor_social_links`, `instructors` |
| `PublicController.php` | 52 | class PublicController | `PublicService` | - |
| `PublicService.php` | 47 | class PublicService | `Cache`@[app], `Course`, `Instructor`, `Supporter` | - |
| `Supporter.php` | 18 | class Supporter | `Database`@[app] | `supporters` |

### Reports

- فایل: 3 | کل خط: 127 | کد: 92 | کامنت: 13 | خالی: 22
- به تفکیک پسوند: `.php`=127
- namespaceها: `App\Modules\Reports`
- وابسته به ماژول: **[app]**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Reports/  (3 files, 127 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Report.php` | 68 | class Report | `Database`@[app] | `reports_status`, `students`, `supporters` |
| `ReportController.php` | 38 | class ReportController | - | - |
| `ReportService.php` | 21 | class ReportService | `Pagination`@[app], `Report` | - |

### Stats

- فایل: 4 | کل خط: 799 | کد: 549 | کامنت: 140 | خالی: 110
- به تفکیک پسوند: `.php`=799
- namespaceها: `App\Modules\Stats`
- وابسته به ماژول: **[app]**, **Bot**, **Threads**, **Students**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `Slim\Psr7`

```
Stats/  (4 files, 799 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `ContentAccessMiddleware.php` | 40 | class ContentAccessMiddleware | `ResponseHelper`@[app] | - |
| `Stats.php` | 320 | class Stats | `Database`@[app] | `report_messages`, `report_threads`, `students`, `supporters` |
| `StatsController.php` | 99 | class StatsController | `ResponseHelper`@[app], `IranDay`@Bot | - |
| `StatsService.php` | 340 | class StatsService | `ApiException`@[app], `Pagination`@[app], `IranDay`@Bot, `Stats`, `Student`@Students, `Message`@Threads, `MessageAttachment`@Threads, `Thread`@Threads | - |

### Students

- فایل: 3 | کل خط: 1,013 | کد: 821 | کامنت: 73 | خالی: 119
- به تفکیک پسوند: `.php`=1,013
- namespaceها: `App\Modules\Students`
- وابسته به ماژول: **[app]**, **Supporters**
- مورد استفاده توسط: **Assignments**, **Bot**, **Broadcasts**, **Linking**, **Outbox**, **Stats**, **Threads**, **WeeklyPlans**, **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Students/  (3 files, 1,013 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Student.php` | 277 | class Student | `Database`@[app] | `events`, `students`, `supporters`, `users`, `weekly_plans` |
| `StudentController.php` | 404 | class StudentController | `ApiException`@[app], `StudentScope`@[app], `Validator`@[app] | - |
| `StudentService.php` | 332 | class StudentService | `ApiException`@[app], `Database`@[app], `Pagination`@[app], `Student`, `Supporter`@Supporters | `events`, `exam_results`, `students`, `users` |

### Subscription

- فایل: 3 | کل خط: 48 | کد: 36 | کامنت: 6 | خالی: 6
- به تفکیک پسوند: `.php`=48
- namespaceها: `App\Modules\Subscription`
- وابسته به ماژول: **[app]**

```
Subscription/  (3 files, 48 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Subscription.php` | 36 | class Subscription | `Database`@[app] | `subscriptions` |
| `SubscriptionModel.php` | 2 | - | - | - |
| `SubscriptionService.php` | 10 | class SubscriptionService | - | - |

### Supporters

- فایل: 3 | کل خط: 812 | کد: 628 | کامنت: 71 | خالی: 113
- به تفکیک پسوند: `.php`=812
- namespaceها: `App\Modules\Supporters`
- وابسته به ماژول: **[app]**, **Bot**
- مورد استفاده توسط: **Assignments**, **Linking**, **Students**, **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Supporters/  (3 files, 812 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Supporter.php` | 349 | class Supporter | `Database`@[app] | `reports_status`, `supporter_scopes`, `supporters`, `users` |
| `SupporterController.php` | 259 | class SupporterController | `ApiException`@[app], `Validator`@[app], `PhoneNormalizer`@Bot | - |
| `SupporterService.php` | 204 | class SupporterService | `ApiException`@[app], `Cache`@[app], `Database`@[app], `Pagination`@[app], `PhoneNormalizer`@Bot, `Supporter` | `users` |

### Threads

- فایل: 7 | کل خط: 1,218 | کد: 883 | کامنت: 163 | خالی: 172
- به تفکیک پسوند: `.php`=1,218
- namespaceها: `App\Modules\Threads`
- وابسته به ماژول: **[app]**, **Bot**, **Outbox**, **Students**
- مورد استفاده توسط: **Broadcasts**, **Stats**, **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Threads/  (7 files, 1,218 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Message.php` | 317 | class Message | `Database`@[app] | `report_messages`, `report_threads`, `students` |
| `MessageAttachment.php` | 69 | class MessageAttachment | `Database`@[app] | `report_message_attachments` |
| `MessageContent.php` | 75 | class MessageContent | `ApiException`@[app], `Logger`@[app], `MessageAttachment` | - |
| `SupporterThreadController.php` | 63 | class SupporterThreadController | `ApiException`@[app], `Logger`@[app], `ResponseHelper`@[app] | - |
| `Thread.php` | 119 | class Thread | `Database`@[app], `Logger`@[app] | `report_threads` |
| `ThreadController.php` | 82 | class ThreadController | `ApiException`@[app], `ResponseHelper`@[app] | - |
| `ThreadService.php` | 493 | class ThreadService | `ApiException`@[app], `Logger`@[app], `Pagination`@[app], `IranDay`@Bot, `OutboxService`@Outbox, `Student`@Students, `Message`, `MessageAttachment`, `MessageContent`, `Thread` | - |

### Topics

- فایل: 3 | کل خط: 177 | کد: 149 | کامنت: 0 | خالی: 28
- به تفکیک پسوند: `.php`=177
- namespaceها: `App\Modules\Topics`
- وابسته به ماژول: **[app]**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Topics/  (3 files, 177 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Topic.php` | 70 | class Topic | `Database`@[app] | `events`, `topic_tree` |
| `TopicController.php` | 72 | class TopicController | - | - |
| `TopicService.php` | 35 | class TopicService | `Cache`@[app], `Topic` | - |

### Tutoring

- فایل: 3 | کل خط: 753 | کد: 509 | کامنت: 139 | خالی: 105
- به تفکیک پسوند: `.php`=753
- namespaceها: `App\Modules\Tutoring`
- وابسته به ماژول: **[app]**, **Bot**, **Auth**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `PDO`, `DateTimeZone`, `DateTimeImmutable`

```
Tutoring/  (3 files, 753 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Tutoring.php` | 451 | class Tutoring | `ApiException`@[app], `Database`@[app], `IranDay`@Bot | `daily_attendance`, `instructors`, `students`, `tutoring_classrooms`, `tutoring_sessions`, `tutoring_teacher_status_log` |
| `TutoringController.php` | 140 | class TutoringController | `ApiException`@[app], `Validator`@[app], `TutoringService` | - |
| `TutoringService.php` | 162 | class TutoringService | `ApiException`@[app], `User`@Auth, `IranDay`@Bot, `Tutoring` | - |

### WeeklyPlans

- فایل: 4 | کل خط: 831 | کد: 670 | کامنت: 66 | خالی: 95
- به تفکیک پسوند: `.php`=831
- namespaceها: `App\Modules\WeeklyPlans`
- وابسته به ماژول: **[app]**, **Students**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
WeeklyPlans/  (4 files, 831 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `PlanController.php` | 141 | class PlanController | `ApiException`@[app] | - |
| `PlanService.php` | 217 | class PlanService | `ApiException`@[app], `Database`@[app], `Student`@Students, `PlanTemplate`, `WeeklyPlan` | `students` |
| `PlanTemplate.php` | 154 | class PlanTemplate | `ApiException`@[app], `Database`@[app], `WeeklyPlan` | `plan_templates` |
| `WeeklyPlan.php` | 319 | class WeeklyPlan | `ApiException`@[app], `Database`@[app] | `event_topics`, `events`, `students`, `weekly_plans` |

### [app]

- فایل: 23 | کل خط: 1,876 | کد: 1,515 | کامنت: 64 | خالی: 297
- به تفکیک پسوند: `.php`=1,876
- namespaceها: `App\Core`, `App\Helpers`
- مورد استفاده توسط: **Admins**, **Appointments**, **Assignments**, **Attendance**, **Auth**, **Blog**, **Bot**, **Broadcasts**, **Courses**, **Dashboard**, **Exams**, **Files**, **Instructors**, **Linking**, **Outbox**, **ParentContacts**, **Public**, **Reports**, **Stats**, **Students**, **Subscription**, **Supporters**, **Threads**, **Topics**, **Tutoring**, **WeeklyPlans**, **[bootstrap]**, **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `Slim\Psr7`, `Throwable`, `Firebase\JWT`, `RuntimeException`, `Monolog\Formatter`, `Monolog\Handler`, `Monolog\Logger`, `Monolog\Level`, `finfo`

```
[app]/  (23 files, 1,876 lines)
  Core/  (22 files, 1,792 lines)
  Helpers/  (1 files, 84 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Core/ApiException.php` | 28 | class ApiException ← RuntimeException | - | - |
| `Core/Auth.php` | 51 | class Auth | - | - |
| `Core/AuthCookie.php` | 144 | class AuthCookie | - | - |
| `Core/Authorization.php` | 75 | class Authorization | - | - |
| `Core/Cache.php` | 117 | class Cache | - | - |
| `Core/ClientIp.php` | 71 | class ClientIp | - | - |
| `Core/CorsMiddleware.php` | 61 | class CorsMiddleware | `AuthCookie` | - |
| `Core/Database.php` | 41 | class Database | - | - |
| `Core/EmailService.php` | 131 | class EmailService | - | - |
| `Core/ExceptionHandler.php` | 99 | class ExceptionHandler | `ApiException`, `RateLimitedException` | - |
| `Core/Logger.php` | 92 | class Logger | - | - |
| `Core/Migrator.php` | 160 | class Migrator | `Database` | `migrations` |
| `Core/MultipartFormDataParser.php` | 136 | class MultipartFormDataParser | `ApiException` | - |
| `Core/Pagination.php` | 32 | class Pagination | - | - |
| `Core/PublicCacheMiddleware.php` | 35 | class PublicCacheMiddleware | - | - |
| `Core/RateLimitedException.php` | 17 | class RateLimitedException ← ApiException | `ApiException` | - |
| `Core/RateLimiter.php` | 68 | class RateLimiter | `Database` | `auth_throttle` |
| `Core/ResponseHelper.php` | 40 | class ResponseHelper | - | - |
| `Core/SmsService.php` | 133 | class SmsService | - | - |
| `Core/Storage.php` | 114 | class Storage | `ApiException` | - |
| `Core/StudentScope.php` | 68 | class StudentScope | `ApiException` | - |
| `Core/Validator.php` | 79 | class Validator | - | - |
| `Helpers/JalaliHelper.php` | 84 | class JalaliHelper | - | - |

### [bootstrap]

- فایل: 1 | کل خط: 122 | کد: 100 | کامنت: 2 | خالی: 20
- به تفکیک پسوند: `.php`=122
- وابسته به ماژول: **[app]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Slim\Exception`, `Slim\Factory`, `Slim\Psr7`, `Psr\Http`

```
[bootstrap]/  (1 files, 122 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `app.php` | 122 | - ⟨include: __DIR__ . '/../routes/api.php'⟩ | `Cache`@[app], `CorsMiddleware`@[app], `ExceptionHandler`@[app], `MultipartFormDataParser`@[app] | - |

### [config]

- فایل: 2 | کل خط: 32 | کد: 30 | کامنت: 0 | خالی: 2
- به تفکیک پسوند: `.php`=32

```
[config]/  (2 files, 32 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `app.php` | 21 | - | - | - |
| `database.php` | 11 | - | - | - |

### [database]

- فایل: 16 | کل خط: 606 | کد: 606 | کامنت: 0 | خالی: 0
- به تفکیک پسوند: `.sql`=606

```
[database]/  (16 files, 606 lines)
  migrations/  (16 files, 606 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `migrations/000_AUTO_INCREMENT.sql` | 5 | - | - | - |
| `migrations/001_fix_schema.sql` | 42 | - | - | - |
| `migrations/002_fix_weekly_plans.sql` | 22 | - | - | - |
| `migrations/003_add_2fa.sql` | 14 | - | - | - |
| `migrations/004_fix_exam_percentage.sql` | 11 | - | - | - |
| `migrations/005_planner_backend.sql` | 61 | - | - | - |
| `migrations/006_preserve_national_id.sql` | 2 | - | - | - |
| `migrations/007_parent_contacts_indexes.sql` | 49 | - | - | - |
| `migrations/008_bot_identity_and_assignments.sql` | 91 | - | - | - |
| `migrations/009_threads_and_messages.sql` | 58 | - | - | - |
| `migrations/010_bot_outbox.sql` | 34 | - | - | - |
| `migrations/011_broadcasts.sql` | 45 | - | - | - |
| `migrations/012_daily_attendance_tutoring.sql` | 92 | - | - | - |
| `migrations/013_telegram_login_auth.sql` | 39 | - | - | - |
| `migrations/014_admin_bot_and_supporter_scopes.sql` | 23 | - | - | - |
| `migrations/015_subscription.sql` | 18 | - | - | - |

### [public]

- فایل: 1 | کل خط: 7 | کد: 4 | کامنت: 0 | خالی: 3
- به تفکیک پسوند: `.php`=7

```
[public]/  (1 files, 7 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `index.php` | 7 | - ⟨include: __DIR__ . '/../vendor/autoload.php' | __DIR__ . '/../bootstrap/app.php'⟩ | - | - |

### [root]

- فایل: 2 | کل خط: 516 | کد: 516 | کامنت: 0 | خالی: 0
- به تفکیک پسوند: `.py`=486, `.json`=30

```
[root]/  (2 files, 516 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `composer.json` | 30 | - | - | - |
| `project_index.py` | 486 | - | - | - |

### [routes]

- فایل: 1 | کل خط: 325 | کد: 255 | کامنت: 37 | خالی: 33
- به تفکیک پسوند: `.php`=325
- وابسته به ماژول: **Blog**, **WeeklyPlans**, **Broadcasts**, **Public**, **Stats**, **Outbox**, **Reports**, **Courses**, **Topics**, **Linking**, **Supporters**, **Appointments**, **Attendance**, **Bot**, **Auth**, **Threads**, **Students**, **Instructors**, **Tutoring**, **ParentContacts**, **Exams**, **Assignments**, **Dashboard**, **[app]**, **Files**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `Slim\App`, `Slim\Exception`

```
[routes]/  (1 files, 325 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `api.php` | 325 | - | `Authorization`@[app], `PublicCacheMiddleware`@[app], `AppointmentController`@Appointments, `AppointmentService`@Appointments, `AssignmentController`@Assignments, `AssignmentService`@Assignments, `AttendanceController`@Attendance, `AttendanceService`@Attendance, `AuthController`@Auth, `AuthMiddleware`@Auth, `AuthService`@Auth, `TelegramAuthController`@Auth, `TelegramAuthService`@Auth, `BlogController`@Blog, `BotAuth`@Bot, `BotController`@Bot, `BotServiceMiddleware`@Bot, `BroadcastController`@Broadcasts, `BroadcastService`@Broadcasts, `CourseController`@Courses, `CourseService`@Courses, `DashboardController`@Dashboard, `ExamController`@Exams, `ExamService`@Exams, `FileController`@Files, `FileService`@Files, `InstructorController`@Instructors, `InstructorService`@Instructors, `LinkController`@Linking, `LinkService`@Linking, `OutboxController`@Outbox, `OutboxService`@Outbox, `ParentContactController`@ParentContacts, `ParentContactService`@ParentContacts, `PublicController`@Public, `ReportController`@Reports, `ReportService`@Reports, `ContentAccessMiddleware`@Stats, `StatsController`@Stats, `StatsService`@Stats, `StudentController`@Students, `StudentService`@Students, `SupporterController`@Supporters, `SupporterService`@Supporters, `SupporterThreadController`@Threads, `ThreadController`@Threads, `ThreadService`@Threads, `TopicController`@Topics, `TopicService`@Topics, `TutoringController`@Tutoring, `TutoringService`@Tutoring, `PlanController`@WeeklyPlans, `PlanService`@WeeklyPlans | - |

### [tests]

- فایل: 34 | کل خط: 6,155 | کد: 4,994 | کامنت: 124 | خالی: 1,037
- به تفکیک پسوند: `.php`=6,155
- namespaceها: `Tests`, `Tests\Core`, `Tests\Modules\Assignments`, `Tests\Modules\Attendance`, `Tests\Modules\Auth`, `Tests\Modules\Bot`, `Tests\Modules\Broadcasts`, `Tests\Modules\Outbox`, `Tests\Modules\Stats`, `Tests\Modules\Students`, `Tests\Modules\Supporters`, `Tests\Modules\Threads`, `Tests\Modules\Tutoring`
- وابسته به ماژول: **[app]**, **Auth**, **Blog**, **Exams**, **Courses**, **Supporters**, **Instructors**, **Assignments**, **Bot**, **Outbox**, **Threads**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `Slim\Psr7`, `PHPUnit\Framework`, `PDO`, `App\Modules`, `DateTimeImmutable`

```
[tests]/  (34 files, 6,155 lines)
  Core/  (12 files, 866 lines)
  Modules/  (19 files, 4,855 lines)
    Assignments/  (3 files, 569 lines)
    Attendance/  (1 files, 339 lines)
    Auth/  (5 files, 1,545 lines)
    Bot/  (3 files, 282 lines)
    Broadcasts/  (1 files, 268 lines)
    Outbox/  (1 files, 285 lines)
    Stats/  (1 files, 222 lines)
    Students/  (1 files, 482 lines)
    Supporters/  (1 files, 65 lines)
    Threads/  (1 files, 256 lines)
    Tutoring/  (1 files, 542 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Core/ApiExceptionTest.php` | 49 | class ApiExceptionTest ← TestCase | `ApiException`@[app], `ExceptionHandler`@[app] | - |
| `Core/BlogPostTest.php` | 71 | class BlogPostTest ← TestCase | `Database`@[app], `BlogPost`@Blog | `blog_categories`, `blog_posts` |
| `Core/CacheTest.php` | 137 | class CacheTest ← TestCase | `Cache`@[app] | - |
| `Core/ClientIpTest.php` | 90 | class ClientIpTest ← TestCase | `ClientIp`@[app], `TestCase` | - |
| `Core/CorsMiddlewareTest.php` | 108 | class CorsMiddlewareTest ← TestCase | `AuthCookie`@[app], `CorsMiddleware`@[app] | - |
| `Core/ExamServiceTest.php` | 79 | class ExamServiceTest ← TestCase | `ApiException`@[app], `Database`@[app], `ExamService`@Exams | `exam_results` |
| `Core/MigratorTest.php` | 33 | class MigratorTest ← TestCase | `Migrator`@[app] | `notes` |
| `Core/MultipartFormDataParserTest.php` | 75 | class MultipartFormDataParserTest ← TestCase | `ApiException`@[app], `MultipartFormDataParser`@[app] | - |
| `Core/OpenCorsOriginsTest.php` | 34 | class OpenCorsOriginsTest ← TestCase | `AuthCookie`@[app], `CorsMiddleware`@[app] | - |
| `Core/PaginationTest.php` | 71 | class PaginationTest ← TestCase | `Database`@[app], `Pagination`@[app], `CourseService`@Courses, `InstructorService`@Instructors, `SupporterService`@Supporters | `courses`, `instructors`, `supporters` |
| `Core/RateLimiterTest.php` | 39 | class RateLimiterTest ← TestCase | `RateLimiter`@[app], `TestCase` | `auth_throttle` |
| `Core/StorageTest.php` | 80 | class StorageTest ← TestCase | `ApiException`@[app], `Storage`@[app] | - |
| `Modules/Assignments/AssignmentTest.php` | 153 | class AssignmentTest ← TestCase | `TestCase` | `student_supporter_assignments`, `students`, `supporters`, `users` |
| `Modules/Assignments/AutoAssignmentLinkTest.php` | 220 | class AutoAssignmentLinkTest ← TestCase | `Supporter`@Supporters, `TestCase` | `auth_throttle`, `student_supporter_assignments`, `students`, `supporter_scopes`, `supporters`, `telegram_2fa_challenges`, `telegram_auth_nonces`, `telegram_links`, `users` |
| `Modules/Assignments/SupporterScopeTest.php` | 196 | class SupporterScopeTest ← TestCase | `Assignment`@Assignments, `AutoAssignmentService`@Assignments, `Supporter`@Supporters, `TestCase` | `student_supporter_assignments`, `students`, `supporter_scopes`, `supporters`, `users` |
| `Modules/Attendance/AttendanceTest.php` | 339 | class AttendanceTest ← TestCase | `IranDay`@Bot, `TestCase` | `daily_attendance`, `students`, `users` |
| `Modules/Auth/AuthTest.php` | 345 | class AuthTest ← TestCase | `TestCase` | `login_codes`, `students`, `users` |
| `Modules/Auth/AuthThrottleTest.php` | 220 | class AuthThrottleTest ← TestCase | `RateLimiter`@[app], `TestCase` | `auth_throttle`, `login_codes`, `users` |
| `Modules/Auth/TeacherAuthTest.php` | 152 | class TeacherAuthTest ← TestCase ⟨include: 2FA'⟩ | `Auth`@[app], `Authorization`@[app], `SmsService`@[app], `AuthService`@Auth, `TestCase` | `instructors`, `login_codes`, `users` |
| `Modules/Auth/TelegramAdminLinkTest.php` | 180 | class TelegramAdminLinkTest ← TestCase | `ApiException`@[app], `TelegramLink`@Bot, `TestCase` | `admins`, `auth_throttle`, `login_codes`, `telegram_2fa_challenges`, `telegram_auth_nonces`, `telegram_links`, `users` |
| `Modules/Auth/TelegramAuthTest.php` | 648 | class TelegramAuthTest ← TestCase | `ApiException`@[app], `Auth`@[app], `TelegramLoginVerifier`@Auth, `TelegramTicket`@Auth, `TelegramLink`@Bot, `TestCase` | `auth_throttle`, `login_codes`, `students`, `supporters`, `telegram_2fa_challenges`, `telegram_auth_nonces`, `telegram_links`, `users` |
| `Modules/Bot/BotGatewayTest.php` | 170 | class BotGatewayTest ← TestCase | `TestCase` | `student_supporter_assignments`, `students`, `supporters`, `telegram_links`, `users` |
| `Modules/Bot/IranDayTest.php` | 53 | class IranDayTest ← TestCase | `IranDay`@Bot | - |
| `Modules/Bot/PhoneNormalizerTest.php` | 59 | class PhoneNormalizerTest ← TestCase | `PhoneNormalizer`@Bot | - |
| `Modules/Broadcasts/BroadcastTest.php` | 268 | class BroadcastTest ← TestCase | `Assignment`@Assignments, `IranDay`@Bot, `TelegramLink`@Bot, `TestCase` | `bot_outbox`, `report_broadcast_recipients`, `report_broadcasts`, `report_messages`, `report_threads`, `student_supporter_assignments`, `students`, `supporters`, `telegram_links`, `users` |
| `Modules/Outbox/OutboxTest.php` | 285 | class OutboxTest ← TestCase | `Assignment`@Assignments, `TelegramLink`@Bot, `OutboxService`@Outbox, `TestCase` | `bot_outbox`, `student_supporter_assignments`, `students`, `supporters`, `telegram_links`, `users` |
| `Modules/Stats/StatsTest.php` | 222 | class StatsTest ← TestCase | `Assignment`@Assignments, `IranDay`@Bot, `Thread`@Threads, `TestCase` | `report_messages`, `student_supporter_assignments`, `students`, `supporters`, `users` |
| `Modules/Students/StudentTest.php` | 482 | class StudentTest ← TestCase | `Auth`@[app], `TestCase` | `students`, `users` |
| `Modules/Supporters/SupporterContactTest.php` | 65 | class SupporterContactTest ← TestCase | `TestCase` | `student_supporter_assignments`, `supporters` |
| `Modules/Threads/ThreadTest.php` | 256 | class ThreadTest ← TestCase | `Assignment`@Assignments, `IranDay`@Bot, `TelegramLink`@Bot, `Message`@Threads, `Thread`@Threads, `TestCase` | `student_supporter_assignments`, `students`, `supporters`, `telegram_links`, `users` |
| `Modules/Tutoring/TutoringTest.php` | 542 | class TutoringTest ← TestCase ⟨include: the seeded classroom'⟩ | `Auth`@[app], `IranDay`@Bot, `TestCase` | `daily_attendance`, `instructors`, `students`, `tutoring_classrooms`, `tutoring_sessions`, `tutoring_teacher_status_log`, `users` |
| `TestCase.php` | 269 | class TestCase ← TestCase | `Auth`@[app], `Database`@[app] | `students`, `users` |
| `auth_cookie_test.php` | 139 | - ⟨include: __DIR__ . '/../vendor/autoload.php' | __DIR__ . '/../app/Core/AuthCookie.php'⟩ | `Auth`@[app], `AuthCookie`@[app], `AuthController`@Auth, `AuthMiddleware`@Auth, `AuthService`@Auth | - |
| `bootstrap.php` | 26 | - ⟨include: __DIR__ . '/../vendor/autoload.php' | __DIR__ . '/../app/Core/Database.php' | __DIR__ . '/../bootstrap/app.php'⟩ | `Database`@[app] | - |

---

**محدودیت‌ها:** تحلیل با regex انجام شده (نه parser کامل PHP). جدول‌هایی که به‌صورت داینامیک ساخته میشن (مثل `{$this->table}`) یا داخل heredoc هستن شناسایی نمیشن. مواردی با ⚠️ حدسی‌ان و باید چک بشن.
