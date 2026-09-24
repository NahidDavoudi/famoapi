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
use App\Modules\Remedial\RemedialController;
use App\Modules\Remedial\RemedialService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Exception\HttpNotFoundException;

return function (App $app) {
    $authMiddleware = new AuthMiddleware();
    $requireAdmin = new Authorization('admin');
    $requireSupporter = new Authorization('supporter');

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
    $remedialController = new RemedialController(new RemedialService());

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
    $app->post('/api/v1/auth/logout', [$authController, 'logout'])->add($authMiddleware);

    // Public
    $app->get('/api/v1/public/courses', [$publicController, 'getCourses']);
    $app->get('/api/v1/public/instructors', [$publicController, 'getInstructors']);
    $app->get('/api/v1/public/supporters', [$publicController, 'getSupporters']);
    $app->get('/api/v1/public/blog/posts', [$blogController, 'getPosts']);
    $app->get('/api/v1/public/blog/posts/{slug}', [$blogController, 'getPost']);
    $app->get('/api/v1/public/blog/categories', [$blogController, 'getCategories']);
    $app->get('/api/v1/public/blog/categories/{category}/posts', [$blogController, 'getPostsByCategory']);

    // Dashboard (protected)
    $app->get('/api/v1/dashboard/stats', [$dashboardController, 'stats'])->add($requireSupporter)->add($authMiddleware);

    // Blog (protected)
    $app->get('/api/v1/blog/posts', [$blogController, 'getAllPosts'])->add($authMiddleware);
    $app->get('/api/v1/blog/posts/{id:[0-9]+}', [$blogController, 'getPostById'])->add($authMiddleware);
    $app->post('/api/v1/blog/posts', [$blogController, 'createPost'])->add($requireAdmin)->add($authMiddleware);
    $app->put('/api/v1/blog/posts/{id:[0-9]+}', [$blogController, 'updatePost'])->add($requireAdmin)->add($authMiddleware);
    $app->delete('/api/v1/blog/posts/{id:[0-9]+}', [$blogController, 'deletePost'])->add($requireAdmin)->add($authMiddleware);

    // Students (protected)
    $app->get('/api/v1/students/list', [$studentController, 'getList'])->add($authMiddleware);
    $app->get('/api/v1/students', [$studentController, 'list'])->add($authMiddleware);
    $app->post('/api/v1/students', [$studentController, 'create'])->add($requireAdmin)->add($authMiddleware);
    $app->get('/api/v1/students/{id:[0-9]+}', [$studentController, 'get'])->add($authMiddleware);
    $app->put('/api/v1/students/{id:[0-9]+}', [$studentController, 'update'])->add($requireAdmin)->add($authMiddleware);
    $app->delete('/api/v1/students/{id:[0-9]+}', [$studentController, 'delete'])->add($requireAdmin)->add($authMiddleware);
    $app->post('/api/v1/students/{id:[0-9]+}/create-account', [$studentController, 'createAccount'])->add($requireAdmin)->add($authMiddleware);
    $app->post('/api/v1/students/{id:[0-9]+}/reset-password', [$studentController, 'resetPassword'])->add($requireAdmin)->add($authMiddleware);
    $app->post('/api/v1/students/{id:[0-9]+}/toggle-status', [$studentController, 'toggleStatus'])->add($authMiddleware);
    $app->get('/api/v1/students/{id:[0-9]+}/analytics/summary', [$studentController, 'analyticsSummary'])->add($authMiddleware);
    $app->get('/api/v1/students/{id:[0-9]+}/analytics/week-detail', [$studentController, 'analyticsWeekDetail'])->add($authMiddleware);
    $app->get('/api/v1/students/{id:[0-9]+}/analytics/subject-stats', [$studentController, 'analyticsSubjectStats'])->add($authMiddleware);
    $app->get('/api/v1/students/{id:[0-9]+}/analytics/exam-trend', [$studentController, 'analyticsExamTrend'])->add($authMiddleware);

    // Courses (protected)
    $app->get('/api/v1/courses', [$courseController, 'list'])->add($authMiddleware);
    $app->post('/api/v1/courses', [$courseController, 'create'])->add($requireAdmin)->add($authMiddleware);
    $app->get('/api/v1/courses/{id:[0-9]+}', [$courseController, 'get'])->add($authMiddleware);
    $app->put('/api/v1/courses/{id:[0-9]+}', [$courseController, 'update'])->add($requireAdmin)->add($authMiddleware);
    $app->delete('/api/v1/courses/{id:[0-9]+}', [$courseController, 'delete'])->add($requireAdmin)->add($authMiddleware);

    // Instructors (protected)
    $app->get('/api/v1/instructors', [$instructorController, 'list'])->add($authMiddleware);
    $app->post('/api/v1/instructors', [$instructorController, 'create'])->add($requireAdmin)->add($authMiddleware);
    $app->get('/api/v1/instructors/{id:[0-9]+}', [$instructorController, 'get'])->add($authMiddleware);
    $app->put('/api/v1/instructors/{id:[0-9]+}', [$instructorController, 'update'])->add($requireAdmin)->add($authMiddleware);
    $app->delete('/api/v1/instructors/{id:[0-9]+}', [$instructorController, 'delete'])->add($requireAdmin)->add($authMiddleware);

    // Supporters (protected)
    $app->get('/api/v1/supporters', [$supporterController, 'list'])->add($authMiddleware);
    $app->post('/api/v1/supporters', [$supporterController, 'create'])->add($requireAdmin)->add($authMiddleware);
    $app->get('/api/v1/supporters/{id:[0-9]+}', [$supporterController, 'get'])->add($authMiddleware);
    $app->put('/api/v1/supporters/{id:[0-9]+}', [$supporterController, 'update'])->add($requireAdmin)->add($authMiddleware);
    $app->delete('/api/v1/supporters/{id:[0-9]+}', [$supporterController, 'delete'])->add($requireAdmin)->add($authMiddleware);

    // Exams (protected)
    $app->get('/api/v1/exams', [$examController, 'getAll'])->add($authMiddleware);
    $app->get('/api/v1/exams/dates', [$examController, 'getDates'])->add($authMiddleware);
    $app->get('/api/v1/exams/students', [$examController, 'getStudents'])->add($requireSupporter)->add($authMiddleware);
    $app->get('/api/v1/exams/details', [$examController, 'getDetails'])->add($authMiddleware);
    $app->post('/api/v1/exams', [$examController, 'save'])->add($requireAdmin)->add($authMiddleware);

    // Weekly Plans (protected)
    $app->get('/api/v1/plans', [$planController, 'get'])->add($authMiddleware);
    $app->post('/api/v1/plans', [$planController, 'save'])->add($authMiddleware);
    $app->delete('/api/v1/plans', [$planController, 'clear'])->add($authMiddleware);
    $app->get('/api/v1/plans/templates', [$planController, 'getTemplates'])->add($authMiddleware);
    $app->get('/api/v1/plans/templates/{id:[0-9]+}', [$planController, 'getTemplate'])->add($authMiddleware);
    $app->post('/api/v1/plans/templates', [$planController, 'saveTemplate'])->add($authMiddleware);
    $app->delete('/api/v1/plans/templates/{id:[0-9]+}', [$planController, 'deleteTemplate'])->add($authMiddleware);
    $app->post('/api/v1/plans/templates/{id:[0-9]+}/apply', [$planController, 'applyTemplate'])->add($authMiddleware);

    // Reports (protected)
    $app->get('/api/v1/reports', [$reportController, 'list'])->add($requireSupporter)->add($authMiddleware);
    $app->get('/api/v1/reports/stats', [$reportController, 'stats'])->add($requireSupporter)->add($authMiddleware);

    // Files (protected)
    $app->get('/api/v1/files', [$fileController, 'list'])->add($authMiddleware);
    $app->post('/api/v1/files/upload', [$fileController, 'upload'])->add($authMiddleware);
    $app->delete('/api/v1/files/{id:[0-9]+}', [$fileController, 'delete'])->add($authMiddleware);

    // Topics (protected)
    $app->get('/api/v1/topics', [$topicController, 'getChildren'])->add($authMiddleware);
    $app->get('/api/v1/topics/{parent_id:[0-9]+}', [$topicController, 'getChildren'])->add($authMiddleware);
    $app->get('/api/v1/topics/search', [$topicController, 'search'])->add($authMiddleware);
    $app->get('/api/v1/topics/{id:[0-9]+}/path', [$topicController, 'getPath'])->add($authMiddleware);
    $app->get('/api/v1/subjects/{grade:[0-9]+}', [$topicController, 'getSubjectsForGrade'])->add($authMiddleware);

    // Appointments (protected)
    $app->get('/api/v1/appointments', [$appointmentController, 'list'])->add($requireSupporter)->add($authMiddleware);
    $app->post('/api/v1/appointments', [$appointmentController, 'create'])->add($requireSupporter)->add($authMiddleware);
    $app->put('/api/v1/appointments/{id:[0-9]+}/status', [$appointmentController, 'updateStatus'])->add($requireSupporter)->add($authMiddleware);
    $app->delete('/api/v1/appointments/{id:[0-9]+}', [$appointmentController, 'delete'])->add($requireSupporter)->add($authMiddleware);

    // Remedial (protected)
    $app->get('/api/v1/remedial/sessions', [$remedialController, 'getSessions'])->add($authMiddleware);
    $app->post('/api/v1/remedial/sessions', [$remedialController, 'createSession'])->add($authMiddleware);
    $app->get('/api/v1/remedial/sessions/{id:[0-9]+}', [$remedialController, 'getSessionData'])->add($authMiddleware);
    $app->post('/api/v1/remedial/attendance', [$remedialController, 'toggleAttendance'])->add($authMiddleware);
    $app->post('/api/v1/remedial/classes', [$remedialController, 'createClass'])->add($authMiddleware);
    $app->put('/api/v1/remedial/students/time', [$remedialController, 'updateStudentTime'])->add($authMiddleware);
    $app->delete('/api/v1/remedial/classes/{id:[0-9]+}', [$remedialController, 'deleteClass'])->add($authMiddleware);
    $app->post('/api/v1/remedial/students', [$remedialController, 'addStudent'])->add($authMiddleware);
    $app->delete('/api/v1/remedial/students', [$remedialController, 'removeStudent'])->add($authMiddleware);

    // Catch-all 404
    $app->map(['GET', 'POST', 'PUT', 'DELETE'], '/api/v1/{routes:.+}', function (Request $request, Response $response) {
        throw new HttpNotFoundException($request);
    });
};