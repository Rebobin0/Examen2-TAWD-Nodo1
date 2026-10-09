# Banco Central · Panel de administración (Nodo 1)

Panel en Laravel para los administradores del banco. No tiene base de datos propia:
todo vive en Supabase y el panel lo consume por HTTP.

- **Login:** Supabase Auth (correo y contraseña). Solo entran usuarios registrados en `public.admins`.
- **Operaciones:** funciones `admin_*` de PostgreSQL llamadas por `POST /rest/v1/rpc/...` con el JWT del administrador.

## Funcionalidades

| Requisito del examen | Dónde está |
|---|---|
| Crear sucursales y cajeros + API Key | Sucursales y cajeros > Nueva sucursal / Nuevo cajero. El API Key se muestra una sola vez; se puede regenerar. |
| Efectivo real disponible | Columna "Efectivo disponible" y formulario Editar. |
| Designar responsables | Formulario Editar (campo Responsable). |
| Historial global por sucursal o cajero | Historial, con filtros por nodo, tipo, cuenta y fechas; exporta CSV. |
| Reportes administrativos | Reportes: resumen del periodo, por nodo y por día; CSV e impresión/PDF. |

## Requisitos

- PHP 8.3 o superior con las extensiones habituales de Laravel (curl, mbstring, xml, zip)
- Composer 2
- Proyecto de Supabase con `banco_central.sql` y `banco_central_02_panel.sql` ejecutados, en ese orden

## Instalación local

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Editar `.env`:

```
SUPABASE_URL=https://TU-PROYECTO.supabase.co
SUPABASE_ANON_KEY=la clave "anon" o "publishable" (Project Settings > API)
```

No usar la clave `service_role`. No hace falta `php artisan migrate`: sesiones y caché usan archivos.

```bash
php artisan serve
```

Abrir http://localhost:8000 e iniciar sesión con el administrador creado en Supabase.

## Estructura

| Archivo | Qué hace |
|---|---|
| `app/Services/Supabase.php` | Cliente HTTP: login, renovación de token y llamadas RPC |
| `app/Http/Middleware/SupabaseAuth.php` | Protege las rutas y renueva el token antes de que caduque |
| `app/Http/Controllers/NodoController.php` | Sucursales y cajeros, API Key, efectivo, responsables |
| `app/Http/Controllers/TransaccionController.php` | Historial global y CSV |
| `app/Http/Controllers/ReporteController.php` | Reportes y CSV |
| `app/Exceptions/SupabaseException.php` | Traduce los errores de Supabase a mensajes |
| `routes/web.php` | Rutas |
| `resources/views/` | Pantallas (Blade) |
| `public/css/app.css` | Estilos (sin Vite ni npm) |

## Despliegue en Render

1. Subir este proyecto a un repositorio de GitHub.
2. En Render: New > Web Service > conectar el repositorio > Runtime **Docker**.
3. Variables de entorno:
   - `APP_KEY` (salida de `php artisan key:generate --show`)
   - `APP_ENV=production`, `APP_DEBUG=false`
   - `APP_URL` con la URL que asigne Render
   - `SUPABASE_URL`, `SUPABASE_ANON_KEY`
   - `SESSION_DRIVER=file`, `CACHE_STORE=file`, `LOG_CHANNEL=stderr`
4. Con Auto-Deploy activo, cada `git push` a `main` vuelve a desplegar.

En el plan gratuito el servicio se duerme: abrirlo unos minutos antes de la demo.
Al redesplegar se cierran las sesiones abiertas (se guardan en archivos del contenedor).
