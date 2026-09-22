<?php

namespace App\Modules\Blog;

use App\Core\Database;
use App\Core\Pagination;

class BlogPost
{
    public static function findAllPublished(int $page, int $perPage): array
    {
        $db = Database::getConnection();
        $offset = Pagination::offset($page, $perPage);
        $stmt = $db->prepare(
            'SELECT * FROM blog_posts WHERE is_published = 1 ORDER BY published_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$perPage, $offset]);
        return $stmt->fetchAll();
    }

    public static function countPublished(): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT COUNT(*) FROM blog_posts WHERE is_published = 1');
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function findBySlug(string $slug): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM blog_posts WHERE slug = ? AND is_published = 1');
        $stmt->execute([$slug]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function findByCategory(string $category, int $page, int $perPage): array
    {
        $db = Database::getConnection();
        $offset = Pagination::offset($page, $perPage);
        $stmt = $db->prepare(
            'SELECT * FROM blog_posts WHERE category = ? AND is_published = 1 ORDER BY published_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$category, $perPage, $offset]);
        return $stmt->fetchAll();
    }

    public static function countByCategory(string $category): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT COUNT(*) FROM blog_posts WHERE category = ? AND is_published = 1');
        $stmt->execute([$category]);
        return (int) $stmt->fetchColumn();
    }

    public static function findAll(int $page, int $perPage): array
    {
        $db = Database::getConnection();
        $offset = Pagination::offset($page, $perPage);
        $stmt = $db->prepare(
            'SELECT * FROM blog_posts ORDER BY created_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$perPage, $offset]);
        return $stmt->fetchAll();
    }

    public static function countAll(): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT COUNT(*) FROM blog_posts');
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function findById(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM blog_posts WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getConnection();
        $slug = self::makeUniqueSlug($data['slug'] ?? null, $data['title'] ?? '');
        $stmt = $db->prepare(
            'INSERT INTO blog_posts (title, slug, excerpt, content, cover_image, category, category_id, author_id, is_published, published_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['title'],
            $slug,
            $data['excerpt'] ?? null,
            $data['content'] ?? null,
            $data['cover_image'] ?? null,
            $data['category'] ?? null,
            $data['category_id'] ?? null,
            $data['author_id'] ?? null,
            $data['is_published'] ?? 0,
            $data['is_published'] ? ($data['published_at'] ?? date('Y-m-d H:i:s')) : null,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): int
    {
        $db = Database::getConnection();
        $fields = [];
        $values = [];

        foreach (['title', 'slug', 'excerpt', 'content', 'cover_image', 'category', 'category_id', 'author_id', 'is_published', 'published_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return 0;
        }

        $values[] = $id;
        $stmt = $db->prepare(
            'UPDATE blog_posts SET ' . implode(', ', $fields) . ' WHERE id = ?'
        );
        $stmt->execute($values);
        return $stmt->rowCount();
    }

    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM blog_posts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function getCategories(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT category AS name, category AS slug, COUNT(*) AS post_count
             FROM blog_posts
             WHERE is_published = 1 AND category IS NOT NULL AND category != \'\'
             GROUP BY category
             ORDER BY category ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private static function makeUniqueSlug(?string $slug, string $title): string
    {
        $slug = $slug ?: self::slugify($title);
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT COUNT(*) FROM blog_posts WHERE slug = ?');
        $stmt->execute([$slug]);
        $count = (int) $stmt->fetchColumn();

        if ($count === 0) {
            return $slug;
        }

        $counter = 1;
        while (true) {
            $newSlug = $slug . '-' . $counter;
            $stmt = $db->prepare('SELECT COUNT(*) FROM blog_posts WHERE slug = ?');
            $stmt->execute([$newSlug]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $newSlug;
            }
            $counter++;
        }
    }

    private static function slugify(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/[^\w\s\-]/u', '', $text);
        $text = preg_replace('/\s+/', '-', $text);
        $text = mb_strtolower($text, 'UTF-8');
        return $text;
    }
}