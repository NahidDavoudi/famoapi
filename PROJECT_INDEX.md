# راهنمای پروژه (ایندکس خودکار)

- مسیر: `/opt/apps/famo/api`
- پوشه ماژول‌ها: `app/Modules`
- تعداد فایل: **170** | مجموع خط: **22,917** | ماژول: **26**
- بخش‌هایی که داخل `[ ]` هستن (مثل `[core]`) بیرون از پوشه ماژول‌ها هستن.

## ۱. خلاصه ماژول‌ها

| ماژول | فایل | کل خط | خط کد | کامنت | کلاس | مدل | جدول‌ها | وابسته به | مورد استفاده در |
|---|---:|---:|---:|---:|---:|---:|---:|---|---|
| **Admins** | 1 | 30 | 20 | 5 | 1 | 1 | 1 | [app/Core](1) | Linking(1) |
| **Appointments** | 3 | 323 | 265 | 17 | 3 | 1 | 2 | [app/Core](5) | [routes](2) |
| **Assignments** | 4 | 778 | 553 | 114 | 4 | 1 | 3 | [app/Core](7), Supporters(2), Students(2) | [tests](6), [routes](2), Linking(1) |
| **Attendance** | 3 | 564 | 406 | 74 | 3 | 1 | 2 | [app/Core](5), Bot(2) | [routes](2) |
| **Auth** | 8 | 1,414 | 1,148 | 43 | 8 | 2 | 5 | [app/Core](31), Bot(1), Linking(1) | [tests](6), [routes](5), Bot(1), Tutoring(1) |
| **Blog** | 3 | 589 | 492 | 19 | 3 | 1 | 2 | [app/Core](6) | [routes](1), [tests](1) |
| **Bot** | 6 | 787 | 559 | 109 | 6 | 1 | 3 | [app/Core](6), Students(2), Auth(1) | [tests](12), [routes](3), Attendance(2), Linking(2), Stats(2), Supporters(2), Tutoring(2), Auth(1), Broadcasts(1), Outbox(1), Threads(1) |
| **Broadcasts** | 4 | 525 | 396 | 52 | 4 | 2 | 3 | [app/Core](6), Threads(4), Outbox(1), Bot(1), Students(1) | [routes](2), Outbox(1) |
| **Courses** | 3 | 375 | 322 | 1 | 3 | 1 | 1 | [app/Core](7) | [routes](2), [tests](1) |
| **Dashboard** | 1 | 51 | 34 | 7 | 1 | 0 | 3 | [app/Core](1) | [routes](1) |
| **Exams** | 3 | 398 | 329 | 23 | 3 | 1 | 3 | [app/Core](5) | [routes](2), [tests](1) |
| **Files** | 3 | 338 | 283 | 14 | 3 | 1 | 2 | [app/Core](7) | [routes](2) |
| **Instructors** | 3 | 366 | 312 | 1 | 3 | 1 | 1 | [app/Core](7) | [routes](2), [tests](1) |
| **Linking** | 2 | 365 | 292 | 16 | 2 | 0 | 0 | [app/Core](2), Bot(2), Assignments(1), Admins(1), Supporters(1), Students(1) | [routes](2), Auth(1) |
| **Outbox** | 3 | 548 | 408 | 80 | 3 | 1 | 1 | [app/Core](4), Broadcasts(1), Bot(1), Students(1) | [routes](2), Broadcasts(1), Threads(1), [tests](1) |
| **ParentContacts** | 3 | 345 | 303 | 4 | 3 | 1 | 2 | [app/Core](4) | [routes](2) |
| **Public** | 5 | 190 | 165 | 0 | 5 | 3 | 5 | [app/Core](4) | [routes](1) |
| **Reports** | 3 | 127 | 92 | 13 | 3 | 1 | 3 | [app/Core](2) | [routes](2) |
| **Stats** | 4 | 799 | 549 | 140 | 4 | 1 | 4 | [app/Core](5), Threads(3), Bot(2), Students(1) | [routes](3) |
| **Students** | 3 | 1,013 | 821 | 73 | 3 | 1 | 6 | [app/Core](7), Supporters(1) | Assignments(2), Bot(2), [routes](2), Broadcasts(1), Linking(1), Outbox(1), Stats(1), Threads(1), WeeklyPlans(1) |
| **Subscription** | 3 | 48 | 36 | 6 | 2 | 1 | 1 | [app/Core](1) | - |
| **Supporters** | 3 | 812 | 628 | 71 | 3 | 1 | 4 | [app/Core](7), Bot(2) | [tests](3), Assignments(2), [routes](2), Linking(1), Students(1) |
| **Threads** | 7 | 1,218 | 883 | 163 | 7 | 3 | 4 | [app/Core](14), Outbox(1), Bot(1), Students(1) | Broadcasts(4), Stats(3), [routes](3), [tests](3) |
| **Topics** | 3 | 177 | 149 | 0 | 3 | 1 | 2 | [app/Core](2) | [routes](2) |
| **Tutoring** | 3 | 753 | 509 | 139 | 3 | 1 | 6 | [app/Core](5), Bot(2), Auth(1) | [routes](2) |
| **WeeklyPlans** | 4 | 831 | 670 | 66 | 4 | 2 | 5 | [app/Core](7), Students(1) | [routes](2) |
| **[app/Core]** | 22 | 1,792 | 1,450 | 64 | 22 | 0 | 2 | - | [tests](33), Auth(31), Threads(14), Assignments(7), Courses(7), Files(7), Instructors(7), Students(7), Supporters(7), WeeklyPlans(7), Blog(6), Bot(6), Broadcasts(6), Appointments(5), Attendance(5), Exams(5), Stats(5), Tutoring(5), Outbox(4), ParentContacts(4), Public(4), [bootstrap](4), Linking(2), Reports(2), Topics(2), [routes](2), Admins(1), Dashboard(1), Subscription(1) |
| **[app/Helpers]** | 1 | 84 | 65 | 0 | 1 | 0 | 0 | - | - |
| **[bootstrap]** | 1 | 122 | 100 | 2 | 0 | 0 | 0 | [app/Core](4) | - |
| **[config]** | 2 | 32 | 30 | 0 | 0 | 0 | 0 | - | - |
| **[database]** | 16 | 606 | 606 | 0 | 0 | 0 | 0 | - | - |
| **[public]** | 1 | 7 | 4 | 0 | 0 | 0 | 0 | - | - |
| **[root]** | 1 | 30 | 30 | 0 | 0 | 0 | 0 | - | - |
| **[routes]** | 1 | 325 | 255 | 37 | 0 | 0 | 0 | Auth(5), Bot(3), Threads(3), Stats(3), Assignments(2), Tutoring(2), Supporters(2), Broadcasts(2), Topics(2), Exams(2), Files(2), Attendance(2), Outbox(2), Instructors(2), WeeklyPlans(2), Appointments(2), ParentContacts(2), [app/Core](2), Students(2), Courses(2), Linking(2), Reports(2), Public(1), Dashboard(1), Blog(1) | - |
| **[tests]** | 34 | 6,155 | 4,994 | 124 | 32 | 0 | 26 | [app/Core](33), Bot(12), Auth(6), Assignments(6), Supporters(3), Threads(3), Blog(1), Exams(1), Courses(1), Instructors(1), Outbox(1) | - |

> عدد داخل پرانتز = تعداد وابستگی‌های فایل‌به‌کلاس بین دو ماژول.

## ۲. نگاشت مدل ← جدول

| ماژول | مدل (کلاس) | فایل | جدول اصلی | منبع | جدول‌های دیگر در همین فایل |
|---|---|---|---|---|---|
| Admins | `App\Modules\Admins\Admin` | `app/Modules/Admins/Admin.php` | `users` | از کوئری‌ها | - |
| Appointments | `App\Modules\Appointments\Appointment` | `app/Modules/Appointments/Appointment.php` | `appointments` | از کوئری‌ها | `students` |
| Assignments | `App\Modules\Assignments\Assignment` | `app/Modules/Assignments/Assignment.php` | `student_supporter_assignments` | از کوئری‌ها | `students`, `supporters` |
| Attendance | `App\Modules\Attendance\Attendance` | `app/Modules/Attendance/Attendance.php` | `daily_attendance` | از کوئری‌ها | `students` |
| Auth | `App\Modules\Auth\TelegramTicket` | `app/Modules/Auth/TelegramTicket.php` | `telegram_auth_nonces` | از کوئری‌ها | - |
| Auth | `App\Modules\Auth\User` | `app/Modules/Auth/User.php` | `users` | از کوئری‌ها | - |
| Blog | `App\Modules\Blog\BlogPost` | `app/Modules/Blog/BlogPost.php` | `blog_posts` | از کوئری‌ها | `blog_category` |
| Bot | `App\Modules\Bot\TelegramLink` | `app/Modules/Bot/TelegramLink.php` | `telegram_links` | از کوئری‌ها | `students`, `users` |
| Broadcasts | `App\Modules\Broadcasts\Broadcast` | `app/Modules/Broadcasts/Broadcast.php` | `report_broadcasts` | از کوئری‌ها | `report_broadcast_recipients` |
| Broadcasts | `App\Modules\Broadcasts\BroadcastRecipient` | `app/Modules/Broadcasts/BroadcastRecipient.php` | `report_broadcast_recipients` | از کوئری‌ها | `students` |
| Courses | `App\Modules\Courses\Course` | `app/Modules/Courses/Course.php` | `courses` | از کوئری‌ها | - |
| Exams | `App\Modules\Exams\ExamResult` | `app/Modules/Exams/ExamResult.php` | `exam_results` | از کوئری‌ها | `files`, `students` |
| Files | `App\Modules\Files\File` | `app/Modules/Files/File.php` | `files` | از کوئری‌ها | `students` |
| Instructors | `App\Modules\Instructors\Instructor` | `app/Modules/Instructors/Instructor.php` | `instructors` | از کوئری‌ها | - |
| Outbox | `App\Modules\Outbox\OutboxItem` | `app/Modules/Outbox/OutboxItem.php` | `bot_outbox` | از کوئری‌ها | - |
| ParentContacts | `App\Modules\ParentContacts\ParentContact` | `app/Modules/ParentContacts/ParentContact.php` | `parent_contacts` | از کوئری‌ها | - |
| Public | `App\Modules\Public\Course` | `app/Modules/Public/Course.php` | `courses` | از کوئری‌ها | `course_features` |
| Public | `App\Modules\Public\Instructor` | `app/Modules/Public/Instructor.php` | `instructors` | از کوئری‌ها | `instructor_social_links` |
| Public | `App\Modules\Public\Supporter` | `app/Modules/Public/Supporter.php` | `supporters` | از کوئری‌ها | - |
| Reports | `App\Modules\Reports\Report` | `app/Modules/Reports/Report.php` | `reports_status` | از کوئری‌ها | `students`, `supporters` |
| Stats | `App\Modules\Stats\Stats` | `app/Modules/Stats/Stats.php` | `report_threads` | از کوئری‌ها | `report_messages`, `students`, `supporters` |
| Students | `App\Modules\Students\Student` | `app/Modules/Students/Student.php` | `students` | از کوئری‌ها | `events`, `supporters`, `users`, `weekly_plans` |
| Subscription | `App\Modules\Subscription\Subscription` | `app/Modules/Subscription/Subscription.php` | `subscriptions` | از کوئری‌ها | - |
| Supporters | `App\Modules\Supporters\Supporter` | `app/Modules/Supporters/Supporter.php` | `supporters` | از کوئری‌ها | `reports_status`, `supporter_scopes`, `users` |
| Threads | `App\Modules\Threads\Message` | `app/Modules/Threads/Message.php` | `report_messages` | از کوئری‌ها | `report_threads`, `students` |
| Threads | `App\Modules\Threads\MessageAttachment` | `app/Modules/Threads/MessageAttachment.php` | `report_message_attachments` | از کوئری‌ها | - |
| Threads | `App\Modules\Threads\Thread` | `app/Modules/Threads/Thread.php` | `report_threads` | از کوئری‌ها | - |
| Topics | `App\Modules\Topics\Topic` | `app/Modules/Topics/Topic.php` | `topic_tree` | از کوئری‌ها | `events` |
| Tutoring | `App\Modules\Tutoring\Tutoring` | `app/Modules/Tutoring/Tutoring.php` | `tutoring_teacher_status_log` | از کوئری‌ها | `daily_attendance`, `instructors`, `students`, `tutoring_classrooms`, `tutoring_sessions` |
| WeeklyPlans | `App\Modules\WeeklyPlans\PlanTemplate` | `app/Modules/WeeklyPlans/PlanTemplate.php` | `plan_templates` | از کوئری‌ها | - |
| WeeklyPlans | `App\Modules\WeeklyPlans\WeeklyPlan` | `app/Modules/WeeklyPlans/WeeklyPlan.php` | `weekly_plans` | از کوئری‌ها | `event_topics`, `events`, `students` |

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
- وابسته به ماژول: **[app/Core]**
- مورد استفاده توسط: **Linking**

```
Admins/  (1 files, 30 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Admin.php` | 30 | class Admin | `Database`@[app/Core] | `users` |

### Appointments

- فایل: 3 | کل خط: 323 | کد: 265 | کامنت: 17 | خالی: 41
- به تفکیک پسوند: `.php`=323
- namespaceها: `App\Modules\Appointments`
- وابسته به ماژول: **[app/Core]**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Appointments/  (3 files, 323 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Appointment.php` | 134 | class Appointment | `Database`@[app/Core] | `appointments`, `students` |
| `AppointmentController.php` | 119 | class AppointmentController | `ApiException`@[app/Core] | - |
| `AppointmentService.php` | 70 | class AppointmentService | `ApiException`@[app/Core], `Pagination`@[app/Core], `SmsService`@[app/Core], `Appointment` | - |

### Assignments

- فایل: 4 | کل خط: 778 | کد: 553 | کامنت: 114 | خالی: 111
- به تفکیک پسوند: `.php`=778
- namespaceها: `App\Modules\Assignments`
- وابسته به ماژول: **[app/Core]**, **Supporters**, **Students**
- مورد استفاده توسط: **Linking**, **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Assignments/  (4 files, 778 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Assignment.php` | 261 | class Assignment | `Database`@[app/Core] | `student_supporter_assignments`, `students`, `supporters` |
| `AssignmentController.php` | 100 | class AssignmentController | `ApiException`@[app/Core], `ResponseHelper`@[app/Core] | - |
| `AssignmentService.php` | 247 | class AssignmentService | `ApiException`@[app/Core], `Database`@[app/Core], `Pagination`@[app/Core], `Assignment`, `Student`@Students, `Supporter`@Supporters | - |
| `AutoAssignmentService.php` | 170 | class AutoAssignmentService | `Database`@[app/Core], `Assignment`, `Student`@Students, `Supporter`@Supporters | - |

### Attendance

- فایل: 3 | کل خط: 564 | کد: 406 | کامنت: 74 | خالی: 84
- به تفکیک پسوند: `.php`=564
- namespaceها: `App\Modules\Attendance`
- وابسته به ماژول: **Bot**, **[app/Core]**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `PDO`, `DateTimeImmutable`, `DateTimeZone`

```
Attendance/  (3 files, 564 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Attendance.php` | 279 | class Attendance | `ApiException`@[app/Core], `Database`@[app/Core], `IranDay`@Bot | `daily_attendance`, `students` |
| `AttendanceController.php` | 128 | class AttendanceController | `ApiException`@[app/Core], `Validator`@[app/Core] | - |
| `AttendanceService.php` | 157 | class AttendanceService | `ApiException`@[app/Core], `Attendance`, `IranDay`@Bot | - |

### Auth

- فایل: 8 | کل خط: 1,414 | کد: 1,148 | کامنت: 43 | خالی: 223
- به تفکیک پسوند: `.php`=1,414
- namespaceها: `App\Modules\Auth`
- وابسته به ماژول: **[app/Core]**, **Bot**, **Linking**
- مورد استفاده توسط: **Bot**, **Tutoring**, **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `Slim\Psr7`, `App\Modules`

```
Auth/  (8 files, 1,414 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `AuthController.php` | 312 | class AuthController | `ApiException`@[app/Core], `Auth`@[app/Core], `AuthCookie`@[app/Core], `ClientIp`@[app/Core], `RateLimitedException`@[app/Core], `RateLimiter`@[app/Core], `Validator`@[app/Core] | - |
| `AuthMiddleware.php` | 54 | class AuthMiddleware | `Auth`@[app/Core], `AuthCookie`@[app/Core] | - |
| `AuthService.php` | 278 | class AuthService | `ApiException`@[app/Core], `Auth`@[app/Core], `Database`@[app/Core], `RateLimitedException`@[app/Core], `RateLimiter`@[app/Core], `SmsService`@[app/Core], `User` | `login_codes`, `students`, `users` |
| `TelegramAuthController.php` | 139 | class TelegramAuthController | `AuthCookie`@[app/Core], `ClientIp`@[app/Core], `RateLimitedException`@[app/Core], `ResponseHelper`@[app/Core], `Validator`@[app/Core] | - |
| `TelegramAuthService.php` | 333 | class TelegramAuthService | `ApiException`@[app/Core], `Database`@[app/Core], `RateLimitedException`@[app/Core], `RateLimiter`@[app/Core], `SmsService`@[app/Core], `AuthService`, `TelegramLoginVerifier`, `TelegramTicket`, `User`, `TelegramLink`@Bot, `LinkService`@Linking | `login_codes`, `telegram_2fa_challenges` |
| `TelegramLoginVerifier.php` | 113 | class TelegramLoginVerifier | `ApiException`@[app/Core], `Database`@[app/Core] | `telegram_auth_nonces` |
| `TelegramTicket.php` | 96 | class TelegramTicket | `ApiException`@[app/Core], `Auth`@[app/Core], `Database`@[app/Core] | `telegram_auth_nonces` |
| `User.php` | 89 | class User | `Database`@[app/Core] | `users` |

### Blog

- فایل: 3 | کل خط: 589 | کد: 492 | کامنت: 19 | خالی: 78
- به تفکیک پسوند: `.php`=589
- namespaceها: `App\Modules\Blog`
- وابسته به ماژول: **[app/Core]**
- مورد استفاده توسط: **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Blog/  (3 files, 589 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `BlogController.php` | 202 | class BlogController | `ApiException`@[app/Core], `BlogService` | - |
| `BlogPost.php` | 248 | class BlogPost | `Database`@[app/Core], `Pagination`@[app/Core] | `blog_category`, `blog_posts` |
| `BlogService.php` | 139 | class BlogService | `ApiException`@[app/Core], `Cache`@[app/Core], `Pagination`@[app/Core], `BlogPost` | - |

### Bot

- فایل: 6 | کل خط: 787 | کد: 559 | کامنت: 109 | خالی: 119
- به تفکیک پسوند: `.php`=787
- namespaceها: `App\Modules\Bot`
- وابسته به ماژول: **[app/Core]**, **Students**, **Auth**
- مورد استفاده توسط: **Attendance**, **Auth**, **Broadcasts**, **Linking**, **Outbox**, **Stats**, **Supporters**, **Threads**, **Tutoring**, **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `Slim\Psr7`, `DateTimeImmutable`, `DateTimeZone`

```
Bot/  (6 files, 787 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `BotAuth.php` | 66 | class BotAuth | `ResponseHelper`@[app/Core], `TelegramLink` | - |
| `BotController.php` | 271 | class BotController | `ApiException`@[app/Core], `Logger`@[app/Core], `ResponseHelper`@[app/Core], `User`@Auth, `IranDay`, `TelegramLink`, `Student`@Students, `StudentService`@Students | - |
| `BotServiceMiddleware.php` | 65 | class BotServiceMiddleware | `ResponseHelper`@[app/Core] | - |
| `IranDay.php` | 235 | class IranDay | - | - |
| `PhoneNormalizer.php` | 79 | class PhoneNormalizer | - | - |
| `TelegramLink.php` | 71 | class TelegramLink | `Database`@[app/Core] | `students`, `telegram_links`, `users` |

### Broadcasts

- فایل: 4 | کل خط: 525 | کد: 396 | کامنت: 52 | خالی: 77
- به تفکیک پسوند: `.php`=525
- namespaceها: `App\Modules\Broadcasts`
- وابسته به ماژول: **[app/Core]**, **Threads**, **Outbox**, **Bot**, **Students**
- مورد استفاده توسط: **Outbox**, **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Broadcasts/  (4 files, 525 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Broadcast.php` | 94 | class Broadcast | `Database`@[app/Core] | `report_broadcast_recipients`, `report_broadcasts` |
| `BroadcastController.php` | 53 | class BroadcastController | `ResponseHelper`@[app/Core] | - |
| `BroadcastRecipient.php` | 101 | class BroadcastRecipient | `Database`@[app/Core] | `report_broadcast_recipients`, `students` |
| `BroadcastService.php` | 277 | class BroadcastService | `ApiException`@[app/Core], `Database`@[app/Core], `Pagination`@[app/Core], `IranDay`@Bot, `Broadcast`, `BroadcastRecipient`, `OutboxService`@Outbox, `Student`@Students, `Message`@Threads, `MessageAttachment`@Threads, `MessageContent`@Threads, `Thread`@Threads | - |

### Courses

- فایل: 3 | کل خط: 375 | کد: 322 | کامنت: 1 | خالی: 52
- به تفکیک پسوند: `.php`=375
- namespaceها: `App\Modules\Courses`
- وابسته به ماژول: **[app/Core]**
- مورد استفاده توسط: **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Courses/  (3 files, 375 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Course.php` | 87 | class Course | `Database`@[app/Core] | `courses` |
| `CourseController.php` | 183 | class CourseController | `ApiException`@[app/Core], `Validator`@[app/Core] | - |
| `CourseService.php` | 105 | class CourseService | `ApiException`@[app/Core], `Cache`@[app/Core], `Pagination`@[app/Core], `Storage`@[app/Core], `Course` | - |

### Dashboard

- فایل: 1 | کل خط: 51 | کد: 34 | کامنت: 7 | خالی: 10
- به تفکیک پسوند: `.php`=51
- namespaceها: `App\Modules\Dashboard`
- وابسته به ماژول: **[app/Core]**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Dashboard/  (1 files, 51 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `DashboardController.php` | 51 | class DashboardController | `Database`@[app/Core] | `exam_results`, `reports_status`, `students` |

### Exams

- فایل: 3 | کل خط: 398 | کد: 329 | کامنت: 23 | خالی: 46
- به تفکیک پسوند: `.php`=398
- namespaceها: `App\Modules\Exams`
- وابسته به ماژول: **[app/Core]**
- مورد استفاده توسط: **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Exams/  (3 files, 398 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `ExamController.php` | 129 | class ExamController | `ApiException`@[app/Core], `StudentScope`@[app/Core] | - |
| `ExamResult.php` | 190 | class ExamResult | `Database`@[app/Core] | `exam_results`, `files`, `students` |
| `ExamService.php` | 79 | class ExamService | `ApiException`@[app/Core], `Pagination`@[app/Core], `ExamResult` | - |

### Files

- فایل: 3 | کل خط: 338 | کد: 283 | کامنت: 14 | خالی: 41
- به تفکیک پسوند: `.php`=338
- namespaceها: `App\Modules\Files`
- وابسته به ماژول: **[app/Core]**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Files/  (3 files, 338 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `File.php` | 83 | class File | `Database`@[app/Core] | `files`, `students` |
| `FileController.php` | 158 | class FileController | `ApiException`@[app/Core], `ResponseHelper`@[app/Core], `StudentScope`@[app/Core] | - |
| `FileService.php` | 97 | class FileService | `ApiException`@[app/Core], `Pagination`@[app/Core], `Storage`@[app/Core], `File` | - |

### Instructors

- فایل: 3 | کل خط: 366 | کد: 312 | کامنت: 1 | خالی: 53
- به تفکیک پسوند: `.php`=366
- namespaceها: `App\Modules\Instructors`
- وابسته به ماژول: **[app/Core]**
- مورد استفاده توسط: **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Instructors/  (3 files, 366 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Instructor.php` | 78 | class Instructor | `Database`@[app/Core] | `instructors` |
| `InstructorController.php` | 177 | class InstructorController | `ApiException`@[app/Core], `Validator`@[app/Core] | - |
| `InstructorService.php` | 111 | class InstructorService | `ApiException`@[app/Core], `Cache`@[app/Core], `Pagination`@[app/Core], `Storage`@[app/Core], `Instructor` | - |

### Linking

- فایل: 2 | کل خط: 365 | کد: 292 | کامنت: 16 | خالی: 57
- به تفکیک پسوند: `.php`=365
- namespaceها: `App\Modules\Linking`
- وابسته به ماژول: **[app/Core]**, **Assignments**, **Admins**, **Bot**, **Supporters**, **Students**
- مورد استفاده توسط: **Auth**, **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `App\Modules`

```
Linking/  (2 files, 365 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `LinkController.php` | 75 | class LinkController | `ResponseHelper`@[app/Core] | - |
| `LinkService.php` | 290 | class LinkService | `ApiException`@[app/Core], `Admin`@Admins, `AutoAssignmentService`@Assignments, `PhoneNormalizer`@Bot, `TelegramLink`@Bot, `Student`@Students, `Supporter`@Supporters | - |

### Outbox

- فایل: 3 | کل خط: 548 | کد: 408 | کامنت: 80 | خالی: 60
- به تفکیک پسوند: `.php`=548
- namespaceها: `App\Modules\Outbox`
- وابسته به ماژول: **[app/Core]**, **Broadcasts**, **Bot**, **Students**
- مورد استفاده توسط: **Broadcasts**, **Threads**, **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Outbox/  (3 files, 548 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `OutboxController.php` | 44 | class OutboxController | `ApiException`@[app/Core], `ResponseHelper`@[app/Core] | - |
| `OutboxItem.php` | 199 | class OutboxItem | `Database`@[app/Core] | `bot_outbox` |
| `OutboxService.php` | 305 | class OutboxService | `Database`@[app/Core], `TelegramLink`@Bot, `BroadcastRecipient`@Broadcasts, `OutboxItem`, `Student`@Students | - |

### ParentContacts

- فایل: 3 | کل خط: 345 | کد: 303 | کامنت: 4 | خالی: 38
- به تفکیک پسوند: `.php`=345
- namespaceها: `App\Modules\ParentContacts`
- وابسته به ماژول: **[app/Core]**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
ParentContacts/  (3 files, 345 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `ParentContact.php` | 99 | class ParentContact | `Database`@[app/Core] | `parent_contacts` |
| `ParentContactController.php` | 159 | class ParentContactController | `ApiException`@[app/Core] | - |
| `ParentContactService.php` | 87 | class ParentContactService | `ApiException`@[app/Core], `Database`@[app/Core], `ParentContact` | `students` |

### Public

- فایل: 5 | کل خط: 190 | کد: 165 | کامنت: 0 | خالی: 25
- به تفکیک پسوند: `.php`=190
- namespaceها: `App\Modules\Public`
- وابسته به ماژول: **[app/Core]**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Public/  (5 files, 190 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Course.php` | 41 | class Course | `Database`@[app/Core] | `course_features`, `courses` |
| `Instructor.php` | 32 | class Instructor | `Database`@[app/Core] | `instructor_social_links`, `instructors` |
| `PublicController.php` | 52 | class PublicController | `PublicService` | - |
| `PublicService.php` | 47 | class PublicService | `Cache`@[app/Core], `Course`, `Instructor`, `Supporter` | - |
| `Supporter.php` | 18 | class Supporter | `Database`@[app/Core] | `supporters` |

### Reports

- فایل: 3 | کل خط: 127 | کد: 92 | کامنت: 13 | خالی: 22
- به تفکیک پسوند: `.php`=127
- namespaceها: `App\Modules\Reports`
- وابسته به ماژول: **[app/Core]**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Reports/  (3 files, 127 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Report.php` | 68 | class Report | `Database`@[app/Core] | `reports_status`, `students`, `supporters` |
| `ReportController.php` | 38 | class ReportController | - | - |
| `ReportService.php` | 21 | class ReportService | `Pagination`@[app/Core], `Report` | - |

### Stats

- فایل: 4 | کل خط: 799 | کد: 549 | کامنت: 140 | خالی: 110
- به تفکیک پسوند: `.php`=799
- namespaceها: `App\Modules\Stats`
- وابسته به ماژول: **[app/Core]**, **Bot**, **Threads**, **Students**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `Slim\Psr7`

```
Stats/  (4 files, 799 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `ContentAccessMiddleware.php` | 40 | class ContentAccessMiddleware | `ResponseHelper`@[app/Core] | - |
| `Stats.php` | 320 | class Stats | `Database`@[app/Core] | `report_messages`, `report_threads`, `students`, `supporters` |
| `StatsController.php` | 99 | class StatsController | `ResponseHelper`@[app/Core], `IranDay`@Bot | - |
| `StatsService.php` | 340 | class StatsService | `ApiException`@[app/Core], `Pagination`@[app/Core], `IranDay`@Bot, `Stats`, `Student`@Students, `Message`@Threads, `MessageAttachment`@Threads, `Thread`@Threads | - |

### Students

- فایل: 3 | کل خط: 1,013 | کد: 821 | کامنت: 73 | خالی: 119
- به تفکیک پسوند: `.php`=1,013
- namespaceها: `App\Modules\Students`
- وابسته به ماژول: **[app/Core]**, **Supporters**
- مورد استفاده توسط: **Assignments**, **Bot**, **Broadcasts**, **Linking**, **Outbox**, **Stats**, **Threads**, **WeeklyPlans**, **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Students/  (3 files, 1,013 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Student.php` | 277 | class Student | `Database`@[app/Core] | `events`, `students`, `supporters`, `users`, `weekly_plans` |
| `StudentController.php` | 404 | class StudentController | `ApiException`@[app/Core], `StudentScope`@[app/Core], `Validator`@[app/Core] | - |
| `StudentService.php` | 332 | class StudentService | `ApiException`@[app/Core], `Database`@[app/Core], `Pagination`@[app/Core], `Student`, `Supporter`@Supporters | `events`, `exam_results`, `students`, `users` |

### Subscription

- فایل: 3 | کل خط: 48 | کد: 36 | کامنت: 6 | خالی: 6
- به تفکیک پسوند: `.php`=48
- namespaceها: `App\Modules\Subscription`
- وابسته به ماژول: **[app/Core]**

```
Subscription/  (3 files, 48 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Subscription.php` | 36 | class Subscription | `Database`@[app/Core] | `subscriptions` |
| `SubscriptionModel.php` | 2 | - | - | - |
| `SubscriptionService.php` | 10 | class SubscriptionService | - | - |

### Supporters

- فایل: 3 | کل خط: 812 | کد: 628 | کامنت: 71 | خالی: 113
- به تفکیک پسوند: `.php`=812
- namespaceها: `App\Modules\Supporters`
- وابسته به ماژول: **[app/Core]**, **Bot**
- مورد استفاده توسط: **Assignments**, **Linking**, **Students**, **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Supporters/  (3 files, 812 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Supporter.php` | 349 | class Supporter | `Database`@[app/Core] | `reports_status`, `supporter_scopes`, `supporters`, `users` |
| `SupporterController.php` | 259 | class SupporterController | `ApiException`@[app/Core], `Validator`@[app/Core], `PhoneNormalizer`@Bot | - |
| `SupporterService.php` | 204 | class SupporterService | `ApiException`@[app/Core], `Cache`@[app/Core], `Database`@[app/Core], `Pagination`@[app/Core], `PhoneNormalizer`@Bot, `Supporter` | `users` |

### Threads

- فایل: 7 | کل خط: 1,218 | کد: 883 | کامنت: 163 | خالی: 172
- به تفکیک پسوند: `.php`=1,218
- namespaceها: `App\Modules\Threads`
- وابسته به ماژول: **[app/Core]**, **Outbox**, **Bot**, **Students**
- مورد استفاده توسط: **Broadcasts**, **Stats**, **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Threads/  (7 files, 1,218 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Message.php` | 317 | class Message | `Database`@[app/Core] | `report_messages`, `report_threads`, `students` |
| `MessageAttachment.php` | 69 | class MessageAttachment | `Database`@[app/Core] | `report_message_attachments` |
| `MessageContent.php` | 75 | class MessageContent | `ApiException`@[app/Core], `Logger`@[app/Core], `MessageAttachment` | - |
| `SupporterThreadController.php` | 63 | class SupporterThreadController | `ApiException`@[app/Core], `Logger`@[app/Core], `ResponseHelper`@[app/Core] | - |
| `Thread.php` | 119 | class Thread | `Database`@[app/Core], `Logger`@[app/Core] | `report_threads` |
| `ThreadController.php` | 82 | class ThreadController | `ApiException`@[app/Core], `ResponseHelper`@[app/Core] | - |
| `ThreadService.php` | 493 | class ThreadService | `ApiException`@[app/Core], `Logger`@[app/Core], `Pagination`@[app/Core], `IranDay`@Bot, `OutboxService`@Outbox, `Student`@Students, `Message`, `MessageAttachment`, `MessageContent`, `Thread` | - |

### Topics

- فایل: 3 | کل خط: 177 | کد: 149 | کامنت: 0 | خالی: 28
- به تفکیک پسوند: `.php`=177
- namespaceها: `App\Modules\Topics`
- وابسته به ماژول: **[app/Core]**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
Topics/  (3 files, 177 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Topic.php` | 70 | class Topic | `Database`@[app/Core] | `events`, `topic_tree` |
| `TopicController.php` | 72 | class TopicController | - | - |
| `TopicService.php` | 35 | class TopicService | `Cache`@[app/Core], `Topic` | - |

### Tutoring

- فایل: 3 | کل خط: 753 | کد: 509 | کامنت: 139 | خالی: 105
- به تفکیک پسوند: `.php`=753
- namespaceها: `App\Modules\Tutoring`
- وابسته به ماژول: **Bot**, **[app/Core]**, **Auth**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `PDO`, `DateTimeImmutable`, `DateTimeZone`

```
Tutoring/  (3 files, 753 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `Tutoring.php` | 451 | class Tutoring | `ApiException`@[app/Core], `Database`@[app/Core], `IranDay`@Bot | `daily_attendance`, `instructors`, `students`, `tutoring_classrooms`, `tutoring_sessions`, `tutoring_teacher_status_log` |
| `TutoringController.php` | 140 | class TutoringController | `ApiException`@[app/Core], `Validator`@[app/Core], `TutoringService` | - |
| `TutoringService.php` | 162 | class TutoringService | `ApiException`@[app/Core], `User`@Auth, `IranDay`@Bot, `Tutoring` | - |

### WeeklyPlans

- فایل: 4 | کل خط: 831 | کد: 670 | کامنت: 66 | خالی: 95
- به تفکیک پسوند: `.php`=831
- namespaceها: `App\Modules\WeeklyPlans`
- وابسته به ماژول: **[app/Core]**, **Students**
- مورد استفاده توسط: **[routes]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`

```
WeeklyPlans/  (4 files, 831 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `PlanController.php` | 141 | class PlanController | `ApiException`@[app/Core] | - |
| `PlanService.php` | 217 | class PlanService | `ApiException`@[app/Core], `Database`@[app/Core], `Student`@Students, `PlanTemplate`, `WeeklyPlan` | `students` |
| `PlanTemplate.php` | 154 | class PlanTemplate | `ApiException`@[app/Core], `Database`@[app/Core], `WeeklyPlan` | `plan_templates` |
| `WeeklyPlan.php` | 319 | class WeeklyPlan | `ApiException`@[app/Core], `Database`@[app/Core] | `event_topics`, `events`, `students`, `weekly_plans` |

### [app/Core]

- فایل: 22 | کل خط: 1,792 | کد: 1,450 | کامنت: 64 | خالی: 278
- به تفکیک پسوند: `.php`=1,792
- namespaceها: `App\Core`
- مورد استفاده توسط: **Admins**, **Appointments**, **Assignments**, **Attendance**, **Auth**, **Blog**, **Bot**, **Broadcasts**, **Courses**, **Dashboard**, **Exams**, **Files**, **Instructors**, **Linking**, **Outbox**, **ParentContacts**, **Public**, **Reports**, **Stats**, **Students**, **Subscription**, **Supporters**, **Threads**, **Topics**, **Tutoring**, **WeeklyPlans**, **[bootstrap]**, **[routes]**, **[tests]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `Slim\Psr7`, `Throwable`, `Firebase\JWT`, `RuntimeException`, `Monolog\Formatter`, `Monolog\Handler`, `Monolog\Logger`, `Monolog\Level`, `finfo`

```
[app/Core]/  (22 files, 1,792 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `ApiException.php` | 28 | class ApiException ← RuntimeException | - | - |
| `Auth.php` | 51 | class Auth | - | - |
| `AuthCookie.php` | 144 | class AuthCookie | - | - |
| `Authorization.php` | 75 | class Authorization | - | - |
| `Cache.php` | 117 | class Cache | - | - |
| `ClientIp.php` | 71 | class ClientIp | - | - |
| `CorsMiddleware.php` | 61 | class CorsMiddleware | `AuthCookie` | - |
| `Database.php` | 41 | class Database | - | - |
| `EmailService.php` | 131 | class EmailService | - | - |
| `ExceptionHandler.php` | 99 | class ExceptionHandler | `ApiException`, `RateLimitedException` | - |
| `Logger.php` | 92 | class Logger | - | - |
| `Migrator.php` | 160 | class Migrator | `Database` | `migrations` |
| `MultipartFormDataParser.php` | 136 | class MultipartFormDataParser | `ApiException` | - |
| `Pagination.php` | 32 | class Pagination | - | - |
| `PublicCacheMiddleware.php` | 35 | class PublicCacheMiddleware | - | - |
| `RateLimitedException.php` | 17 | class RateLimitedException ← ApiException | `ApiException` | - |
| `RateLimiter.php` | 68 | class RateLimiter | `Database` | `auth_throttle` |
| `ResponseHelper.php` | 40 | class ResponseHelper | - | - |
| `SmsService.php` | 133 | class SmsService | - | - |
| `Storage.php` | 114 | class Storage | `ApiException` | - |
| `StudentScope.php` | 68 | class StudentScope | `ApiException` | - |
| `Validator.php` | 79 | class Validator | - | - |

### [app/Helpers]

- فایل: 1 | کل خط: 84 | کد: 65 | کامنت: 0 | خالی: 19
- به تفکیک پسوند: `.php`=84
- namespaceها: `App\Helpers`

```
[app/Helpers]/  (1 files, 84 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `JalaliHelper.php` | 84 | class JalaliHelper | - | - |

### [bootstrap]

- فایل: 1 | کل خط: 122 | کد: 100 | کامنت: 2 | خالی: 20
- به تفکیک پسوند: `.php`=122
- وابسته به ماژول: **[app/Core]**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Slim\Exception`, `Slim\Psr7`, `Psr\Http`, `Slim\Factory`

```
[bootstrap]/  (1 files, 122 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `app.php` | 122 | - ⟨include: __DIR__ . '/../routes/api.php'⟩ | `Cache`@[app/Core], `CorsMiddleware`@[app/Core], `ExceptionHandler`@[app/Core], `MultipartFormDataParser`@[app/Core] | - |

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

- فایل: 1 | کل خط: 30 | کد: 30 | کامنت: 0 | خالی: 0
- به تفکیک پسوند: `.json`=30

```
[root]/  (1 files, 30 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `composer.json` | 30 | - | - | - |

### [routes]

- فایل: 1 | کل خط: 325 | کد: 255 | کامنت: 37 | خالی: 33
- به تفکیک پسوند: `.php`=325
- وابسته به ماژول: **Bot**, **Assignments**, **Tutoring**, **Public**, **Supporters**, **Broadcasts**, **Topics**, **Threads**, **Exams**, **Files**, **Attendance**, **Outbox**, **Instructors**, **WeeklyPlans**, **Appointments**, **ParentContacts**, **[app/Core]**, **Stats**, **Auth**, **Students**, **Dashboard**, **Courses**, **Linking**, **Blog**, **Reports**
- وابستگی‌های بیرونی/ناشناخته (vendor, PHP): `Psr\Http`, `Slim\App`, `Slim\Exception`

```
[routes]/  (1 files, 325 lines)
```

| فایل | خط | تعریف‌ها | وابستگی داخلی | جدول‌ها |
|---|---:|---|---|---|
| `api.php` | 325 | - | `Authorization`@[app/Core], `PublicCacheMiddleware`@[app/Core], `AppointmentController`@Appointments, `AppointmentService`@Appointments, `AssignmentController`@Assignments, `AssignmentService`@Assignments, `AttendanceController`@Attendance, `AttendanceService`@Attendance, `AuthController`@Auth, `AuthMiddleware`@Auth, `AuthService`@Auth, `TelegramAuthController`@Auth, `TelegramAuthService`@Auth, `BlogController`@Blog, `BotAuth`@Bot, `BotController`@Bot, `BotServiceMiddleware`@Bot, `BroadcastController`@Broadcasts, `BroadcastService`@Broadcasts, `CourseController`@Courses, `CourseService`@Courses, `DashboardController`@Dashboard, `ExamController`@Exams, `ExamService`@Exams, `FileController`@Files, `FileService`@Files, `InstructorController`@Instructors, `InstructorService`@Instructors, `LinkController`@Linking, `LinkService`@Linking, `OutboxController`@Outbox, `OutboxService`@Outbox, `ParentContactController`@ParentContacts, `ParentContactService`@ParentContacts, `PublicController`@Public, `ReportController`@Reports, `ReportService`@Reports, `ContentAccessMiddleware`@Stats, `StatsController`@Stats, `StatsService`@Stats, `StudentController`@Students, `StudentService`@Students, `SupporterController`@Supporters, `SupporterService`@Supporters, `SupporterThreadController`@Threads, `ThreadController`@Threads, `ThreadService`@Threads, `TopicController`@Topics, `TopicService`@Topics, `TutoringController`@Tutoring, `TutoringService`@Tutoring, `PlanController`@WeeklyPlans, `PlanService`@WeeklyPlans | - |

### [tests]

- فایل: 34 | کل خط: 6,155 | کد: 4,994 | کامنت: 124 | خالی: 1,037
- به تفکیک پسوند: `.php`=6,155
- namespaceها: `Tests`, `Tests\Core`, `Tests\Modules\Assignments`, `Tests\Modules\Attendance`, `Tests\Modules\Auth`, `Tests\Modules\Bot`, `Tests\Modules\Broadcasts`, `Tests\Modules\Outbox`, `Tests\Modules\Stats`, `Tests\Modules\Students`, `Tests\Modules\Supporters`, `Tests\Modules\Threads`, `Tests\Modules\Tutoring`
- وابسته به ماژول: **[app/Core]**, **Auth**, **Blog**, **Exams**, **Courses**, **Supporters**, **Instructors**, **Assignments**, **Bot**, **Outbox**, **Threads**
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
| `Core/ApiExceptionTest.php` | 49 | class ApiExceptionTest ← TestCase | `ApiException`@[app/Core], `ExceptionHandler`@[app/Core] | - |
| `Core/BlogPostTest.php` | 71 | class BlogPostTest ← TestCase | `Database`@[app/Core], `BlogPost`@Blog | `blog_categories`, `blog_posts` |
| `Core/CacheTest.php` | 137 | class CacheTest ← TestCase | `Cache`@[app/Core] | - |
| `Core/ClientIpTest.php` | 90 | class ClientIpTest ← TestCase | `ClientIp`@[app/Core], `TestCase` | - |
| `Core/CorsMiddlewareTest.php` | 108 | class CorsMiddlewareTest ← TestCase | `AuthCookie`@[app/Core], `CorsMiddleware`@[app/Core] | - |
| `Core/ExamServiceTest.php` | 79 | class ExamServiceTest ← TestCase | `ApiException`@[app/Core], `Database`@[app/Core], `ExamService`@Exams | `exam_results` |
| `Core/MigratorTest.php` | 33 | class MigratorTest ← TestCase | `Migrator`@[app/Core] | `notes` |
| `Core/MultipartFormDataParserTest.php` | 75 | class MultipartFormDataParserTest ← TestCase | `ApiException`@[app/Core], `MultipartFormDataParser`@[app/Core] | - |
| `Core/OpenCorsOriginsTest.php` | 34 | class OpenCorsOriginsTest ← TestCase | `AuthCookie`@[app/Core], `CorsMiddleware`@[app/Core] | - |
| `Core/PaginationTest.php` | 71 | class PaginationTest ← TestCase | `Database`@[app/Core], `Pagination`@[app/Core], `CourseService`@Courses, `InstructorService`@Instructors, `SupporterService`@Supporters | `courses`, `instructors`, `supporters` |
| `Core/RateLimiterTest.php` | 39 | class RateLimiterTest ← TestCase | `RateLimiter`@[app/Core], `TestCase` | `auth_throttle` |
| `Core/StorageTest.php` | 80 | class StorageTest ← TestCase | `ApiException`@[app/Core], `Storage`@[app/Core] | - |
| `Modules/Assignments/AssignmentTest.php` | 153 | class AssignmentTest ← TestCase | `TestCase` | `student_supporter_assignments`, `students`, `supporters`, `users` |
| `Modules/Assignments/AutoAssignmentLinkTest.php` | 220 | class AutoAssignmentLinkTest ← TestCase | `Supporter`@Supporters, `TestCase` | `auth_throttle`, `student_supporter_assignments`, `students`, `supporter_scopes`, `supporters`, `telegram_2fa_challenges`, `telegram_auth_nonces`, `telegram_links`, `users` |
| `Modules/Assignments/SupporterScopeTest.php` | 196 | class SupporterScopeTest ← TestCase | `Assignment`@Assignments, `AutoAssignmentService`@Assignments, `Supporter`@Supporters, `TestCase` | `student_supporter_assignments`, `students`, `supporter_scopes`, `supporters`, `users` |
| `Modules/Attendance/AttendanceTest.php` | 339 | class AttendanceTest ← TestCase | `IranDay`@Bot, `TestCase` | `daily_attendance`, `students`, `users` |
| `Modules/Auth/AuthTest.php` | 345 | class AuthTest ← TestCase | `TestCase` | `login_codes`, `students`, `users` |
| `Modules/Auth/AuthThrottleTest.php` | 220 | class AuthThrottleTest ← TestCase | `RateLimiter`@[app/Core], `TestCase` | `auth_throttle`, `login_codes`, `users` |
| `Modules/Auth/TeacherAuthTest.php` | 152 | class TeacherAuthTest ← TestCase ⟨include: 2FA'⟩ | `Auth`@[app/Core], `Authorization`@[app/Core], `SmsService`@[app/Core], `AuthService`@Auth, `TestCase` | `instructors`, `login_codes`, `users` |
| `Modules/Auth/TelegramAdminLinkTest.php` | 180 | class TelegramAdminLinkTest ← TestCase | `ApiException`@[app/Core], `TelegramLink`@Bot, `TestCase` | `admins`, `auth_throttle`, `login_codes`, `telegram_2fa_challenges`, `telegram_auth_nonces`, `telegram_links`, `users` |
| `Modules/Auth/TelegramAuthTest.php` | 648 | class TelegramAuthTest ← TestCase | `ApiException`@[app/Core], `Auth`@[app/Core], `TelegramLoginVerifier`@Auth, `TelegramTicket`@Auth, `TelegramLink`@Bot, `TestCase` | `auth_throttle`, `login_codes`, `students`, `supporters`, `telegram_2fa_challenges`, `telegram_auth_nonces`, `telegram_links`, `users` |
| `Modules/Bot/BotGatewayTest.php` | 170 | class BotGatewayTest ← TestCase | `TestCase` | `student_supporter_assignments`, `students`, `supporters`, `telegram_links`, `users` |
| `Modules/Bot/IranDayTest.php` | 53 | class IranDayTest ← TestCase | `IranDay`@Bot | - |
| `Modules/Bot/PhoneNormalizerTest.php` | 59 | class PhoneNormalizerTest ← TestCase | `PhoneNormalizer`@Bot | - |
| `Modules/Broadcasts/BroadcastTest.php` | 268 | class BroadcastTest ← TestCase | `Assignment`@Assignments, `IranDay`@Bot, `TelegramLink`@Bot, `TestCase` | `bot_outbox`, `report_broadcast_recipients`, `report_broadcasts`, `report_messages`, `report_threads`, `student_supporter_assignments`, `students`, `supporters`, `telegram_links`, `users` |
| `Modules/Outbox/OutboxTest.php` | 285 | class OutboxTest ← TestCase | `Assignment`@Assignments, `TelegramLink`@Bot, `OutboxService`@Outbox, `TestCase` | `bot_outbox`, `student_supporter_assignments`, `students`, `supporters`, `telegram_links`, `users` |
| `Modules/Stats/StatsTest.php` | 222 | class StatsTest ← TestCase | `Assignment`@Assignments, `IranDay`@Bot, `Thread`@Threads, `TestCase` | `report_messages`, `student_supporter_assignments`, `students`, `supporters`, `users` |
| `Modules/Students/StudentTest.php` | 482 | class StudentTest ← TestCase | `Auth`@[app/Core], `TestCase` | `students`, `users` |
| `Modules/Supporters/SupporterContactTest.php` | 65 | class SupporterContactTest ← TestCase | `TestCase` | `student_supporter_assignments`, `supporters` |
| `Modules/Threads/ThreadTest.php` | 256 | class ThreadTest ← TestCase | `Assignment`@Assignments, `IranDay`@Bot, `TelegramLink`@Bot, `Message`@Threads, `Thread`@Threads, `TestCase` | `student_supporter_assignments`, `students`, `supporters`, `telegram_links`, `users` |
| `Modules/Tutoring/TutoringTest.php` | 542 | class TutoringTest ← TestCase ⟨include: the seeded classroom'⟩ | `Auth`@[app/Core], `IranDay`@Bot, `TestCase` | `daily_attendance`, `instructors`, `students`, `tutoring_classrooms`, `tutoring_sessions`, `tutoring_teacher_status_log`, `users` |
| `TestCase.php` | 269 | class TestCase ← TestCase | `Auth`@[app/Core], `Database`@[app/Core] | `students`, `users` |
| `auth_cookie_test.php` | 139 | - ⟨include: __DIR__ . '/../vendor/autoload.php' | __DIR__ . '/../app/Core/AuthCookie.php'⟩ | `Auth`@[app/Core], `AuthCookie`@[app/Core], `AuthController`@Auth, `AuthMiddleware`@Auth, `AuthService`@Auth | - |
| `bootstrap.php` | 26 | - ⟨include: __DIR__ . '/../vendor/autoload.php' | __DIR__ . '/../app/Core/Database.php' | __DIR__ . '/../bootstrap/app.php'⟩ | `Database`@[app/Core] | - |

---

**محدودیت‌ها:** تحلیل با regex انجام شده (نه parser کامل PHP). جدول‌هایی که به‌صورت داینامیک ساخته میشن (مثل `{$this->table}`) یا داخل heredoc هستن شناسایی نمیشن. مواردی با ⚠️ حدسی‌ان و باید چک بشن.
