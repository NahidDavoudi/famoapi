<?php

namespace App\Modules\Supporters;

use App\Core\ApiException;
use App\Core\Cache;
use App\Core\Database;
use App\Core\Pagination;
use App\Modules\Bot\PhoneNormalizer;

class SupporterService
{
    private function normalizePhoneOrFail(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $normalized = PhoneNormalizer::normalize($phone);
        if ($normalized === null) {
            throw new ApiException('شماره تلفن پشتیبان معتبر نیست', 422, 'VALIDATION_ERROR');
        }

        return $normalized;
    }

    public function list(int $page, int $perPage): array
    {
        $total = Supporter::countAll();
        $pagination = Pagination::build($page, $perPage, $total);

        $items = Supporter::findAll($pagination['page'], $pagination['per_page']);

        return [
            'items'      => $items,
            'pagination' => $pagination,
        ];
    }

    public function create(array $data): array
    {
        if (array_key_exists('phone', $data)) {
            $data['phone'] = $this->normalizePhoneOrFail($data['phone']);
        }

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

        Cache::flushGroup('public');

        return $this->get($supporterId);
    }

    public function get(int $id): array
    {
        $supporter = Supporter::findById($id);
        if (!$supporter) {
            throw new \App\Core\ApiException('پشتیبان یافت نشد', 404, 'NOT_FOUND');
        }
        return $supporter;
    }

    public function missingPhone(int $page, int $perPage): array
    {
        $total = Supporter::countMissingPhone();
        $pagination = Pagination::build($page, $perPage, $total);

        return [
            'items'      => Supporter::findMissingPhone($pagination['page'], $pagination['per_page']),
            'pagination' => $pagination,
        ];
    }

    public function update(int $id, array $data): array
    {
        $supporter = Supporter::findById($id);
        if (!$supporter) {
            throw new \App\Core\ApiException('پشتیبان یافت نشد', 404, 'NOT_FOUND');
        }

        if (array_key_exists('phone', $data)) {
            $data['phone'] = $this->normalizePhoneOrFail($data['phone']);
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

        Cache::flushGroup('public');

        return $this->get($id);
    }

    public function delete(int $id): void
    {
        $supporter = Supporter::findById($id);
        if (!$supporter) {
            throw new \App\Core\ApiException('پشتیبان یافت نشد', 404, 'NOT_FOUND');
        }

        Supporter::delete($id);

        Cache::flushGroup('public');
    }
}
