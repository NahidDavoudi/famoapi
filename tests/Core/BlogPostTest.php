<?php

namespace Tests\Core;

use App\Core\Database;
use App\Modules\Blog\BlogPost;
use PDO;
use PHPUnit\Framework\TestCase;

class BlogPostTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Database::setConnection($this->db);
        $this->db->exec('CREATE TABLE blog_categories (id INTEGER PRIMARY KEY, slug TEXT NOT NULL)');
        $this->db->exec('CREATE TABLE blog_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT,
            slug TEXT,
            excerpt TEXT,
            content TEXT,
            cover_image TEXT,
            category TEXT,
            category_id INTEGER,
            meta_description TEXT,
            is_published INTEGER,
            published_at TEXT,
            views INTEGER DEFAULT 0
        )');
        $this->db->exec("INSERT INTO blog_categories (id, slug) VALUES (1, 'math')");
    }

    protected function tearDown(): void
    {
        Database::reset();
        parent::tearDown();
    }

    public function testCategorySlugUsesCategoryIdAndKeepsLegacyTextFallback(): void
    {
        $this->db->exec("INSERT INTO blog_posts (title, slug, category, category_id, is_published, published_at) VALUES
            ('Related', 'related', 'legacy-other', 1, 1, '2026-01-01'),
            ('Legacy', 'legacy', 'math', NULL, 1, '2026-01-02'),
            ('Draft', 'draft', 'math', NULL, 0, NULL)");

        self::assertSame(2, BlogPost::countByCategory('math'));
        self::assertSame(['legacy', 'related'], array_column(BlogPost::findByCategory('math', 1, 20), 'slug'));
    }

    public function testCreateDraftHasNoPublicationTimestampAndNormalizesFalseToZero(): void
    {
        $id = BlogPost::create(['title' => 'Draft', 'is_published' => false]);
        $draft = BlogPost::findById($id);

        self::assertSame(0, (int) $draft['is_published']);
        self::assertNull($draft['published_at']);
    }

    public function testEditingPublishedPostDoesNotResetPublicationTimestamp(): void
    {
        $this->db->exec("INSERT INTO blog_posts (id, title, slug, is_published, published_at) VALUES (1, 'Published', 'published', 1, '2025-01-01 10:00:00')");

        BlogPost::update(1, ['title' => 'Corrected title', 'is_published' => true]);

        self::assertSame('2025-01-01 10:00:00', BlogPost::findById(1)['published_at']);
    }
}
