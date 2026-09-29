<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

## Módulo AgrIoT: usuarios y dashboard

Configura `BREVO_API_KEY` y `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` en el entorno del backend antes de crear usuarios. Ejecuta `php artisan migrate` para añadir `rol`, `must_change_password` y la tabla `cultivos`; las cuentas existentes se conservan con rol `admin`, y el registro público crea cuentas `user`.

Rutas añadidas:

- `POST /api/admin/users` (Bearer Sanctum, administrador): `nombre`, `apellido`, `correo`. Crea un operario con contraseña temporal y la envía por Brevo.
- `PUT /api/admin/users/{user}/cultivos` (Bearer Sanctum, administrador): `cultivo_ids: number[]` reasigna cultivos al usuario indicado.
- `POST /api/password/change-initial` (Bearer Sanctum): `password_actual`, `password`, `password_confirmation`.
- `GET /api/usuario/dashboard` (Bearer Sanctum): devuelve únicamente cultivos del usuario autenticado.
- `POST /api/logout` (Bearer Sanctum): revoca el token actual.
- `GET /api/cultivos` y `GET /api/cultivos/{id}` (Bearer Sanctum): filtran automáticamente por usuario para `user`; `admin` y `asistente` pueden consultar todos.
- `GET /api/cultivos/mapa` (Bearer Sanctum): devuelve cultivos geolocalizados con usuario, variedad y nodo, aplicando el mismo filtro de rol.
- `POST /api/cultivos` (Bearer Sanctum, `admin` o `asistente`): crea un cultivo con `nombre`, `user_id`, `device_id`, `fecha_siembra`, `fecha_estimada_cosecha`, `estado_actual`, `ubicacion` y `observaciones`.
- `PUT /api/cultivos/{id}` y `DELETE /api/cultivos/{id}` (Bearer Sanctum, `admin` o `asistente`): actualiza o elimina el cultivo.
- `GET /api/admin/usuarios` (Bearer Sanctum, `admin` o `asistente`): lista operarios para el formulario de asignación.

En React están las rutas `/admin/usuarios`, `/cambiar-password`, `/dashboard/usuario`, `/mapa` y `/cultivos/:id`. Define `REACT_APP_API_BASE_URL` en el frontend si la API no está en el host predeterminado. La sub-dashboard simula las lecturas del nodo hasta integrar el hardware.

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
