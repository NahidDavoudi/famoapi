<?php

namespace App\Modules\Supporters;

use App\Core\ApiException;
use App\Core\Cache;
use App\Core\Database;
use App\Core\Pagination;
use App\Modules\Bot\PhoneNormalizer;

class SupporterService
{
    private const VALID_GRADES = [7, 8, 9, 10, 11, 12];

    /**
     * @param array<int,array{field:mixed,grade:mixed}> $scopes
     * @return array<int,array{field:string,grade:int}>
     */
    private function normalizeScopes(array $scopes): array
    {
        $normalized = [];
        foreach ($scopes as $scope) {
            if (!is_array($scope)) {
                throw new ApiException('ساختار محدوده پشتیبانی معتبر نیست', 422, 'VALIDATION_ERROR');
            }
            $field = trim((string) ($scope['field'] ?? ''));
            $grade = (int) ($scope['grade'] ?? 0);
            if ($field === '' || !in_array($grade, self::VALID_GRADES, true)) {
                throw new ApiException('رشته یا پایه تحصیلی محدوده پشتیبانی معتبر نیست', 422, 'VALIDATION_ERROR');
            }
            $normalized[$field . '|' . $grade] = ['field' => $field, 'grade' => $grade];
        }

        return array_values($normalized);
    }

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

        $scopes = null;
        if (array_key_exists('scopes', $data) && is_array($data['scopes']) && $data['scopes'] !== []) {
            $scopes = $this->normalizeScopes($data['scopes']);
        } elseif (array_key_exists('grade', $data) && array_key_exists('field', $data)
            && $data['grade'] !== null && $data['field'] !== null && $data['field'] !== '') {
            $scopes = $this->normalizeScopes([['field' => $data['field'], 'grade' => $data['grade']]]);
        }

        if ($scopes !== null && $scopes !== []) {
            if (empty($data['field'])) {
                $data['field'] = $scopes[0]['field'];
            }
            if (empty($data['grade'])) {
                $data['grade'] = $scopes[0]['grade'];
            }
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

            if ($scopes !== null) {
                Supporter::replaceScopes($supporterId, $scopes);
            }

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

        $scopes = null;
        if (array_key_exists('scopes', $data) && is_array($data['scopes'])) {
            $scopes = $this->normalizeScopes($data['scopes']);
        } elseif (array_key_exists('grade', $data) || array_key_exists('field', $data)) {
            $field = array_key_exists('field', $data) ? $data['field'] : ($supporter['field'] ?? '');
            $grade = array_key_exists('grade', $data) ? $data['grade'] : ($supporter['grade'] ?? 0);
            $scopes = $this->normalizeScopes([['field' => $field, 'grade' => $grade]]);
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            Supporter::update($id, $data);

            if (isset($data['password'])) {
                $stmt = $db->prepare(
                    'UPDATE users SET password_hash = :password WHERE linked_id = :linked_id AND role = :role'
                );
                $stmt->execute([
                    'password'  => password_hash($data['password'], PASSWORD_DEFAULT),
                    'linked_id' => $id,
                    'role'      => 'supporter',
                ]);
            }

            if ($scopes !== null) {
                Supporter::replaceScopes($id, $scopes);
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
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
