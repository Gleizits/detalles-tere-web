<?php

namespace Dao;

use Modelo\Usuario;
use PDO;
use ConexionDB;
use RuntimeException;
use InvalidArgumentException;

/**
 * Acceso a datos de USUARIO. Login por username + BCrypt.
 * password_verify() soporta hashes $2a$ generados por jBCrypt.
 */
class UsuarioDAO
{
    private const SQL_LOGIN =
        "SELECT ID_USUARIO, USERNAME, NOMBRE, APELLIDO, ROL, CONTRASENA "
        . "FROM dbo.USUARIO WHERE USERNAME = ? AND ESTADO = 1";

    private static ?string $hashDummy = null;

    /** Hash ficticio para igualar el tiempo cuando el usuario no existe. */
    private static function hashDummy(): string
    {
        if (self::$hashDummy === null) {
            self::$hashDummy = password_hash('tiempo-de-respuesta-dummy', PASSWORD_BCRYPT);
        }
        return self::$hashDummy;
    }

    /** Valida credenciales. Devuelve el Usuario o null si no coinciden. */
    public function login(?string $username, ?string $password): ?Usuario
    {
        if ($username === null || $password === null) {
            return null;
        }

        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_LOGIN);
        $ps->execute([trim($username)]);
        $fila = $ps->fetch();

        if ($fila !== false) {
            if (password_verify($password, (string) $fila['CONTRASENA'])) {
                return new Usuario(
                    (int) $fila['ID_USUARIO'],
                    (string) $fila['USERNAME'],
                    (string) $fila['NOMBRE'],
                    (string) $fila['APELLIDO'],
                    (string) $fila['ROL']
                );
            }
            return null;
        }

        // Usuario inexistente: compara contra hash ficticio para igualar tiempo.
        password_verify($password, self::hashDummy());
        return null;
    }

    /** @return Usuario[] */
    public function listar(): array
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(
            "SELECT ID_USUARIO, USERNAME, NOMBRE, APELLIDO, CORREO, ROL, ESTADO "
            . "FROM dbo.USUARIO ORDER BY APELLIDO, NOMBRE"
        );
        $ps->execute();

        $usuarios = [];
        while ($fila = $ps->fetch()) {
            $usuarios[] = $this->mapear($fila);
        }
        return $usuarios;
    }

    public function crear(Usuario $usuario, string $password): void
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(
            "INSERT INTO dbo.USUARIO (USERNAME, NOMBRE, APELLIDO, CORREO, CONTRASENA, ROL, ESTADO) "
            . "VALUES (?, ?, ?, ?, ?, ?, 1)"
        );
        try {
            $ps->execute([
                $usuario->username,
                $usuario->nombre,
                $usuario->apellido,
                $usuario->correo !== null && $usuario->correo !== '' ? $usuario->correo : null,
                password_hash($password, PASSWORD_BCRYPT),
                $usuario->rol,
            ]);
        } catch (\PDOException $e) {
            throw new RuntimeException(
                'No se pudo crear el usuario. Verifica que usuario y correo no estén repetidos.',
                0,
                $e
            );
        }
    }

    public function actualizar(Usuario $usuario, ?string $password): void
    {
        // Protege que siempre quede al menos un admin activo.
        $adminActual = $this->buscarPorId($usuario->id);
        $quedaAdmin = strtolower($usuario->rol) === 'admin' && $usuario->estado;
        $eraAdminActivo = $adminActual !== null && $adminActual->esAdmin() && $adminActual->estado;
        if ($eraAdminActivo && !$quedaAdmin && $this->contarAdministradoresActivos() <= 1) {
            throw new InvalidArgumentException('Debe permanecer al menos un administrador activo.');
        }

        $cn = ConexionDB::obtener();
        if ($password !== null && $password !== '') {
            $sql = "UPDATE dbo.USUARIO SET USERNAME=?, NOMBRE=?, APELLIDO=?, CORREO=?, ROL=?, ESTADO=?, CONTRASENA=? WHERE ID_USUARIO=?";
            $params = [
                $usuario->username,
                $usuario->nombre,
                $usuario->apellido,
                $usuario->correo !== null && $usuario->correo !== '' ? $usuario->correo : null,
                $usuario->rol,
                $usuario->estado ? 1 : 0,
                password_hash($password, PASSWORD_BCRYPT),
                $usuario->id,
            ];
        } else {
            $sql = "UPDATE dbo.USUARIO SET USERNAME=?, NOMBRE=?, APELLIDO=?, CORREO=?, ROL=?, ESTADO=? WHERE ID_USUARIO=?";
            $params = [
                $usuario->username,
                $usuario->nombre,
                $usuario->apellido,
                $usuario->correo !== null && $usuario->correo !== '' ? $usuario->correo : null,
                $usuario->rol,
                $usuario->estado ? 1 : 0,
                $usuario->id,
            ];
        }

        try {
            $cn->prepare($sql)->execute($params);
        } catch (\PDOException $e) {
            throw new RuntimeException(
                'No se pudo actualizar el usuario. Verifica que usuario y correo no estén repetidos.',
                0,
                $e
            );
        }
    }

    public function buscarPorId(int $id): ?Usuario
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(
            "SELECT ID_USUARIO, USERNAME, NOMBRE, APELLIDO, CORREO, ROL, ESTADO FROM dbo.USUARIO WHERE ID_USUARIO = ?"
        );
        $ps->execute([$id]);
        $fila = $ps->fetch();
        return $fila === false ? null : $this->mapear($fila);
    }

    private function contarAdministradoresActivos(): int
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare("SELECT COUNT(*) FROM dbo.USUARIO WHERE ROL = 'admin' AND ESTADO = 1");
        $ps->execute();
        return (int) $ps->fetchColumn();
    }

    private function mapear(array $fila): Usuario
    {
        $u = new Usuario(
            (int) $fila['ID_USUARIO'],
            (string) $fila['USERNAME'],
            (string) $fila['NOMBRE'],
            (string) $fila['APELLIDO'],
            (string) $fila['ROL'],
            $fila['CORREO'] ?? null,
            (bool) $fila['ESTADO']
        );
        return $u;
    }
}
