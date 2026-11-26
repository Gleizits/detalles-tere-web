# EXPORTAR — llevar el proyecto a otra PC en un .zip

> **Nota:** esta guía describe la versión local PHP/Java con SQL Server. El despliegue actual de la rama `DB` usa **TypeScript + Express en Vercel y PostgreSQL en Neon**; consulta [`HOSTING.md`](HOSTING.md).

Guía para copiar **DetallesTereWeb** a otra computadora **sin containers ni
Docker**: solo ZIP + SQL Server 2019 + XAMPP (Tomcat 8.5) + JDK, con los
**mismos puertos** (8080 web, 8443 HTTPS, 1433 SQL Server) que se usan en la
PC de desarrollo.

---

## 1. Qué llevar en el .zip

Desde la raíz del repo, comprime **todo excepto** los artefactos que se
regeneran o son locales:

```
✅ Incluir
   DetallesTereWeb/src/          código Java
   DetallesTereWeb/web/          JSP, CSS, WEB-INF/lib (JARs), context.xml
   DetallesTereWeb/nbproject/    configuración NetBeans (sin /private)
   DetallesTereWeb/db/           scripts SQL 01 → 04
   DetallesTereWeb/herramientas/lib/   servlet-api.jar y jsp-api.jar (compilación)
   README.md / EXPORTAR.md / Requerimientos/

❌ Excluir
   DetallesTereWeb/build/  y  DetallesTereWeb/dist/   (se regeneran con Ant)
   .git/                                            (si solo llevas el código)
   node_modules/  .playwright-cli/  herramientas/node_modules/
   nbproject/private/                               (rutas privadas de tu PC)
```

> El .zip **no** lleva credenciales: `context.xml` solo tiene
> `${DB_URL}/${DB_USER}/${DB_PASSWORD}`. Las credenciales se configuran en la
> PC destino (paso 4).

---

## 2. Prerrequisitos en la PC destino

| Componente | Versión | Notas |
|---|---|---|
| SQL Server 2019 | Developer / Express / Standard | Puerto **1433**, autenticación mixta |
| XAMPP (Tomcat) | 8.5 (el que trae XAMPP) | Puerto **8080** (HTTP) y **8443** (HTTPS) |
| JDK | 21 o superior para compilar · 25 recomendado para ejecutar | `mssql-jdbc 12.8` exige Java 11+; el proyecto compila con `javac.source/target=21` |
| NetBeans *o* Apache Ant | cualquiera de los dos | para generar el WAR (paso 5) |

**No hace falta** instalar el driver de SQL Server aparte: `mssql-jdbc-12.8.1.jre11.jar`
ya viene en `DetallesTereWeb/web/WEB-INF/lib/` y Tomcat lo carga desde ahí.

---

## 3. Base de datos (una sola vez)

### 3.1 Habilitar SQL Server para conexiones locales

1. **SQL Server Configuration Manager → SQL Server Network Configuration →
   Protocols for SQLSERVER2019 → TCP/IP → Enabled** (y el puerto **1433**
   en la pestaña IPAll).
2. **SQL Server Services → reiniciar** el servicio SQL Server.
3. En SSMS: clic derecho al servidor → *Properties → Security →
   Server authentication*: **SQL Server and Windows Authentication mode**.

### 3.2 Usuario de base de datos (la otra PC no tiene `sa`)

La PC destino **no** tiene configurada la cuenta `sa`. Elige **una** de estas
dos opciones:

**Opción A — crear un login dedicado (recomendada):** ejecutar en SSMS
(con una cuenta que sí tenga permisos, p. ej. la del dueño de la instancia):

```sql
-- Login nuevo para la aplicación
CREATE LOGIN detallestere WITH PASSWORD = 'CambiaEstaClave_2026';
GO
```

**Opción B — habilitar `sa`:** en SSMS → *Security → Logins → sa* →
*Properties → Status → Login: Enabled* y definirle contraseña; además
`Server authentication` debe estar en modo mixto (paso 3.1).

### 3.3 Crear el esquema y los datos

Ejecutar **en este orden** (en SSMS o `sqlcmd -S localhost -U <login> -P <clave> -i archivo.sql`),
con la sesión apuntando a la base `master` para que el script 01 cree la BD:

| # | Script | Qué hace |
|---|---|---|
| 1 | `DetallesTereWeb/db/01_schema_DetallesTereDB.sql` | Crea la BD `DetallesTereDB` (collation `Modern_Spanish_CI_AI`) y las **22 tablas** |
| 2 | `DetallesTereWeb/db/02_seed_DetallesTereDB.sql` | Inserta el usuario **admin** (hash BCrypt, cost 10) y la categoría por defecto |
| 3 | `DetallesTereWeb/db/03_alter_fase1_inventario.sql` | Migración Fase 1 (inventario) |
| 4 | `DetallesTereWeb/db/04_alter_fase6_credito.sql` | Migración Fase 6 (crédito) |

Después, otorgar permisos al login sobre la base (Opción A):

```sql
USE DetallesTereDB;
CREATE USER detallestere FOR LOGIN detallestere;
ALTER ROLE db_datareader ADD MEMBER detallestere;
ALTER ROLE db_datawriter ADD MEMBER detallestere;
-- El seed y los ALTER ejecutan DDL; si fallan, usar también:
-- ALTER ROLE db_ddladmin ADD MEMBER detallestere;
GO
```

> **Usuario admin inicial:** el seed solo inserta el usuario `admin` con su
> contraseña en **hash BCrypt** (no está en claro en el repo). Si no la
> recuerdas, genera un hash nuevo con jBCrypt y actualízalo:
>
> ```sql
> -- (generar el hash con la clase BCrypt del JAR jBCrypt-0.4.jar)
> UPDATE dbo.USUARIO
>    SET CONTRASENA = '<hash-bcrypt-nuevo>'
>  WHERE USERNAME = 'admin';
> ```
>
> No existe usuario `empleado` en el seed: se crean desde la pantalla de
> Usuarios con rol `empleado` (solo en la PC de desarrollo estaba el de prueba).

---

## 4. Configurar Tomcat en la PC destino

Crear/editar el archivo **`C:\xampp\tomcat\bin\setenv.bat`** (si no existe,
crearlo). Es el **único** lugar donde van las credenciales:

```bat
@echo off
rem Ruta real del JDK en la PC destino (ajustar)
set "JRE_HOME=C:\Program Files\Eclipse Adoptium\jdk-25.0.5.8-hotspot"

rem Credenciales de BD como propiedades del proceso de Tomcat.
rem context.xml usa ${DB_URL}/${DB_USER}/${DB_PASSWORD}; nada va en el repo.
set "CATALINA_OPTS=%CATALINA_OPTS% -DDB_URL=jdbc:sqlserver://localhost:1433;databaseName=DetallesTereDB;encrypt=true;trustServerCertificate=true -DDB_USER=detallestere -DDB_PASSWORD=CambiaEstaClave_2026"
```

Ajustar:

- `DB_USER` / `DB_PASSWORD` = el login creado en el paso 3.2.
- `JRE_HOME` = carpeta del JDK **21+** instalado (el `java` del PATH en muchas
  PCs es el 1.8 y Tomcat fallaría al arrancar el JDBC).
- Si SQL Server está en **otra máquina**, cambiar `localhost` por su IP en `DB_URL`.

`web/META-INF/context.xml` **no se toca**: ya referencia esas tres propiedades.

---

## 5. Compilar y desplegar

### Opción A — desde NetBeans (igual que en la PC de desarrollo)

1. *File → Open Project* → seleccionar la carpeta **`DetallesTereWeb`**
   (la que contiene `nbproject/`).
2. Asignar el servidor Tomcat de XAMPP y un JDK 21+ (*Tools → Servers*).
3. *Clean and Build* → genera `DetallesTereWeb/dist/DetallesTereWeb.war`.

### Opción B — desde consola con Ant (sin NetBeans)

El proyecto **no** trae `nbproject/private/` (rutas privadas, no viajan en el
.zip), así que Ant necesita que se le indiquen tres propiedades. Ant viene
incluido con NetBeans (`...\Apache NetBeans\extide\ant\bin\ant.bat`):

```powershell
cd DetallesTereWeb

$NB   = "C:\Program Files\Apache NetBeans"   # ruta de NetBeans en la PC destino
$ANT  = "$NB\extide\ant\bin\ant.bat"

& $ANT `
  "-Dj2ee.server.home=C:\xampp\tomcat" `
  "-Dlibs.CopyLibs.classpath=$NB\java\ant\extra\org-netbeans-modules-java-j2seproject-copylibstask.jar" `
  "-Dlibs.jstl.classpath=$NB\enterprise\modules\ext\jstl-impl.jar;$NB\enterprise\modules\ext\jstl-api.jar" `
  -f nbproject\build-impl.xml dist
```

Resultado esperado: `BUILD SUCCESSFUL` y el WAR en
`DetallesTereWeb/dist/DetallesTereWeb.war` (~8.7 MB).

> **Comando verificado** el 2026-11-22 sobre una copia limpia del repo (sin
> `nbproject/private/`, sin `build/` ni `dist/`): compila con JDK 25 y Ant de
> NetBeans 31. Si aparece `The Java EE server classpath is not correctly set
> up` falta el `-Dj2ee.server.home`; si dice `Could not find file
> ${libs.jstl.classpath}` o `copylibstask.jar ... setup the property manually`,
> faltan los otros dos `-D`.

### Desplegar

Copiar el WAR generado a la carpeta de aplicaciones de Tomcat:

```powershell
Copy-Item dist\DetallesTereWeb.war C:\xampp\tomcat\webapps\
```

Y arrancar Tomcat (si `startup.bat` falla por `CATALINA_HOME`):

```powershell
$env:CATALINA_HOME='C:\xampp\tomcat'
C:\xampp\tomcat\bin\startup.bat
```

> El contexto se llama **`/DetallesTereWeb`** porque ese es el nombre del WAR;
> no renombrar el archivo. **`setenv.bat` solo se lee al arrancar Tomcat**: si
> cambias credenciales, reinicia (no basta con redeploy del WAR).
> Si Tomcat no se detiene con `shutdown.bat`, terminar el proceso `java` a mano.

---

## 6. Puertos (iguales a los de desarrollo)

| Servicio | Puerto | Archivo |
|---|---|---|
| Tomcat HTTP | **8080** | `C:\xampp\tomcat\conf\server.xml` (`<Connector port="8080">`) |
| Tomcat HTTPS | **8443** | `conf\server.xml` (`<Connector port="8443">`) |
| SQL Server | **1433** | SQL Server Configuration Manager → TCP/IP |

Si 8080 está ocupado en la PC destino, cambiar el puerto en `server.xml` y
usar la URL nueva (`http://localhost:8081/DetallesTereWeb/...`); en la
guía se asume **8080** como en el desarrollo.

---

## 7. Checklist de verificación

1. ✅ SQL Server responde: `telnet localhost 1433` (o `Test-NetConnection localhost -Port 1433`).
2. ✅ Tomcat arrancó sin errores en `C:\xampp\tomcat\logs\catalina.out` (o `catalina.<fecha>.log`).
3. ✅ `http://localhost:8080/DetallesTereWeb/` redirige al login.
4. ✅ Login con el usuario `admin` → entra al panel lateral (22 tablas visibles en el menú según permisos).
5. ✅ Abrir Caja, Registrar venta, Inventario y Reportes sin error de BD.
6. ✅ Si algo falla: revisar `logs/localhost.<fecha>.log` → el error típico es
   `Cannot create PoolableConnectionFactory` = credenciales de `setenv.bat`
   incorrectas o TCP/IP de SQL Server deshabilitado.

---

## 8. Resumen de una migración limpia

```
1. Descomprimir el .zip en, p. ej., C:\proyectos\detalles-tere-web
2. Instalar XAMPP (Tomcat) + JDK 21/25 + SQL Server 2019
3. Habilitar TCP/IP 1433 + autenticación mixta (reiniciar SQL Server)
4. Crear login de BD (detallestere) y ejecutar db/01 → 02 → 03 → 04
5. Crear C:\xampp\tomcat\bin\setenv.bat con JRE_HOME y DB_URL/DB_USER/DB_PASSWORD
6. Compilar (NetBeans o ant dist) y copiar DetallesTereWeb.war a webapps\
7. startup.bat → http://localhost:8080/DetallesTereWeb/
```
