<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database\DatabaseManager;
use PDO;
use RuntimeException;

final class RegisterInterestService
{
    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? DatabaseManager::connection('mrp_auth');
    }

    /**
     * @param array<string, string> $input
     * @return array{success: bool, errors: array<string, string>}
     */
    public function create(array $input): array
    {
        $errors = $this->validate($input);
        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $this->ensureTable();

            $stmt = $this->connection->prepare('INSERT INTO public.registro_interes_mrp (empresa, nombre, email, comentarios, created_at) VALUES (:empresa, :nombre, :email, :comentarios, NOW())');
            $stmt->execute([
                'empresa' => trim($input['empresa']),
                'nombre' => trim($input['nombre']),
                'email' => strtolower(trim($input['email'])),
                'comentarios' => trim($input['comentarios']),
            ]);

            return ['success' => true, 'errors' => []];
        } catch (\Throwable $exception) {
            throw new RuntimeException('No se pudo registrar la solicitud de alta. Intenta nuevamente.', 0, $exception);
        }
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function validate(array $input): array
    {
        $errors = [];

        $empresa = trim($input['empresa'] ?? '');
        $nombre = trim($input['nombre'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $comentarios = trim($input['comentarios'] ?? '');

        if ($empresa === '') {
            $errors['empresa'] = 'La empresa es obligatoria.';
        }

        if ($nombre === '') {
            $errors['nombre'] = 'El nombre es obligatorio.';
        }

        if ($email === '') {
            $errors['email'] = 'El email es obligatorio.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'El email no es valido.';
        }

        if ($comentarios === '') {
            $errors['comentarios'] = 'Describe brevemente que quieres resolver.';
        }

        if (mb_strlen($comentarios) > 1500) {
            $errors['comentarios'] = 'El comentario no puede superar 1500 caracteres.';
        }

        return $errors;
    }

    private function ensureTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS public.registro_interes_mrp (
                id BIGSERIAL PRIMARY KEY,
                empresa VARCHAR(160) NOT NULL,
                nombre VARCHAR(160) NOT NULL,
                email VARCHAR(190) NOT NULL,
                comentarios TEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT NOW()
            )
        ";

        $this->connection->exec($sql);
    }
}
