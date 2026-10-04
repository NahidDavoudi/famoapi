<?php

namespace App\Modules\Supporters;

use App\Core\Database;

class Supporter
{
    public static function findAll(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = Database::getConnection()->prepare(
            'SELECT s.*,
                    COALESCE(r.replied_count, 0) AS replied_count,
                    COALESCE(r.pending_count, 0) AS pending_count
             FROM supporters s
             LEFT JOIN (
                 SELECT supporter_id,
                        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS replied_count,
                        SUM(CASE WHEN status = 0 OR status IS NULL THEN 1 ELSE 0 END) AS pending_count
                 FROM reports_status
                 GROUP BY supporter_id
             ) r ON r.supporter_id = s.id
             ORDER BY s.name ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countAll(): int
    {
        $stmt = Database::getConnection()->query('SELECT COUNT(*) FROM supporters');
        return (int) $stmt->fetchColumn();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT s.*, u.id AS user_id, u.username, u.role
             FROM supporters s
             LEFT JOIN users u ON u.linked_id = s.id AND u.role = :role
             WHERE s.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'role' => 'supporter']);
        $result = $stmt->fetch();
        return $result ?: null;
    }

 public static function findByField(string $field): ?int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id FROM supporters WHERE field = :field LIMIT 1'
        );
        $stmt->execute(['field' => $field]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Lookup supporters by equivalent raw phone representations.
     *
     * @param string[] $forms
     * @return array<int,array>
     */
    public static function findByPhoneForms(array $forms): array
    {
        if ($forms === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($forms), '?'));
        $stmt = Database::getConnection()->prepare(
            "SELECT id, name, grade, field, phone, is_active
             FROM supporters
             WHERE phone IN ({$placeholders})"
        );
        $stmt->execute(array_values($forms));

        return $stmt->fetchAll();
    }

    public static function findMissingPhone(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = Database::getConnection()->prepare(
            "SELECT id, name, grade, field, phone, is_active, created_at
             FROM supporters
             WHERE phone IS NULL OR phone = ''
             ORDER BY name ASC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function countMissingPhone(): int
    {
        $stmt = Database::getConnection()->query(
            "SELECT COUNT(*) FROM supporters WHERE phone IS NULL OR phone = ''"
        );

        return (int) $stmt->fetchColumn();
    }

    /**
     * Active supporters considered for assignment matching.
     *
     * @return array<int,array>
     */
    public static function findActiveForAssignment(): array
    {
        $stmt = Database::getConnection()->query(
            'SELECT id, name, grade, field, phone
             FROM supporters
             WHERE is_active = 1
             ORDER BY id ASC'
        );

        $rows = $stmt->fetchAll();

        if (self::hasUsersActiveFlag()) {
            $rows = array_values(array_filter($rows, static function (array $row): bool {
                return self::isAccountActive((int) $row['id']);
            }));
        }

        $scopesBySupporter = self::scopesBySupporterIds(array_map(
            static fn (array $row): int => (int) $row['id'],
            $rows
        ));

        foreach ($rows as &$row) {
            $scopes = $scopesBySupporter[(int) $row['id']] ?? [];
            if ($scopes === [] && $row['field'] !== null && $row['grade'] !== null && $row['field'] !== '') {
                $scopes = [['field' => (string) $row['field'], 'grade' => (int) $row['grade']]];
            }
            $row['scopes'] = $scopes;
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int,array{field:string,grade:int}>
     */
    public static function findScopes(int $id): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT field, grade FROM supporter_scopes
             WHERE supporter_id = :id ORDER BY field ASC, grade ASC'
        );
        $stmt->execute(['id' => $id]);

        $scopes = [];
        foreach ($stmt->fetchAll() as $row) {
            $scopes[] = ['field' => (string) $row['field'], 'grade' => (int) $row['grade']];
        }

        return $scopes;
    }

    /**
     * @param array<int,array{field:string,grade:int}> $scopes
     */
    public static function replaceScopes(int $id, array $scopes): void
    {
        $db = Database::getConnection();
        $startedTransaction = !$db->inTransaction();
        if ($startedTransaction) {
            $db->beginTransaction();
        }

        try {
            $db->prepare('DELETE FROM supporter_scopes WHERE supporter_id = :id')->execute(['id' => $id]);

            $insert = $db->prepare(
                'INSERT INTO supporter_scopes (supporter_id, field, grade)
                 VALUES (:supporter_id, :field, :grade)'
            );
            foreach ($scopes as $scope) {
                $insert->execute([
                    'supporter_id' => $id,
                    'field'        => (string) $scope['field'],
                    'grade'        => (int) $scope['grade'],
                ]);
            }

            if ($startedTransaction) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param int[] $supporterIds
     * @return array<int,array<int,array{field:string,grade:int}>>
     */
    public static function scopesBySupporterIds(array $supporterIds): array
    {
        $supporterIds = array_values(array_unique(array_filter(
            array_map('intval', $supporterIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($supporterIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($supporterIds), '?'));
        $stmt = Database::getConnection()->prepare(
            "SELECT supporter_id, field, grade FROM supporter_scopes
             WHERE supporter_id IN ({$placeholders})
             ORDER BY field ASC, grade ASC"
        );
        $stmt->execute($supporterIds);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['supporter_id']][] = [
                'field' => (string) $row['field'],
                'grade' => (int) $row['grade'],
            ];
        }

        return $out;
    }

    /**
     * Supporters are active when supporters.is_active = 1 AND, if a linked
     * users row exists and exposes an is_active flag, it is not explicitly 0.
     */
    public static function isAccountActive(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT is_active FROM supporters WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $flag = $stmt->fetchColumn();
        if ($flag === false || (int) $flag !== 1) {
            return false;
        }

        if (!self::hasUsersActiveFlag()) {
            return true;
        }

        $stmt = Database::getConnection()->prepare(
            'SELECT u.is_active
             FROM users u
             WHERE u.role = :role AND u.linked_id = :linked_id
             LIMIT 1'
        );
        $stmt->execute(['role' => 'supporter', 'linked_id' => $id]);
        $userFlag = $stmt->fetchColumn();

        return $userFlag === false || (int) $userFlag !== 0;
    }

    private static ?bool $usersActiveFlag = null;

    private static function hasUsersActiveFlag(): bool
    {
        if (self::$usersActiveFlag === null) {
            $stmt = Database::getConnection()->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_active'"
            );
            $stmt->execute();
            self::$usersActiveFlag = (int) $stmt->fetchColumn() > 0;
        }

        return self::$usersActiveFlag;
    }

    public static function create(array $data): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO supporters (name, grade, field, phone, chat_id, is_active)
             VALUES (:name, :grade, :field, :phone, :chat_id, :is_active)'
        );
        $stmt->execute([
            'name'      => $data['name'],
            'grade'     => $data['grade'] ?? null,
            'field'     => $data['field'] ?? null,
            'phone'     => $data['phone'] ?? null,
            'chat_id'   => $data['chat_id'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (int) $data['is_active'] : 1,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): int
    {
        $sets = [];
        $params = ['id' => $id];
        $allowed = ['name', 'grade', 'field', 'phone', 'chat_id', 'is_active'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($sets)) return 0;

        $stmt = Database::getConnection()->prepare('UPDATE supporters SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM users WHERE linked_id = :linked_id AND role = :role');
        $stmt->execute(['linked_id' => $id, 'role' => 'supporter']);

        $stmt = $db->prepare('DELETE FROM supporters WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}