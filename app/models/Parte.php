<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Parte extends BaseTenantModel
{
    protected function getTable(): string
    {
        return 'partes';
    }

    public function paginated(int $page = 1, int $perPage = 25, string $search = ''): array
    {
        $fetchAll = $perPage <= 0;
        $offset = $fetchAll ? 0 : ($page - 1) * $perPage;

        if ($search !== '') {
            // 1. Obtener items
            $sql = 'SELECT p.*, tp.nombre AS tipo_nombre, gp.nombre AS grupo_nombre
                FROM partes p
                LEFT JOIN tipos_partes tp ON tp.id = p.id_tipo
                LEFT JOIN grupos_partes gp ON gp.id = p.id_grupo
                WHERE (
                    p.codigo ILIKE ? OR
                    p.detalle ILIKE ? OR
                    EXISTS (
                        SELECT 1 FROM variantes v
                        WHERE v.id_parte = p.id
                        AND (v.codigo_variante ILIKE ? OR v.detalle ILIKE ?)
                    )
                )
                ORDER BY p.id DESC';

            if (!$fetchAll) {
                $sql .= ' LIMIT ? OFFSET ?';
            }

            $stmt = $this->connection->prepare($sql);
            $searchParam = '%' . $search . '%';
            $params = [
                $searchParam,
                $searchParam,
                $searchParam,
                $searchParam,
            ];

            if (!$fetchAll) {
                $params[] = $perPage;
                $params[] = $offset;
            }

            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Obtener total
            $countSql = 'SELECT COUNT(*)
                FROM partes p
                WHERE (
                    p.codigo ILIKE ? OR
                    p.detalle ILIKE ? OR
                    EXISTS (
                        SELECT 1 FROM variantes v
                        WHERE v.id_parte = p.id
                        AND (v.codigo_variante ILIKE ? OR v.detalle ILIKE ?)
                    )
                )';
            $countStmt = $this->connection->prepare($countSql);
            $countStmt->execute([
                $searchParam,
                $searchParam,
                $searchParam,
                $searchParam
            ]);
            $count = (int) $countStmt->fetchColumn();
        } else {
            // 1. Obtener items
            $sql = 'SELECT p.*, tp.nombre AS tipo_nombre, gp.nombre AS grupo_nombre
                FROM partes p
                LEFT JOIN tipos_partes tp ON tp.id = p.id_tipo
                LEFT JOIN grupos_partes gp ON gp.id = p.id_grupo
                ORDER BY p.id DESC';

            if (!$fetchAll) {
                $sql .= ' LIMIT ? OFFSET ?';
            }

            $stmt = $this->connection->prepare($sql);
            $params = [];
            if (!$fetchAll) {
                $params[] = $perPage;
                $params[] = $offset;
            }
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Obtener total
            $count = (int) $this->connection->query('SELECT COUNT(*) FROM partes')->fetchColumn();
        }

        return [
            'items' => $items,
            'total' => $count,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function options(): array
    {
        $stmt = $this->connection->query('SELECT id, codigo, detalle FROM partes ORDER BY codigo');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listAll(): array
    {
        $sql = 'SELECT p.*, tp.nombre AS tipo_nombre, gp.nombre AS grupo_nombre
            FROM partes p
            LEFT JOIN tipos_partes tp ON tp.id = p.id_tipo
            LEFT JOIN grupos_partes gp ON gp.id = p.id_grupo
            ORDER BY p.codigo ASC';
        $stmt = $this->connection->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
