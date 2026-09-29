<?php

namespace Tests\Core;

use App\Core\Database;
use App\Modules\Exams\ExamService;
use PDO;
use PHPUnit\Framework\TestCase;

class ExamServiceTest extends TestCase
{
    private PDO $db;
    private ExamService $service;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Database::setConnection($this->db);
        $this->db->exec('CREATE TABLE exam_results (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            exam_date TEXT NOT NULL,
            subject TEXT NOT NULL,
            chapter TEXT,
            total_q INTEGER NOT NULL,
            correct INTEGER NOT NULL,
            wrong INTEGER NOT NULL,
            skipped INTEGER NOT NULL
        )');
        $this->service = new ExamService();
    }

    protected function tearDown(): void
    {
        Database::reset();
        parent::tearDown();
    }

    public function testBulkSaveReplacesPriorResultsForTheStudentAndDate(): void
    {
        $subjects = [[
            'subject' => 'Math', 'total_q' => 10, 'correct' => 5, 'wrong' => 2, 'skipped' => 3,
        ]];
        self::assertSame(['inserted' => 1], $this->service->save(17, '2026-09-01', $subjects));
        self::assertSame(['inserted' => 1], $this->service->save(17, '2026-09-01', $subjects));
        self::assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM exam_results')->fetchColumn());
    }

    public function testNegativeExamCountsAreRejectedBeforeWriting(): void
    {
        try {
            $this->service->save(17, '2026-09-01', [[
                'subject' => 'Math', 'total_q' => 10, 'correct' => 8, 'wrong' => -1, 'skipped' => 0,
            ]]);
            self::fail('Expected invalid negative exam counts to be rejected');
        } catch (\App\Core\ApiException $exception) {
            self::assertSame(422, $exception->getHttpStatus());
            self::assertSame('VALIDATION_ERROR', $exception->getErrorCode());
        }

        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM exam_results')->fetchColumn());
    }

    public function testInvalidDatesAndNonArrayRowsAreRejected(): void
    {
        foreach ([
            ['date' => '2026-02-30', 'subjects' => [['subject' => 'Math', 'total_q' => 1]]],
            ['date' => '2026-09-01', 'subjects' => ['bad row']],
        ] as $case) {
            try {
                $this->service->save(17, $case['date'], $case['subjects']);
                self::fail('Expected invalid exam input to be rejected');
            } catch (\App\Core\ApiException $exception) {
                self::assertSame(422, $exception->getHttpStatus());
            }
        }
    }
}
