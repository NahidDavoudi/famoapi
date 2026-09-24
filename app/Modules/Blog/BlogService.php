<?php

namespace App\Modules\Blog;

use App\Core\Pagination;

class BlogService
{
    public function getPublishedPosts(int $page, int $perPage): array
    {
        $total = BlogPost::countPublished();
        $pagination = Pagination::build($page, $perPage, $total);
        $posts = BlogPost::findAllPublished($pagination['page'], $perPage);
        return [
            'posts' => $posts,
            'pagination' => $pagination,
        ];
    }

    public function getPostsByCategory(string $category, int $page, int $perPage): array
    {
        $total = BlogPost::countByCategory($category);
        $pagination = Pagination::build($page, $perPage, $total);
        $posts = BlogPost::findByCategory($category, $pagination['page'], $perPage);
        return [
            'posts' => $posts,
            'pagination' => $pagination,
        ];
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
        return ['categories' => BlogPost::getCategories()];
    }

    public function getAllPosts(int $page, int $perPage): array
    {
        $total = BlogPost::countAll();
        $pagination = Pagination::build($page, $perPage, $total);
        $posts = BlogPost::findAll($pagination['page'], $perPage);
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
            throw new \RuntimeException(implode(' | ', $errors));
        }

        $id = BlogPost::create($data);
        $post = BlogPost::findById($id);
        return ['post' => $post];
    }

    public function updatePost(int $id, array $data): array
    {
        $post = BlogPost::findById($id);
        if (!$post) {
            throw new \RuntimeException('پست مورد نظر یافت نشد');
        }

        BlogPost::update($id, $data);
        $post = BlogPost::findById($id);
        return ['post' => $post];
    }

    public function deletePost(int $id): void
    {
        $post = BlogPost::findById($id);
        if (!$post) {
            throw new \RuntimeException('پست مورد نظر یافت نشد');
        }

        $deleted = BlogPost::delete($id);
        if (!$deleted) {
            throw new \RuntimeException('خطا در حذف پست');
        }
    }
}