<?php

namespace App\Modules\Supporters;

use App\Core\Database;
use App\Core\Pagination;

class SupporterService
{
    public function list(int $page, int $perPage): array
    {
        $total = Supporter::countAll();
        $pagination = Pagination::build($page, $perPage, $total);

        $items = Supporter::findAll($pagination['page'], $perPage);

        return [
            'items'      => $items,
            'pagination' => $pagination,
        ];
    }

    public function create(array $data): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $supporterId = Supporter::create($data);

            $stmt = $db->prepare(
                'INSERT INTO users (username, password_hash, role, linked_id)
                 VALUES (:username, :password_hash, :role, :linked_id)'
            );
            $stmt->execute([
                'username'      => $data['phone'] ?? $data['name'],
                'password_hash' => password_hash('1234', PASSWORD_DEFAULT),
                'role'          => 'supporter',
                'linked_id'     => $supporterId,
            ]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return $this->get($supporterId);
    }

    public function get(int $id): array
    {
        $supporter = Supporter::findById($id);
        if (!$supporter) {
            throw new \RuntimeException('پشتیبان یافت نشد', 404);
        }
        return $supporter;
    }

    public function update(int $id, array $data): array
    {
        $supporter = Supporter::findById($id);
        if (!$supporter) {
            throw new \RuntimeException('پشتیبان یافت نشد', 404);
        }

        Supporter::update($id, $data);

        if (isset($data['password'])) {
            $stmt = Database::getConnection()->prepare(
                'UPDATE users SET password_hash = :password WHERE linked_id = :linked_id AND role = :role'
            );
            $stmt->execute([
                'password'  => password_hash($data['password'], PASSWORD_DEFAULT),
                'linked_id' => $id,
                'role'      => 'supporter',
            ]);
        }

        return $this->get($id);
    }

    public function delete(int $id): void
    {
        $supporter = Supporter::findById($id);
        if (!$supporter) {
            throw new \RuntimeException('پشتیبان یافت نشد', 404);
        }

        Supporter::delete($id);
    }
}