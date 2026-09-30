<?php

use App\Core\Authorization;
use App\Modules\Auth\AuthController;
use App\Modules\Auth\AuthMiddleware;
use App\Modules\Auth\AuthService;
use App\Modules\Blog\BlogController;
use App\Modules\Dashboard\DashboardController;
use App\Modules\Courses\CourseController;
use App\Modules\Courses\CourseService;
use App\Modules\Instructors\InstructorController;
use App\Modules\Instructors\InstructorService;
use App\Modules\Public\PublicController;
use App\Modules\Students\StudentController;
use App\Modules\Students\StudentService;
use App\Modules\Supporters\SupporterController;
use App\Modules\Supporters\SupporterService;
use App\Modules\Exams\ExamController;
use App\Modules\Exams\ExamService;
use App\Modules\WeeklyPlans\PlanController;
use App\Modules\WeeklyPlans\PlanService;
use App\Modules\Reports\ReportController;
use App\Modules\Reports\ReportService;
use App\Modules\Files\FileController;
use App\Modules\Files\FileService;
use App\Modules\Topics\TopicController;
use App\Modules\Topics\TopicService;
use App\Modules\Appointments\AppointmentController;
use App\Modules\Appointments\AppointmentService;
use App\Modules\Attendance\AttendanceController;
use App\Modules\Attendance\AttendanceService;
use App\Modules\Tutoring\TutoringController;
use App\Modules\Tutoring\TutoringService;
use App\Modules\Remedial\RemedialController;
use App\Modules\Remedial\RemedialService;
use App\Modules\ParentContacts\ParentContactController;
use App\Modules\ParentContacts\ParentContactService;
use App\Modules\Assignments\AssignmentController;
use App\Modules\Assignments\AssignmentService;
use App\Modules\Bot\BotActorMiddleware;
use App\Modules\Bot\BotController;
use App\Modules\Bot\BotServiceMiddleware;
use App\Modules\Linking\LinkController;
use App\Modules\Linking\LinkService;
use App\Modules\Broadcasts\BroadcastController;
use App\Modules\Broadcasts\BroadcastService;
use App\Modules\Outbox\OutboxController;
use App\Modules\Outbox\OutboxService;
use App\Modules\Stats\ContentAccessMiddleware;
use App\Modules\Stats\StatsController;
use App\Modules\Stats\StatsService;
use App\Modules\Threads\SupporterThreadController;
use App\Modules\Threads\ThreadController;
use App\Modules\Threads\ThreadService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Exception\HttpNotFoundException;

return function (App $app) {
    $authMiddleware = new AuthMiddleware();
    $requireAdmin = new Authorization('admin');
    $requireSupporter = new Authorization('supporter');
    $requireTeacher = new Authorization('teacher');

    $authController = new AuthController(new AuthService());
    $dashboardController = new DashboardController();
    $publicController = new PublicController();
    $blogController = new BlogController();
    $studentController = new StudentController(new StudentService());
    $courseController = new CourseController(new CourseService());
    $instructorController = new InstructorController(new InstructorService());
    $supporterController = new SupporterController(new SupporterService());
    $examController = new ExamController(new ExamService());
    $planController = new PlanController(new PlanService());
    $reportController = new ReportController(new ReportService());
    $fileController = new FileController(new FileService());
    $topicController = new TopicController(new TopicService());
    $appointmentController = new AppointmentController(new AppointmentService());
    $attendanceController = new AttendanceController(new AttendanceService());
    $tutoringController = new TutoringController(new TutoringService());
    $remedialController = new RemedialController(new RemedialService());
    $parentContactController = new ParentContactController(new ParentContactService());
    $assignmentController = new AssignmentController(new AssignmentService());
    $botController = new BotController();
    $linkController = new LinkController(new LinkService());
    $threadController = new ThreadController(new ThreadService());
    $supporterThreadController = new SupporterThreadController(new ThreadService());
    $outboxController = new OutboxController(new OutboxService());
    $broadcastController = new BroadcastController(new BroadcastService());
    $statsController = new StatsController(new StatsService());
    $requireContentAccess = new ContentAccessMiddleware();
    $botServiceMiddleware = new BotServiceMiddleware();
    $botActorMiddleware = new BotActorMiddleware();

    // Health
    $app->get('/api/v1/health', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode([
            'success' => true, 'data' => ['status' => 'healthy', 'version' => '1.0.0', 'time' => date('Y-m-d H:i:s')],
            'pagination' => null, 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    });

    // Auth
    $app->post('/api/v1/auth/login', [$authController, 'login']);
    $app->post('/api/v1/auth/register', [$authController, 'register']);
    $app->post('/api/v1/auth/verify-2fa', [$authController, 'verify2fa']);
    $app->get('/api/v1/auth/me', [$authController, 'me'])->add($authMiddleware);
    // Logout only clears the shared auth cookie and must work for expired sessions.
    $app->post('/api/v1/auth/logout', [$authController, 'logout']);

    // Public
    $app->group('/api/v1/public', function ($group) use ($publicController, $blogController) {
        $group->get('/courses', [$publicController, 'getCourses']);
        $group->get('/instructors', [$publicController, 'getInstructors']);
        $group->get('/supporters', [$publicController, 'getSupporters']);
        $group->get('/blog/posts', [$blogController, 'getPosts']);
        $group->get('/blog/posts/{slug}', [$blogController, 'getPost']);
        $group->get('/blog/categories', [$blogController, 'getCategories']);
        $group->get('/blog/categories/{category}/posts', [$blogController, 'getPostsByCategory']);
    })->add(new \App\Core\PublicCacheMiddleware(300));

    // Dashboard (protected)
    $app->get('/api/v1/dashboard/stats', [$dashboardController, 'stats'])->add($requireSupporter)->add($authMiddleware);

    // Blog (protected)
    $app->get('/api/v1/blog/posts', [$blogController, 'getAllPosts'])->add($requireSupporter)->add($authMiddleware);
    $app->get('/api/v1/blog/posts/{id:[0-9]+}', [$blogController, 'getPostById'])->add($requireSupporter)->add($authMiddleware);
    $app->post('/api/v1/blog/posts', [$blogController, 'createPost'])->add($requireAdmin)->add($authMiddleware);
    $app->put('/api/v1/blog/posts/{id:[0-9]+}', [$blogController, 'updatePost'])->add($requireAdmin)->add($authMiddleware);
    $app->delete('/api/v1/blog/posts/{id:[0-9]+}', [$blogController, 'deletePost'])->add($requireAdmin)->add($authMiddleware);

    // Students (protected)
    $app->get('/api/v1/students/list', [$studentController, 'getList'])->add($requireSupporter)->add($authMiddleware);
    $app->get('/api/v1/students/overview', [$studentController, 'overview'])->add($requireAdmin)->add($authMiddleware);
    $app->get('/api/v1/students', [$studentController, 'list'])->add($requireSupporter)->add($authMiddleware);
    $app->post('/api/v1/students', [$studentController, 'create'])->add($requireAdmin)->add($authMiddleware);
    $app->get('/api/v1/students/{id:[0-9]+}', [$studentController, 'get'])->add($requireSupporter)->add($authMiddleware);
    $app->put('/api/v1/students/{id:[0-9]+}', [$studentController, 'update'])->add($requireAdmin)->add($authMiddleware);
    $app->delete('/api/v1/students/{id:[0-9]+}', [$studentController, 'delete'])->add($requireAdmin)->add($authMiddleware);
    $app->post('/api/v1/students/{id:[0-9]+}/create-account', [$studentController, 'createAccount'])->add($requireAdmin)->add($authMiddleware);
    $app->post('/api/v1/students/{id:[0-9]+}/reset-password', [$studentController, 'resetPassword'])->add($requireAdmin)->add($authMiddleware);
    $app->post('/api/v1/students/{id:[0-9]+}/toggle-status', [$studentController, 'toggleStatus'])->add($requireAdmin)->add($authMiddleware);
    $app->get('/api/v1/students/{id:[0-9]+}/analytics/summary', [$studentController, 'analyticsSummary'])->add($authMiddleware);
    $app->get('/api/v1/students/{id:[0-9]+}/analytics/week-detail', [$studentController, 'analyticsWeekDetail'])->add($authMiddleware);
    $app->get('/api/v1/students/{id:[0-9]+}/analytics/subject-stats', [$studentController, 'analyticsSubjectStats'])->add($authMiddleware);
    $app->get('/api/v1/students/{id:[0-9]+}/analytics/exam-trend', [$studentController, 'analyticsExamTrend'])->add($authMiddleware);
    $app->get('/api/v1/students/{studentId:[0-9]+}/parent-contacts', [$parentContactController, 'list'])->add($requireSupporter)->add($authMiddleware);
    $app->post('/api/v1/students/{studentId:[0-9]+}/parent-contacts', [$parentContactController, 'create'])->add($requireAdmin)->add($authMiddleware);
    $app->put('/api/v1/students/{studentId:[0-9]+}/parent-contacts/{id:[0-9]+}', [$parentContactController, 'update'])->add($requireAdmin)->add($authMiddleware);
    $app->delete('/api/v1/students/{studentId:[0-9]+}/parent-contacts/{id:[0-9]+}', [$parentContactController, 'delete'])->add($requireAdmin)->add($authMiddleware);

    // Courses (protected)
    $app->get('/api/v1/courses', [$courseController, 'list'])->add($requireSupporter)->add($authMiddleware);
    $app->post('/api/v1/courses', [$courseController, 'create'])->add($requireAdmin)->add($authMiddleware);
    $app->get('/api/v1/courses/{id:[0-9]+}', [$courseController, 'get'])->add($authMiddleware);
    $app->put('/api/v1/courses/{id:[0-9]+}', [$courseController, 'update'])->add($requireAdmin)->add($authMiddleware);
    $app->delete('/api/v1/courses/{id:[0-9]+}', [$courseController, 'delete'])->add($requireAdmin)->add($authMiddleware);

    // Instructors (protected)
    $app->get('/api/v1/instructors', [$instructorController, 'list'])->add($requireSupporter)->add($authMiddleware);
    $app->post('/api/v1/instructors', [$instructorController, 'create'])->add($requireAdmin)->add($authMiddleware);
    $app->get('/api/v1/instructors/{id:[0-9]+}', [$instructorController, 'get'])->add($authMiddleware);
    $app->put('/api/v1/instructors/{id:[0-9]+}', [$instructorController, 'update'])->add($requireAdmin)->add($authMiddleware);
    $app->delete('/api/v1/instructors/{id:[0-9]+}', [$instructorController, 'delete'])->add($requireAdmin)->add($authMiddleware);

    // Supporters (protected)
    $app->get('/api/v1/supporters', [$supporterController, 'list'])->add($requireSupporter)->add($authMiddleware);
    $app->get('/api/v1/supporters/missing-phone', [$supporterController, 'missingPhone'])->add($requireAdmin)->add($authMiddleware);
    $app->post('/api/v1/supporters', [$supporterController, 'create'])->add($requireAdmin)->add($authMiddleware);
    $app->get('/api/v1/supporters/{id:[0-9]+}', [$supporterController, 'get'])->add($requireSupporter)->add($authMiddleware);
    $app->put('/api/v1/supporters/{id:[0-9]+}', [$supporterController, 'update'])->add($requireAdmin)->add($authMiddleware);
    $app->delete('/api/v1/supporters/{id:[0-9]+}', [$supporterController, 'delete'])->add($requireAdmin)->add($authMiddleware);

    // Student → supporter assignments (canonical). Admin only.
    $app->get('/api/v1/assignments/students', [$assignmentController, 'listStudents'])->add($requireAdmin)->add($authMiddleware);
    $app->post('/api/v1/assignments', [$assignmentController, 'assign'])->add($requireAdmin)->add($authMiddleware);
    $app->delete('/api/v1/assignments/{studentId:[0-9]+}', [$assignmentController, 'unassign'])->add($requireAdmin)->add($authMiddleware);
    $app->get('/api/v1/assignments/supporters/{supporterId:[0-9]+}/students', [$assignmentController, 'supporterStudents'])->add($requireAdmin)->add($authMiddleware);
    $app->get('/api/v1/assignments/history/{studentId:[0-9]+}', [$assignmentController, 'history'])->add($requireAdmin)->add($authMiddleware);
    $app->post('/api/v1/assignments/initial-fill', [$assignmentController, 'initialFill'])->add($requireAdmin)->add($authMiddleware);

    // Bot gateway + identity/linking. Service-key protected, never JWT.
    $app->group('/api/v1/bot', function ($group) use (
        $botController,
        $linkController,
        $threadController,
        $supporterThreadController,
        $outboxController,
        $broadcastController,
        $botActorMiddleware
    ) {
        $group->get('/ping', [$botController, 'ping']);
        $group->get('/me', [$botController, 'me'])->add($botActorMiddleware);

        $group->post('/identity/lookup', [$linkController, 'lookup']);
        $group->post('/identity/link', [$linkController, 'link']);
        $group->get('/identity/resolve', [$linkController, 'resolve']);
        $group->post('/identity/unlink', [$linkController, 'unlink']);
        $group->post('/identity/block', [$linkController, 'block']);
        $group->post('/identity/unblock', [$linkController, 'unblock']);

        // Threads & messages (acting on behalf of a linked account)
        $group->post('/threads/messages', [$threadController, 'sendMessage'])->add($botActorMiddleware);
        $group->get('/threads/day', [$threadController, 'getDay'])->add($botActorMiddleware);
        $group->get('/threads/weekly', [$threadController, 'weekly'])->add($botActorMiddleware);
        $group->post('/threads/read', [$threadController, 'markRead'])->add($botActorMiddleware);

        $group->get('/supporter/inbox', [$supporterThreadController, 'inbox'])->add($botActorMiddleware);
        $group->get('/supporter/students', [$supporterThreadController, 'students'])->add($botActorMiddleware);
        $group->get('/supporter/students/{studentId:[0-9]+}/unread', [$supporterThreadController, 'unread'])->add($botActorMiddleware);
        $group->post('/supporter/reply', [$supporterThreadController, 'reply'])->add($botActorMiddleware);

        // Broadcasts (supporter → own students)
        $group->post('/broadcasts/preview', [$broadcastController, 'preview'])->add($botActorMiddleware);
        $group->post('/broadcasts/confirm', [$broadcastController, 'confirm'])->add($botActorMiddleware);
        $group->get('/broadcasts', [$broadcastController, 'list'])->add($botActorMiddleware);
        $group->get('/broadcasts/{id:[0-9]+}', [$broadcastController, 'get'])->add($botActorMiddleware);

        // Outbox pull/report protocol (bot worker; service key only)
        $group->post('/outbox/claim', [$outboxController, 'claim']);
        $group->post('/outbox/report', [$outboxController, 'report']);
    })->add($botServiceMiddleware);

    // Exams (protected)
    $app->get('/api/v1/exams', [$examController, 'getAll'])->add($authMiddleware);
    $app->get('/api/v1/exams/dates', [$examController, 'getDates'])->add($authMiddleware);
    $app->get('/api/v1/exams/students', [$examController, 'getStudents'])->add($requireSupporter)->add($authMiddleware);
    $app->get('/api/v1/exams/details', [$examController, 'getDetails'])->add($authMiddleware);
    $app->post('/api/v1/exams', [$examController, 'save'])->add($requireAdmin)->add($authMiddleware);

    // Weekly Plans (temporary: admin-only while the planner is being migrated)
    $app->get('/api/v1/plans', [$planController, 'get'])->add($requireAdmin)->add($authMiddleware);
    $app->post('/api/v1/plans', [$planController, 'save'])->add($requireAdmin)->add($authMiddleware);
    $app->delete('/api/v1/plans', [$planController, 'clear'])->add($requireAdmin)->add($authMiddleware);
    $app->get('/api/v1/plans/{id:[0-9]+}', [$planController, 'getOne'])->add($requireAdmin)->add($authMiddleware);
    $app->put('/api/v1/plans/{id:[0-9]+}', [$planController, 'update'])->add($requireAdmin)->add($authMiddleware);
    $app->delete('/api/v1/plans/{id:[0-9]+}', [$planController, 'deleteOne'])->add($requireAdmin)->add($authMiddleware);
    $app->get('/api/v1/plans/templates', [$planController, 'getTemplates'])->add($requireAdmin)->add($authMiddleware);
    $app->get('/api/v1/plans/templates/{id:[0-9]+}', [$planController, 'getTemplate'])->add($requireAdmin)->add($authMiddleware);
    $app->post('/api/v1/plans/templates', [$planController, 'saveTemplate'])->add($requireAdmin)->add($authMiddleware);
    $app->delete('/api/v1/plans/templates/{id:[0-9]+}', [$planController, 'deleteTemplate'])->add($requireAdmin)->add($authMiddleware);
    $app->post('/api/v1/plans/templates/{id:[0-9]+}/apply', [$planController, 'applyTemplate'])->add($requireAdmin)->add($authMiddleware);

    // Reports (protected)
    $app->get('/api/v1/reports', [$reportController, 'list'])->add($requireSupporter)->add($authMiddleware);
    $app->get('/api/v1/reports/stats', [$reportController, 'stats'])->add($requireSupporter)->add($authMiddleware);

    // Files (protected)
    $app->get('/api/v1/files', [$fileController, 'list'])->add($authMiddleware);
    $app->post('/api/v1/files/upload', [$fileController, 'upload'])->add($authMiddleware);
    $app->get('/api/v1/files/{id:[0-9]+}/download', [$fileController, 'download'])->add($authMiddleware);
    $app->delete('/api/v1/files/{id:[0-9]+}', [$fileController, 'delete'])->add($authMiddleware);

    // Topics (protected)
    $app->get('/api/v1/topics', [$topicController, 'getChildren'])->add($requireSupporter)->add($authMiddleware);
    $app->get('/api/v1/topics/{parent_id:[0-9]+}', [$topicController, 'getChildren'])->add($requireSupporter)->add($authMiddleware);
    $app->get('/api/v1/topics/search', [$topicController, 'search'])->add($requireSupporter)->add($authMiddleware);
    $app->get('/api/v1/topics/{id:[0-9]+}/path', [$topicController, 'getPath'])->add($requireSupporter)->add($authMiddleware);
    $app->get('/api/v1/subjects/{grade:[0-9]+}', [$topicController, 'getSubjectsForGrade'])->add($requireSupporter)->add($authMiddleware);

    // Appointments (protected)
    $app->get('/api/v1/appointments', [$appointmentController, 'list'])->add($requireSupporter)->add($authMiddleware);
    $app->post('/api/v1/appointments', [$appointmentController, 'create'])->add($requireSupporter)->add($authMiddleware);
    $app->put('/api/v1/appointments/{id:[0-9]+}/status', [$appointmentController, 'updateStatus'])->add($requireSupporter)->add($authMiddleware);
    $app->delete('/api/v1/appointments/{id:[0-9]+}', [$appointmentController, 'delete'])->add($requireSupporter)->add($authMiddleware);

    // Attendance (protected)
    $app->get('/api/v1/attendance', [$attendanceController, 'list'])->add($requireSupporter)->add($authMiddleware);
    $app->get('/api/v1/attendance/print', [$attendanceController, 'printList'])->add($requireSupporter)->add($authMiddleware);
    $app->put('/api/v1/attendance/entries', [$attendanceController, 'upsertEntry'])->add($requireSupporter)->add($authMiddleware);
    $app->patch('/api/v1/attendance/{id:[0-9]+}/times', [$attendanceController, 'setTime'])->add($requireSupporter)->add($authMiddleware);
    $app->post('/api/v1/attendance/guests', [$attendanceController, 'addGuest'])->add($requireSupporter)->add($authMiddleware);

    // Tutoring (teacher panel + live board)
    $app->get('/api/v1/tutoring/me', [$tutoringController, 'me'])->add($requireTeacher)->add($authMiddleware);
    $app->put('/api/v1/tutoring/status', [$tutoringController, 'setStatus'])->add($requireTeacher)->add($authMiddleware);
    $app->get('/api/v1/tutoring/students', [$tutoringController, 'students'])->add($requireTeacher)->add($authMiddleware);
    $app->post('/api/v1/tutoring/sessions', [$tutoringController, 'startSession'])->add($requireTeacher)->add($authMiddleware);
    $app->post('/api/v1/tutoring/sessions/{id:[0-9]+}/end', [$tutoringController, 'endSession'])->add($requireTeacher)->add($authMiddleware);
    $app->get('/api/v1/tutoring/board', [$tutoringController, 'board'])->add($requireSupporter)->add($authMiddleware);

    // Remedial (protected)
    $app->get('/api/v1/remedial/sessions', [$remedialController, 'getSessions'])->add($requireSupporter)->add($authMiddleware);
    $app->post('/api/v1/remedial/sessions', [$remedialController, 'createSession'])->add($requireSupporter)->add($authMiddleware);
    $app->get('/api/v1/remedial/sessions/{id:[0-9]+}', [$remedialController, 'getSessionData'])->add($requireSupporter)->add($authMiddleware);
    $app->post('/api/v1/remedial/attendance', [$remedialController, 'toggleAttendance'])->add($requireSupporter)->add($authMiddleware);
    $app->post('/api/v1/remedial/classes', [$remedialController, 'createClass'])->add($requireSupporter)->add($authMiddleware);
    $app->put('/api/v1/remedial/students/time', [$remedialController, 'updateStudentTime'])->add($requireSupporter)->add($authMiddleware);
    $app->delete('/api/v1/remedial/classes/{id:[0-9]+}', [$remedialController, 'deleteClass'])->add($requireSupporter)->add($authMiddleware);
    $app->post('/api/v1/remedial/students', [$remedialController, 'addStudent'])->add($requireSupporter)->add($authMiddleware);
    $app->delete('/api/v1/remedial/students', [$remedialController, 'removeStudent'])->add($requireSupporter)->add($authMiddleware);

    // Admin statistics (admin JWT). Content reading has a separate permission.
    $app->group('/api/v1/admin/stats', function ($group) use ($statsController, $requireContentAccess) {
        $group->get('/reports/overview', [$statsController, 'overview']);
        $group->get('/reports/by-major', [$statsController, 'byMajor']);
        $group->get('/reports/by-supporter', [$statsController, 'bySupporter']);
        $group->get('/students/no-report', [$statsController, 'noReport']);
        $group->get('/supporters/performance', [$statsController, 'performance']);
        $group->get('/students/{id:[0-9]+}/history', [$statsController, 'history']);
        $group->get('/students/{id:[0-9]+}/thread', [$statsController, 'thread'])->add($requireContentAccess);
    })->add($requireAdmin)->add($authMiddleware);

    // Catch-all 404
    $app->map(['GET', 'POST', 'PUT', 'DELETE'], '/api/v1/{routes:.+}', function (Request $request, Response $response) {
        throw new HttpNotFoundException($request);
    });
};
