<?php

namespace App\Modules\Blog;

use App\Core\ApiException;
use App\Core\AssetUrl;
use App\Core\Cache;
use App\Core\Pagination;
use App\Core\Storage;

class BlogService
{
    private const GROUP = 'public';
    private const TTL = 600;
    private const MAX_CACHED_PAGE = 10;

    // ── خواندن ──

    public function getPublishedPosts(int $page, int $perPage): array
    {
        $page = max($page, 1);
        $perPage = min(max($perPage, 1), 50);

        $load = function () use ($page, $perPage) {
            $total = BlogPost::countPublished();
            $pagination = Pagination::build($page, $perPage, $total);
            $posts = BlogPost::findAllPublished($pagination['page'], $pagination['per_page']);
            return [
                'posts' => $posts,
                'pagination' => $pagination,
            ];
        };

        $result = $page > self::MAX_CACHED_PAGE
            ? $load()
            : Cache::remember(self::GROUP, "blog_posts:0:{$page}:{$perPage}", self::TTL, $load);

        $result['posts'] = array_map([$this, 'hydratePost'], $result['posts']);
        return $result;
    }

    public function getPostsByCategory(string $category, int $page, int $perPage): array
    {
        $page = max($page, 1);
        $perPage = min(max($perPage, 1), 50);
        $categoryKey = $category === '' ? 'all' : $category;

        $load = function () use ($category, $page, $perPage) {
            $total = BlogPost::countByCategory($category);
            $pagination = Pagination::build($page, $perPage, $total);
            $posts = BlogPost::findByCategory($category, $pagination['page'], $pagination['per_page']);
            return [
                'posts' => $posts,
                'pagination' => $pagination,
            ];
        };

        $result = $page > self::MAX_CACHED_PAGE
            ? $load()
            : Cache::remember(self::GROUP, "blog_posts:{$categoryKey}:{$page}:{$perPage}", self::TTL, $load);

        $result['posts'] = array_map([$this, 'hydratePost'], $result['posts']);
        return $result;
    }

    public function getPost(string $slug): ?array
    {
        $post = BlogPost::findBySlug($slug);
        if ($post) {
            BlogPost::incrementViews((int) $post['id']);
            $post['views'] = ($post['views'] ?? 0) + 1;
            return $this->hydratePost($post);
        }
        return null;
    }

    public function getCategories(): array
    {
        return Cache::remember(self::GROUP, 'blog_category', self::TTL, function () {
            return ['categories' => BlogPost::getCategories()];
        });
    }

    public function getAllPosts(int $page, int $perPage): array
    {
        $total = BlogPost::countAll();
        $pagination = Pagination::build($page, $perPage, $total);
        $posts = BlogPost::findAll($pagination['page'], $pagination['per_page']);
        $posts = array_map([$this, 'hydratePost'], $posts);
        return [
            'posts' => $posts,
            'pagination' => $pagination,
        ];
    }

    public function getPostById(int $id): ?array
    {
        $post = BlogPost::findById($id);
        return $post ? $this->hydratePost($post) : null;
    }

    // ── نوشتن ──

    public function createPost(array $data): array
    {
        if (empty($data['title'])) {
            throw new ApiException('عنوان پست الزامی است', 422, 'VALIDATION_ERROR');
        }

        $newPath = null;
        if (!empty($data['cover_image_file'])) {
            $newPath = Storage::upload($data['cover_image_file'], 'blog');
            $data['cover_image'] = $newPath;
            unset($data['cover_image_file']);
        }

        try {
            $id = BlogPost::create($data);
        } catch (\Throwable $e) {
            if ($newPath !== null) {
                Storage::delete($newPath);
            }
            throw $e;
        }

        Cache::flushGroup(self::GROUP);

        return ['post' => $this->getPostById($id)];
    }

    public function updatePost(int $id, array $data): array
    {
        $post = BlogPost::findById($id);
        if (!$post) {
            throw new ApiException('پست مورد نظر یافت نشد', 404, 'NOT_FOUND');
        }

        $oldCover = $post['cover_image'] ?? null;
        $newPath  = null;

        if (!empty($data['cover_image_file'])) {
            $newPath = Storage::upload($data['cover_image_file'], 'blog', $id);
            $data['cover_image'] = $newPath;
            unset($data['cover_image_file']);
        }

        try {
            BlogPost::update($id, $data);
        } catch (\Throwable $e) {
            if ($newPath !== null) {
                Storage::delete($newPath);
            }
            throw $e;
        }

        // پاک‌سازی کاور قبلی اگر فایل محلی بود (نه URL خارجی)
        if ($newPath !== null && $oldCover && !preg_match('#^(https?:)?//#i', $oldCover)) {
            Storage::delete($oldCover);
        }

        Cache::flushGroup(self::GROUP);

        return ['post' => $this->getPostById($id)];
    }

    public function deletePost(int $id): void
    {
        $post = BlogPost::findById($id);
        if (!$post) {
            throw new ApiException('پست مورد نظر یافت نشد', 404, 'NOT_FOUND');
        }

        $cover = $post['cover_image'] ?? null;

        if (!BlogPost::delete($id)) {
            throw new ApiException('خطا در حذف پست', 500, 'INTERNAL_ERROR');
        }

        if ($cover && !preg_match('#^(https?:)?//#i', $cover)) {
            Storage::delete($cover);
        }

        Cache::flushGroup(self::GROUP);
    }

    // ── Hydrate ──

    /**
     * مسیر نسبی ذخیره‌شده در DB را به URL کامل تبدیل می‌کند
     * تا فرانت آن را نسبت به دامنه‌ی خودش resolve نکند.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private function hydratePost(array $post): array
    {
        if (!empty($post['cover_image'])) {
            $post['cover_image'] = AssetUrl::fromStorage($post['cover_image']);
        }
        return $post;
    }
}