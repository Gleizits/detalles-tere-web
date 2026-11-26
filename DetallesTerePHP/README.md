# DetallesTere — versión PHP (rama `tpw`)

> Esta carpeta se conserva como referencia de la versión local con SQL Server. La versión desplegable de la rama `DB` es `DetallesTereTS` (TypeScript + Vercel + Neon); consulta `../HOSTING.md`.

Migración del sistema Java Servlet/JSP a **PHP puro MVC sin frameworks**.
Misma base de datos: **SQL Server 2019 Developer Edition · `DetallesTereDB`**.

## Estructura

```
DetallesTerePHP/
├── app/
│   ├── bootstrap.php      # autoload, .env, sesión, errores
│   ├── config.php         # configuración (lee .env)
│   ├── rutas.php          # mapa de rutas
│   ├── controlador/       # ControladorBase + 11 controladores
│   ├── dao/               # acceso a datos (PDO + pdo_sqlsrv)
│   ├── modelo/            # entidades
│   ├── vista/             # vistas PHP (comunes/cabecera.php y pie.php)
│   └── lib/               # Enrutador, Seguridad, Vista, ConexionDB, helpers
├── db/                    # esquema y seed (sin cambios)
├── public/                # DocumentRoot: index.php (front controller), assets
├── Dockerfile             # PHP 8.2 + Apache + ODBC 18 + pdo_sqlsrv
├── docker-compose.yml     # desarrollo local opcional
└── .env.example           # copiar a .env
```

## Puesta en marcha local (XAMPP)

1. Copia `.env.example` a `.env` y ajusta `DB_PASSWORD` (y `DB_SERVER`).
2. **Instala `pdo_sqlsrv` en tu PHP de XAMPP**. No viene incluido:
   - Descarga la extensión `pdo_sqlsrv` para PHP 8.2 (TS/NTS según tu XAMPP)
     desde `microsoft/msphpsql` y el **Microsoft ODBC Driver 17/18**.
   - Añade `extension=pdo_sqlsrv` a `php.ini` y reinicia Apache.
3. Apunta el DocumentRoot de Apache a `DetallesTerePHP/public`.
4. Abre `http://localhost/` y entra con `admin` / `Admin123!` (usuario del seed).

Si no quieres tocar el PHP de XAMPP, usa `docker compose up --build`
(el contenedor ya trae el driver ODBC y `pdo_sqlsrv`).

## Despliegue en Render

Render **no tiene runtime PHP nativo**, por eso se usa Docker.
El blueprint `render.yaml` está en la **raíz del repositorio**.

1. Sube la rama `tpw` a GitHub.
2. En Render → *New* → *Blueprint* → selecciona el repo
   `Gleizits/detalles-tere-web` y la rama `tpw`.
3. Rellena `DB_SERVER` y `DB_PASSWORD` (campos `sync: false`).
   La base de datos **no** se aprovisiona en Render: debe ser tu
   SQL Server 2019 accesible desde Internet (o el mismo servidor actual
   con reglas de firewall que permitan al contenedor conectarse).
4. Render construirá la imagen con `DetallesTerePHP/Dockerfile`,
   contexto `DetallesTerePHP/`, y servirá `public/` por el puerto 80.

> Las credenciales nunca se versionan: `.env` está en `.gitignore` y en
> `.dockerignore`. En Render se inyectan como variables de entorno.

## Seguridad

- Cabeceras CSP, `X-Frame-Options`, `X-Content-Type-Options`, etc.
- Token CSRF en **todos** los formularios POST (comparación `hash_equals`).
- Sesión con `HttpOnly`, `SameSite=Lax`, regeneración del ID tras login.
- Límite de intentos de login por usuario+IP y backoff exponencial.
- Rutas `/ingresos`, `/terminos`, `/reportes`, `/usuarios` solo administrador.
- Consultas siempre preparadas; sin SQL dinámico con datos del usuario.
