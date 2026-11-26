<?php

namespace Dao;

use Modelo\Cliente;
use PDO;
use ConexionDB;

/**
 * Acceso a datos de CLIENTE (solo NOMBRE y NUM_WSP).
 * En pedidos se busca por celular y, si no existe, se crea.
 */
class ClienteDAO
{
    private const SQL_POR_WSP =
        "SELECT ID_CLIENTE, NOMBRE, NUM_WSP FROM dbo.CLIENTE WHERE NUM_WSP = ?";

    private const SQL_INSERT =
        "INSERT INTO dbo.CLIENTE (NOMBRE, NUM_WSP) VALUES (?, ?)";

    /** Busca un cliente por número de WhatsApp/celular, o null si no existe. */
    public function buscarPorWsp(string $numWsp): ?Cliente
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_POR_WSP);
        $ps->execute([$numWsp]);
        $fila = $ps->fetch();

        if ($fila === false) {
            return null;
        }
        $c = new Cliente();
        $c->idCliente = (int) $fila['ID_CLIENTE'];
        $c->nombre = $fila['NOMBRE'];
        $c->numWsp = $fila['NUM_WSP'];
        return $c;
    }

    /**
     * Devuelve el id del cliente con ese celular: lo usa si existe,
     * o lo crea si no. Si existe pero con otro nombre, no lo modifica.
     */
    public function buscarOCrear(string $numWsp, string $nombre): int
    {
        $existente = $this->buscarPorWsp($numWsp);
        if ($existente !== null) {
            return $existente->idCliente;
        }

        $id = ConexionDB::insertarId(self::SQL_INSERT, [$nombre, $numWsp]);
        if ($id <= 0) {
            throw new \PDOException('No se pudo crear el cliente');
        }
        return $id;
    }
}
