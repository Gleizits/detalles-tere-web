<?php

namespace Controlador;

use ConexionDB;
use Dao\UsuarioDAO;
use InvalidArgumentException;
use Modelo\Usuario;
use Throwable;

/**
 * Alta, edición y baja lógica de usuarios (solo admin).
 * Acciones: crear y editar. Al editar al propio usuario se impide la baja.
 *
 * Nota: mientras UsuarioDAO no exponga listar/crear/actualizar (migración en
 * curso), el controlador ejecuta las mismas consultas con la conexión actual.
 * Cuando el DAO las incorpore, se usan desde ahí automáticamente.
 */
final class UsuarioControlador extends ControladorBase
{
    /** GET /usuarios (?nuevo=1 | ?editar=id) */
    public function index(): never
    {
        $datos = $this->listarUsuarios();
        $usuarios = $datos['usuarios'];
        $campos = $datos['campos'];

        $editar = null;
        $camposEditar = $this->camposPorFormulario(false);
        $error = null;

        if (isset($_GET['editar'])) {
            $id = $this->enteroOpcional(valorParam('editar'));
            if ($id === null || $id <= 0) {
                $error = 'El usuario solicitado no es válido.';
            } else {
                foreach ($usuarios as $usuario) {
                    if ($usuario->id === $id) {
                        $editar = $usuario;
                        $camposEditar = $campos[$id] ?? $camposEditar;
                        break;
                    }
                }
            }
        }

        $this->ver('usuarios', 'Usuarios', '/usuarios', [
            'usuarios'      => $usuarios,
            'campos'        => $campos,
            'usuarioEditar' => $editar,
            'camposEditar'  => $camposEditar,
            'nuevo'         => isset($_GET['nuevo']),
            'error'         => $error,
        ]);
    }

    /** POST /usuarios */
    public function procesar(): never
    {
        $accion = valorParam('accion');

        try {
            if ($accion === 'crear') {
                $campos = $this->camposPorFormulario(false);
                $usuario = $this->leerUsuario(false, $campos);
                $this->crearUsuario($usuario, $this->password(true), $campos);
            } elseif ($accion === 'editar') {
                $campos = $this->camposPorFormulario(true);
                $usuario = $this->leerUsuario(true, $campos);
                $this->protegerSesionPropia($usuario, $campos);
                $this->actualizarUsuario($usuario, $this->password(false), $campos);
                $this->actualizarSesionSiCorresponde($usuario);
            } else {
                throw new InvalidArgumentException('Acción no válida.');
            }
        } catch (InvalidArgumentException $e) {
            $this->mostrarConError($e->getMessage());
        } catch (Throwable $e) {
            error_log('Error en el módulo de usuarios: ' . $e->getMessage());
            $this->mostrarConError('No pudimos guardar el usuario. Intenta de nuevo.');
        }

        flash('aviso', 'Usuario guardado.');
        $this->ir('/usuarios');
    }

    private function leerUsuario(bool $edicion, array &$campos): Usuario
    {
        $id = $edicion ? $this->positivo(valorParam('id'), 'Selecciona un usuario válido.') : 0;
        $username = $this->obligatorio(valorParam('username'), 50, 'Indica el usuario.');
        $nombre = $this->obligatorio(valorParam('nombre'), 50, 'Indica el nombre.');
        $apellido = $this->obligatorio(valorParam('apellido'), 50, 'Indica el apellido.');
        $correo = mb_substr(valorParam('correo'), 0, 75);
        $rol = $this->obligatorio(valorParam('rol'), 12, 'Selecciona un rol.');
        if (!in_array($rol, ['admin', 'empleado'], true)) {
            throw new InvalidArgumentException('El rol seleccionado no es válido.');
        }

        $campos['correo'] = $correo === '' ? null : $correo;
        $campos['estado'] = !$edicion || valorParam('estado') === 'on';

        $usuario = new Usuario($id, $username, $nombre, $apellido, $rol);
        $this->asignar($usuario, 'correo', $campos['correo']);
        $this->asignar($usuario, 'estado', $campos['estado']);
        return $usuario;
    }

    private function camposPorFormulario(bool $edicion): array
    {
        $correo = trim(valorParam('correo'));
        return [
            'correo' => $correo === '' ? null : mb_substr($correo, 0, 75),
            'estado' => !$edicion || valorParam('estado') === 'on',
        ];
    }

    private function password(bool $obligatorio): ?string
    {
        $password = $_POST['password'] ?? '';
        $password = is_string($password) ? $password : '';

        if ($password === '') {
            if ($obligatorio) {
                throw new InvalidArgumentException('Indica una contraseña.');
            }
            return null;
        }

        $longitud = mb_strlen($password);
        if ($longitud < 6 || $longitud > 100) {
            throw new InvalidArgumentException(
                'La contraseña debe tener entre 6 y 100 caracteres.'
            );
        }
        return $password;
    }

    private function protegerSesionPropia(Usuario $usuario, array $campos): void
    {
        $actual = $this->usuario();
        if ($actual->id === $usuario->id && !($campos['estado'] ?? true)) {
            throw new InvalidArgumentException('No puedes desactivar tu propio usuario.');
        }
    }

    private function actualizarSesionSiCorresponde(Usuario $actualizado): void
    {
        $actual = $this->usuario();
        if ($actual->id === $actualizado->id) {
            $_SESSION['usuario'] = $actualizado;
        }
    }

    /** Repinta la vista con los datos capturados cuando hay error. */
    private function mostrarConError(string $error): never
    {
        $id = enteroParam('id');
        $editando = $id > 0 || valorParam('accion') === 'editar';
        $capturado = new Usuario(
            $id,
            valorParam('username'),
            valorParam('nombre'),
            valorParam('apellido'),
            valorParam('rol')
        );
        $campos = $this->camposPorFormulario($editando);
        $this->asignar($capturado, 'correo', $campos['correo']);
        $this->asignar($capturado, 'estado', $campos['estado']);

        $lista = $this->listarUsuarios();
        $this->ver('usuarios', 'Usuarios', '/usuarios', [
            'usuarios'      => $lista['usuarios'],
            'campos'        => $lista['campos'],
            'usuarioEditar' => $capturado,
            'camposEditar'  => $campos,
            'nuevo'         => !$editando,
            'error'         => $error,
        ]);
    }

    /** @return array{usuarios:Usuario[], campos:array<int,array{correo:?string,estado:bool}>} */
    private function listarUsuarios(): array
    {
        $dao = new UsuarioDAO();
        if (method_exists($dao, 'listar') && $this->modeloCompleto()) {
            $usuarios = $dao->listar();
            $campos = [];
            foreach ($usuarios as $usuario) {
                $campos[$usuario->id] = [
                    'correo' => $usuario->correo ?? null,
                    'estado' => (bool) $usuario->estado,
                ];
            }
            return ['usuarios' => $usuarios, 'campos' => $campos];
        }

        $ps = ConexionDB::obtener()->query(
            "SELECT ID_USUARIO, USERNAME, NOMBRE, APELLIDO, CORREO, ROL, ESTADO "
            . "FROM dbo.USUARIO ORDER BY APELLIDO, NOMBRE"
        );
        $usuarios = [];
        $campos = [];
        while ($fila = $ps->fetch()) {
            $usuario = new Usuario(
                (int) $fila['ID_USUARIO'],
                (string) $fila['USERNAME'],
                (string) $fila['NOMBRE'],
                (string) $fila['APELLIDO'],
                (string) $fila['ROL']
            );
            $usuarios[] = $usuario;
            $campos[$usuario->id] = [
                'correo' => $fila['CORREO'] !== null ? (string) $fila['CORREO'] : null,
                'estado' => (bool) $fila['ESTADO'],
            ];
        }
        return ['usuarios' => $usuarios, 'campos' => $campos];
    }

    private function crearUsuario(Usuario $usuario, string $password, array $campos): void
    {
        $dao = new UsuarioDAO();
        if (method_exists($dao, 'crear') && $this->modeloCompleto()) {
            $dao->crear($usuario, $password);
            return;
        }

        $sql = "INSERT INTO dbo.USUARIO "
            . "(USERNAME, NOMBRE, APELLIDO, CORREO, CONTRASENA, ROL, ESTADO) "
            . "VALUES (?, ?, ?, ?, ?, ?, 1)";
        try {
            ConexionDB::obtener()->prepare($sql)->execute([
                $usuario->username,
                $usuario->nombre,
                $usuario->apellido,
                $campos['correo'],
                password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]),
                $usuario->rol,
            ]);
        } catch (Throwable $e) {
            throw new \RuntimeException(
                'No se pudo crear el usuario. Verifica que usuario y correo no estén repetidos.',
                0,
                $e
            );
        }
    }

    private function actualizarUsuario(
        Usuario $usuario,
        ?string $password,
        array $campos
    ): void {
        $dao = new UsuarioDAO();
        if (method_exists($dao, 'actualizar') && $this->modeloCompleto()) {
            $dao->actualizar($usuario, $password);
            return;
        }

        $estado = !empty($campos['estado']);
        if ($this->contarAdminsActivos() <= 1 && (!$estado || $usuario->rol !== 'admin')) {
            throw new InvalidArgumentException('Debe permanecer al menos un administrador activo.');
        }

        $sql = "UPDATE dbo.USUARIO SET USERNAME=?, NOMBRE=?, APELLIDO=?, CORREO=?, "
            . "ROL=?, ESTADO=?";
        $params = [
            $usuario->username,
            $usuario->nombre,
            $usuario->apellido,
            $campos['correo'],
            $usuario->rol,
            $estado ? 1 : 0,
        ];
        if ($password !== null) {
            $sql .= ", CONTRASENA=?";
            $params[] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        }
        $sql .= " WHERE ID_USUARIO=?";
        $params[] = $usuario->id;

        try {
            ConexionDB::obtener()->prepare($sql)->execute($params);
        } catch (Throwable $e) {
            throw new \RuntimeException(
                'No se pudo actualizar el usuario. Verifica que usuario y correo no estén repetidos.',
                0,
                $e
            );
        }
    }

    private function contarAdminsActivos(): int
    {
        $ps = ConexionDB::obtener()->query(
            "SELECT COUNT(*) FROM dbo.USUARIO WHERE ROL = 'admin' AND ESTADO = 1"
        );
        return (int) $ps->fetchColumn();
    }

    private function modeloCompleto(): bool
    {
        return property_exists(Usuario::class, 'correo')
            && property_exists(Usuario::class, 'estado');
    }

    private function obligatorio(string $valor, int $maximo, string $mensaje): string
    {
        $resultado = mb_substr(trim($valor), 0, $maximo);
        if ($resultado === '') {
            throw new InvalidArgumentException($mensaje);
        }
        return $resultado;
    }

    private function positivo(string $valor, string $mensaje): int
    {
        if (preg_match('/^\d+$/', $valor) !== 1 || (int) $valor <= 0) {
            throw new InvalidArgumentException($mensaje);
        }
        return (int) $valor;
    }

    private function enteroOpcional(string $valor): ?int
    {
        return preg_match('/^-?\d+$/', trim($valor)) === 1 ? (int) $valor : null;
    }

    /** Evita propiedades dinámicas si el modelo aún no declara el campo. */
    private function asignar(object $destino, string $propiedad, mixed $valor): void
    {
        if (property_exists($destino, $propiedad)) {
            $destino->{$propiedad} = $valor;
        }
    }
}
