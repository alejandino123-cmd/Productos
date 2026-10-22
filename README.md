# Productos – Sistema con inicio de sesión seguro

## Introducción

Este proyecto es un sistema de administración de productos con un inicio de sesión de dos pasos.
Está dividido en dos partes:

- **backend**: la API (Laravel), que guarda los datos y valida a los usuarios.
- **frontend**: la pantalla que ve el usuario (Vue).

Hay dos tipos de usuario:

- **Superadmin**: entra directo con su correo y contraseña.
- **Usuario normal**: escribe su correo y contraseña, y luego confirma que es él por **una de dos formas**:
  - un **código de 6 dígitos enviado a su correo**, o
  - un **código QR** que escanea con la aplicación **Google Authenticator**.

## Tecnologías

- Backend: Laravel, Laravel Sanctum (sesiones con token), PostgreSQL, documentación con Swagger.
- Frontend: Vue 3 y Vite.

## Requisitos

Tener instalado en la computadora:

- PHP 8.2 o superior (con la extensión `pdo_pgsql`) y Composer
- Node.js 18 o superior
- PostgreSQL

## Instalación

### 1. Backend

Primero crea una base de datos vacía en PostgreSQL (por ejemplo `laravel_db`). Luego:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Abre el archivo `backend/.env` y escribe los datos de tu base de datos:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel_db
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

Después crea las tablas y los usuarios de prueba, y enciende el servidor:

```bash
php artisan migrate --seed
php artisan serve --port=8001
```

### 2. Frontend

En otra terminal:

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

Abre en el navegador la dirección que muestra la terminal (normalmente `http://localhost:5173`).

## Usuarios de prueba

Estos usuarios se crean solos con `php artisan migrate --seed`. Son solo para pruebas locales.

| Tipo | Correo | Contraseña |
|---|---|---|
| Superadmin | `admin@uls.com` | `Admin123*` |
| Usuario normal | `alejandino123@gmail.com` | `Usuario123*` |

## Cómo probar el inicio de sesión

1. **Superadmin**: entra con su correo y contraseña. Pasa directo al panel.
2. **Usuario normal**: tras la contraseña aparece la pantalla "Verifica tu identidad" con dos opciones:
   - **Código por correo**: en modo de pruebas (`APP_ENV=local`, que es el valor por defecto) el código aparece en un cuadro amarillo en la pantalla, así que no hace falta configurar ningún correo.
   - **Google Authenticator (QR)**: la primera vez se muestra un código QR para escanear con la aplicación. Después solo se pide el código de 6 dígitos que muestra la aplicación.

## Documentación de la API

Con el backend encendido, la documentación Swagger está en:

`http://localhost:8001/api/documentation`

## Conclusiones

El sistema separa dos niveles de acceso: el superadmin entra directo y el usuario normal debe
confirmar su identidad en un segundo paso, con la opción de elegir entre correo o código QR.
Mientras el usuario no termina el segundo paso no recibe ninguna sesión, y los intentos de
verificación tienen un límite por minuto para evitar que alguien adivine el código.
Para pasar a producción hay que configurar un servicio de correo real y cambiar `APP_ENV` a `production`.