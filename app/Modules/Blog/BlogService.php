<?php

namespace App\Modules\Blog;

use App\Core\Cache;
use App\Core\Pagination;

class BlogService
{
    private const GROUP = 'public';
    private const TTL = 600;
    private const MAX_CACHED_PAGE = 10;

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

        if ($page > self::MAX_CACHED_PAGE) {
            return $load();
        }

        return Cache::remember(self::GROUP, "blog_posts:0:{$page}:{$perPage}", self::TTL, $load);
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

        if ($page > self::MAX_CACHED_PAGE) {
            return $load();
        }

        return Cache::remember(self::GROUP, "blog_posts:{$categoryKey}:{$page}:{$perPage}", self::TTL, $load);
    }

    public function getPost(string $slug): ?array
    {
        $post = BlogPost::findBySlug($slug);
        if ($post) {
            BlogPost::incrementViews((int) $post['id']);
            $post['views'] = ($post['views'] ?? 0) + 1;
        }
        return $post;
    }

    public function getCategories(): array
    {
        return Cache::remember(self::GROUP, 'blog_categories', self::TTL, function () {
            return ['categories' => BlogPost::getCategories()];
        });
    }

    public function getAllPosts(int $page, int $perPage): array
    {
        $total = BlogPost::countAll();
        $pagination = Pagination::build($page, $perPage, $total);
        $posts = BlogPost::findAll($pagination['page'], $pagination['per_page']);
        return [
            'posts' => $posts,
            'pagination' => $pagination,
        ];
    }

    public function getPostById(int $id): ?array
    {
        return BlogPost::findById($id);
    }

    public function createPost(array $data): array
    {
        $errors = [];
        if (empty($data['title'])) {
            $errors[] = 'عنوان پست الزامی است';
        }
        if (!empty($errors)) {
            throw new \App\Core\ApiException(implode(' | ', $errors), 422, 'VALIDATION_ERROR');
        }

        $id = BlogPost::create($data);
        $post = BlogPost::findById($id);

        Cache::flushGroup(self::GROUP);

        return ['post' => $post];
    }

    public function updatePost(int $id, array $data): array
    {
        $post = BlogPost::findById($id);
        if (!$post) {
            throw new \App\Core\ApiException('پست مورد نظر یافت نشد', 404, 'NOT_FOUND');
        }

        BlogPost::update($id, $data);
        $post = BlogPost::findById($id);

        Cache::flushGroup(self::GROUP);

        return ['post' => $post];
    }

    public function deletePost(int $id): void
    {
        $post = BlogPost::findById($id);
        if (!$post) {
            throw new \App\Core\ApiException('پست مورد نظر یافت نشد', 404, 'NOT_FOUND');
        }

        $deleted = BlogPost::delete($id);
        if (!$deleted) {
            throw new \App\Core\ApiException('خطا در حذف پست', 500, 'INTERNAL_ERROR');
        }

        Cache::flushGroup(self::GROUP);
    }
}
