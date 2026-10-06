# SkillSwap — Sistema Combinado

Fusión de **dos versiones de la misma aplicación** en una sola:

- **Base**: el sistema que veníamos trabajando (el de `/var/www/html/skillswap`), que es el más avanzado:
  certificados con evidencia, banco de horas asimétrico, negociación de horas, rate-limit de login, CSRF, etc.
- **Aported**: la versión que trabajó otra persona (`~/Descargas/SkillSwap-main`), de la que se tomaron
  las funciones que faltaban: notificaciones, chat con polling, modalidad de sesión, insignias,
  avatar, constancia de horas imprimible, gráficas y exportación CSV.

**Los dos originales quedaron intactos**: el sistema en producción (`/var/www/html/skillswap`) y la
copia descargada (`~/Descargas/SkillSwap-main`) no se modificaron. Además hay respaldos en
`/tmp/opencode/respaldos/`.

---

## 1. Estructura

```
skillswap-combinado/
├── index.php                      -> redirige a /skillswap/
├── README.md                      -> este documento
├── pruebas/
│   └── combinado_e2e.php          -> 74 pruebas automáticas (requiere el sistema desplegado)
└── skillswap/                     -> la aplicación (copiar como /var/www/html/skillswap)
    ├── admin/       (5 archivos)  -> index, users, categories, reports, export (CSV)
    ├── api/         (2 archivos)  -> notifications.php, messages.php (JSON + CSRF)
    ├── assets/      -> css, js, img/avatars/
    ├── config/      -> db.php
    ├── database/    -> skillswap.sql (12 tablas, coherente) + migraciones
    ├── includes/    -> auth, functions, header, footer
    ├── modules/     -> auth, dashboard, exchanges, matches, messages, profile, ratings, skills
    └── uploads/     -> certificados adjuntos
```

## 2. Qué se agregó (y de dónde vino)

| Funcionalidad | Origen | Dónde quedó en el combinado |
|---|---|---|
| Notificaciones (campana, dropdown, marcar leídas) | SkillSwap-main | `includes/functions.php`, `includes/header.php`, `api/notifications.php` |
| Avisos automáticos: propuesta, aceptar, rechazar, cancelar, confirmar, completar, valorar, mensaje | SkillSwap-main (parcial) | `propose.php`, `accept.php`, `complete.php`, `ratings/submit.php`, `api/messages.php` |
| Chat asíncrono con polling (4 s) y envío sin recargar | SkillSwap-main | `api/messages.php`, `modules/messages/index.php` |
| Modalidad de sesión virtual/presencial (enlace o punto de encuentro) | SkillSwap-main | `propose.php`, `modules/exchanges/index.php`, `admin/reports.php` |
| Insignias (Pionero, Mentor Experimentado, Tutor 5 Estrellas, Máster del Tiempo) | SkillSwap-main | `getUserBadges()`, perfiles propio y público |
| Foto de perfil (avatar) | SkillSwap-main (corregido) | `uploadAvatarImage()`, `modules/profile/edit.php` |
| Biografía + enlaces GitHub/LinkedIn | SkillSwap-main | `modules/profile/edit.php`, perfiles |
| Constancia de horas imprimible (PDF desde el navegador) | SkillSwap-main | `modules/profile/certificate.php` (enlace en el menú de usuario) |
| Gráficas Chart.js en reportes | SkillSwap-main | `admin/reports.php` |
| Exportación CSV de usuarios e intercambios | SkillSwap-main (corregido) | `admin/export.php` |

## 3. Lo que se conservó del sistema base (no se perdió nada)

- Certificados con evidencia (URL + archivo, skill asociada) y gatekeeper `modules/profile/file.php`.
- Banco de horas asimétrico (`proposer_hours` / `acceptor_hours`, decimal) con saldo nunca negativo.
- Negociación de horas (`extend.php`), fecha programada, deduplicación de propuestas.
- Confirmación doble de finalización con transacción y `FOR UPDATE`.
- Rate-limit de login (`login_attempts`), CSRF en todos los formularios y APIs.
- Validación de archivos por contenido (certificados), política de privacidad, UI con temas claro/oscuro.

## 4. Correcciones aplicadas al portsar

| Problema en `SkillSwap-main` | Qué se hizo aquí |
|---|---|
| Su dump SQL no incluía `notifications`, `users.bio/github_url/linkedin_url` ni `exchanges.meeting_*` que su propio código usa | Se creó `database/migrate_combinado.sql` y se regeneró el dump con las 12 tablas ya coherentes |
| Avatar: usaba la extensión enviada por el cliente → un `.php` con contenido JPEG podía quedar ejecutable | `uploadAvatarImage()` valida con `getimagesize()` y **deriva la extensión del contenido** (verificado en las pruebas 30-32) |
| `admin/export.php` consultaba `e.meeting_type`, columna que no existía en su esquema | CSV reescrito con las columnas reales del sistema combinado |
| `api/messages.php` sin validación CSRF | Ahora exige token (por formulario o cabecera) |
| Sin límite de intentos de login | Se mantiene el `login_attempts` del sistema base |
| Avisos de `notifications` sin manejar errores | `createNotification()` captura excepciones para no romper la página |

### Bug real encontrado durante la integración

`modules/exchanges/propose.php`, `modules/messages/index.php` y `modules/ratings/submit.php`
imprimían el HTML **antes** de procesar el POST, por lo que `redirect()` enviaba la cabecera
`Location` cuando el buffer de salida ya se había vaciado y la aplicación respondía 200 en vez de
redirigir. En el sistema combinado el POST se procesa antes de emitir la página.

## 5. Esquema de base de datos (12 tablas)

`categories`, `users`, `skills`, `user_skills_offered`, `user_skills_wanted`, `exchanges`,
`ratings`, `hours_log`, `messages`, `notifications`, `certificates`, `login_attempts`.

Columnas nuevas respecto al sistema base:

- `users.bio` TEXT, `users.github_url` VARCHAR(255), `users.linkedin_url` VARCHAR(255)
- `exchanges.meeting_type` ENUM('virtual','presencial') DEFAULT 'virtual',
  `exchanges.meeting_location` VARCHAR(255), `exchanges.meeting_link` VARCHAR(255)
- tabla `notifications` (user_id, type, title, message, link, is_read, created_at)

Nota de diseño: **no** se agregó `meeting_date`; se reutiliza `scheduled_at` del sistema base como
fecha de la sesión para no duplicar el mismo dato.

## 6. Despliegue

### Opción A — instalar de cero

```bash
mysql -u root -p < skillswap/database/skillswap.sql          # crea y llena la base
sudo cp -a skillswap /var/www/html/                          # o sin sudo si ya tienes permisos
```

El dump trae los **12 objetos de esquema** y **datos de demostración** (vaciados de información
personal: sin `login_attempts`, sin certificados reales y con usuarios de prueba).

Usuarios demo:

| Correo | Contraseña | Rol |
|---|---|---|
| `admin@skillswap.com` | `password` | admin |
| `estudiante@skillswap.com` | `password` | estudiante |

Después hay que dar permiso de escritura al servidor web:

```bash
sudo chown -R www-data:www-data /var/www/html/skillswap/uploads
sudo chmod -R 770 /var/www/html/skillswap/uploads /var/www/html/skillswap/assets/img/avatars
```

### Opción B — actualizar el sistema que ya está instalado

```bash
cd /var/www/html/skillswap
mysql -u root -p skillswap < database/migrate_combinado.sql   # una sola vez
```

Luego copiar encima los archivos PHP/CSS/JS de `skillswap-combinado/skillswap/`.
Haz antes un `mysqldump` y un `tar` del árbol (ver §7).

## 7. Respaldos

- `/tmp/opencode/respaldos/skillswap_20261005_0846.sql` — base de datos (sistema en producción).
- `/tmp/opencode/respaldos/skillswap_arbol_20261005_0846.tar.gz` — árbol del sistema en producción.

## 8. Verificación realizada

`pruebas/combinado_e2e.php` — **74/74 correctas** sobre un entorno aislado (BD `skillswap_com_test`
y copia servida en `/ss-test`, ya eliminados). Cubre:

- registro, login, permisos por rol y protección del panel admin y del CSV sin sesión;
- propuesta con modalidad virtual/enlace y presencial/lugar, deduplicación;
- la otra persona no puede aceptar, el proponente no puede cancelar tras aceptar;
- doble confirmación, saldos, 4 movimientos de historial y limpieza de saldos;
- notificaciones: creación, API, marcar leídas, rechazo con CSRF inválido;
- mensajes: envío por API, polling, rechazo de CSRF inválido y de usuarios ajenos;
- avatar: extensión derivada del contenido, `.php` del cliente no se guarda, payload PHP rechazado;
- insignias, perfil público, constancia de horas 200;
- reportes con las 3 gráficas y CSV de usuarios/intercambios con BOM UTF-8;
- todas las páginas sin errores fatales, avisos ni warnings.

Comando (con el sistema combinado ya desplegado en `/skillswap`):

```bash
php ~/Descargas/skillswap-combinado/pruebas/combinado_e2e.php
```

## 9. Limitaciones conocidas

- `AllowOverride None` en este Apache: los `.htaccess` se ignoran. La protección real de
  `uploads/` y `assets/img/avatars/` está en el código (validación por contenido y
  `modules/profile/file.php`), no en el `.htaccess`.
- Sin `sudo` en este equipo, las carpetas que Apache debe escribir quedaron en `0777`.
- Si borras el avatar de un usuario desde la BD, el archivo queda huérfano en disco
  (la app lo borra al reemplazarlo o al marcar "quitar foto").
