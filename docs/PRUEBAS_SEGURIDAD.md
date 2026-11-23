# Pruebas de seguridad (sección 10 del PDF)

Entorno: `https://localhost`. Cuentas ficticias del README. Guarda cada captura en `docs/evidencias/` con el nombre indicado.
Las pruebas automáticas equivalentes están en `tests/Feature/SecurityTest.php` (`php artisan test`).

| # | Prueba | Cómo hacerla | Resultado esperado | Captura | ¿OK? |
|---|---|---|---|---|---|
| 1 | Contraseña incorrecta | Login con `basico300@haki.test` y clave errónea | «Credenciales incorrectas», sin acceso | `01_clave_incorrecta.png` | ☐ |
| 2 | Tres intentos fallidos | Repite 3 veces la prueba 1 | Contador de 3 minutos; Bitácora muestra «Cuenta bloqueada» | `02_bloqueo.png` | ☐ |
| 3 | Nueva sesión durante el bloqueo | Abre otra ventana privada e intenta con la clave **correcta** | Sigue bloqueado | `03_sesion_nueva.png` | ☐ |
| 4 | Clave correcta tras el bloqueo | Espera 3 min e inicia sesión | Acceso permitido | `04_tras_bloqueo.png` | ☐ |
| 5 | 300 intenta administración | Como 300 escribe `/admin/usuarios` | Acceso denegado / vuelve al portal; evento «Acceso no autorizado» en Bitácora | `05_300_admin.png` | ☐ |
| 6 | 499 intenta crear usuario | Como 499 no aparece «+ Nuevo usuario». La suite verifica la autorización. | 403 | `06_499_crear.png` | ☐ |
| 7 | 300 modifica el ID de otro | Como 300, en DevTools reenvía `PATCH /admin/usuarios/1/nivel` | 403 | `07_300_id_ajeno.png` | ☐ |
| 8 | Caracteres SQL | Ejecuta la prueba automatizada `test_caracteres_sql_no_alteran_la_autenticacion` | Sin acceso inesperado | `08_sql.png` | ☐ |
| 9 | Escape HTML | Comprueba el renderizado con la prueba `test_html_y_javascript_se_escapan` | El nombre se muestra como texto | `09_xss.png` | ☐ |
| 10 | Sin token CSRF | En DevTools borra el campo `_token` y envía un formulario | Error 419 | `10_csrf.png` | ☐ |
| 11 | Reutilizar sesión cerrada | Cierra sesión y usa «atrás» o copia la cookie anterior | Redirige al login | `11_sesion_cerrada.png` | ☐ |
| 12 | Cambio de contraseña | Cambia la clave en dos navegadores a la vez | Hash nuevo; la otra sesión queda invalidada | `12_cambio_clave.png` | ☐ |
| 13 | Restauración de backup | Ejecuta los comandos de respaldo y restauración del README | Tablas recuperadas y cero referencias huérfanas | `13_restore.png` | ☐ |
| 14 | Recuperación con enlace | Solicita en `/recuperar-clave`, el 500 autoriza, usa el enlace dos veces | Primera vez cambia la clave; segunda: «Enlace no válido» | `14_enlace.png` | ☐ |
| 15 | Hash solo para 500 | Como 499 no hay botón; como 500 pide tu clave y queda en Bitácora | Evento «Consulta de hash almacenado» | `15_hash.png` | ☐ |
