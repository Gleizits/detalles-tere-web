<?php

/**
 * Enrutador mínimo (front controller).
 *
 * Sustituye a las anotaciones @WebServlet del proyecto Java. Cada ruta se
 * asocia a un controlador y a sus métodos GET/POST; el resto de la URL
 * (acciones concretas) se resuelve dentro de cada controlador con el
 * parámetro "accion", igual que hacían los servlets.
 */
final class Enrutador
{
    /** @var array<string, array{get?:array{0:class-string,1:string}, post?:array{0:class-string,1:string}}> */
    private array $rutas = [];

    /** @var array<int, string> Rutas que solo requieren sesión (sin admin). */
    private array $publicas = [];

    /** @var array<int, string> Rutas que exigen rol administrador. */
    private array $soloAdmin = [];

    public function get(string $ruta, string $controlador, string $metodo): void
    {
        $this->registrar('get', $ruta, $controlador, $metodo);
    }

    public function post(string $ruta, string $controlador, string $metodo): void
    {
        $this->registrar('post', $ruta, $controlador, $metodo);
    }

    private function registrar(string $metodoHttp, string $ruta, string $controlador, string $metodo): void
    {
        $this->rutas[$ruta][$metodoHttp] = [$controlador, $metodo];
    }

    /** Rutas accesibles sin iniciar sesión. */
    public function publica(string $ruta): void
    {
        $this->publicas[] = $ruta;
    }

    /** Rutas reservadas al administrador (como RequiereAdministradorFilter). */
    public function soloAdmin(string $ruta): void
    {
        $this->soloAdmin[] = $ruta;
    }

    public function esPublica(string $ruta): bool
    {
        return in_array($ruta, $this->publicas, true);
    }

    public function requiereAdmin(string $ruta): bool
    {
        return in_array($ruta, $this->soloAdmin, true);
    }

    /** @return array{0:class-string,1:string}|null */
    public function resolver(string $ruta, string $metodoHttp): ?array
    {
        $metodoHttp = strtolower($metodoHttp);
        $definida = $this->rutas[$ruta] ?? null;

        if ($definida === null) {
            return null;
        }
        if (isset($definida[$metodoHttp])) {
            return $definida[$metodoHttp];
        }

        // La ruta existe pero con otro verbo (p. ej. GET a /logout).
        throw new ExcepcionHttp(405, 'Método no permitido para esta ruta.');
    }
}
