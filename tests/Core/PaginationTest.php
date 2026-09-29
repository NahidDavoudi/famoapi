<?php

namespace Tests\Core;

use App\Core\Database;
use App\Core\Pagination;
use App\Modules\Courses\CourseService;
use App\Modules\Instructors\InstructorService;
use App\Modules\Supporters\SupporterService;
use PDO;
use PHPUnit\Framework\TestCase;

class PaginationTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Database::setConnection($this->db);

        $this->db->exec('CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT, display_order INTEGER, created_at TEXT)');
        $this->db->exec('CREATE TABLE instructors (id INTEGER PRIMARY KEY, name TEXT, display_order INTEGER)');
        $this->db->exec('CREATE TABLE supporters (id INTEGER PRIMARY KEY, name TEXT)');
        $this->db->exec('CREATE TABLE reports_status (supporter_id INTEGER, status INTEGER)');
        $this->db->exec("INSERT INTO courses (id, name, display_order) VALUES (1, 'Course', 1)");
        $this->db->exec("INSERT INTO instructors (id, name, display_order) VALUES (1, 'Instructor', 1)");
        $this->db->exec("INSERT INTO supporters (id, name) VALUES (1, 'Supporter')");
    }

    protected function tearDown(): void
    {
        Database::reset();
        parent::tearDown();
    }

    public function testMetadataClampsPageAndPageSize(): void
    {
        self::assertSame([
            'page' => 1,
            'per_page' => 100,
            'total' => 250,
            'total_pages' => 3,
        ], Pagination::build(-3, 1000000, 250));
    }

    public function testCourseQueryUsesClampedPageSize(): void
    {
        $result = (new CourseService())->list(1, 1000000);

        self::assertSame(100, $result['pagination']['per_page']);
        self::assertCount(1, $result['items']);
    }

    public function testInstructorQueryUsesClampedPageSize(): void
    {
        $result = (new InstructorService())->list(1, 1000000);

        self::assertSame(100, $result['pagination']['per_page']);
        self::assertCount(1, $result['items']);
    }

    public function testSupporterQueryUsesClampedPageSize(): void
    {
        $result = (new SupporterService())->list(1, 1000000);

        self::assertSame(100, $result['pagination']['per_page']);
        self::assertCount(1, $result['items']);
    }
}
