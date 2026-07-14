# Despliegue en Hostinger — Perfumeria

Guía completa para desplegar la **API** (`luxurperfumeria.elezdev.com`) y el **Frontend** (`scenxialperfums.elezdev.com`).

---

## Dominios y repos

| Proyecto   | Dominio                              | Repositorio GitHub        | Rama      |
|------------|--------------------------------------|---------------------------|-----------|
| API        | `https://luxurperfumeria.elezdev.com` | `ScenxialPerfumsApi`      | `develop` |
| Frontend   | `https://scenxialperfums.elezdev.com`  | `ScenxialPerfumsFront`    | `develop` |

---

## 1. API — Despliegue Git

Hostinger clona el repo en `public_html`. Directorio raíz del deploy: **`public_html`**.

### `.htaccess` en la raíz del proyecto (API)

Archivo `public_html/.htaccess` (junto a `artisan`, `app/`, etc.):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

**Alternativa (recomendada):** en hPanel cambiar **Document Root** del dominio a `public_html/public`.

---

## 2. API — Variables de entorno (`.env`)

Crear `public_html/.env` en el servidor:

```env
APP_NAME="Perfumeria API"
APP_ENV=production
APP_KEY=base64:TU_CLAVE_AQUI
APP_DEBUG=false
APP_URL=https://luxurperfumeria.elezdev.com

FRONTEND_URL=https://scenxialperfums.elezdev.com

APP_LOCALE=es
APP_FALLBACK_LOCALE=es

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u321920935_Scenxial
DB_USERNAME=u321920935_edwinScenxial
DB_PASSWORD="El1002923375#"

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

MERCADOPAGO_ACCESS_TOKEN=
MERCADOPAGO_PUBLIC_KEY=
MERCADOPAGO_WEBHOOK_SECRET=
MERCADOPAGO_CURRENCY=COP

JWT_SECRET=TU_JWT_SECRET_AQUI
```

> **Importante:** `FRONTEND_URL` **sin barra final** (`/`). Si termina en `/`, CORS falla.

### Generar claves en local (si no tienes SSH)

```bash
cd perfumeria-api
php artisan key:generate --show
php artisan jwt:secret --show
```

Copiar los valores a `APP_KEY` y `JWT_SECRET` en el `.env` del servidor.

---

## 3. API — Base de datos

### Opción A: Import manual (phpMyAdmin)

1. Exportar desde local (phpMyAdmin o `mysqldump`).
2. Importar en Hostinger → **phpMyAdmin** → base `u321920935_Scenxial`.

### Opción B: Migraciones por SSH

```bash
cd ~/domains/luxurperfumeria.elezdev.com/public_html
php artisan migrate --force
php artisan db:seed --force
```

---

## 4. API — SSH: permisos y storage

### Conectar por SSH

```bash
ssh u321920935@us-bos-web1933.hostinger.com
```

### Navegar al proyecto

```bash
cd ~/domains/luxurperfumeria.elezdev.com/public_html
```

### Permisos de storage

```bash
chmod -R 775 storage bootstrap/cache
```

Si sigue fallando la escritura:

```bash
chmod -R 777 storage bootstrap/cache
```

### Enlace simbólico para imágenes (`storage:link`)

`php artisan storage:link` **falla en Hostinger** (`exec()` / `symlink()` deshabilitados en PHP). Crear el enlace manualmente:

```bash
cd ~/domains/luxurperfumeria.elezdev.com/public_html/public

rm -rf storage
ln -s ../storage/app/public storage

ls -la storage
```

Debe mostrar: `storage -> ../storage/app/public`

### Carpetas de uploads

```bash
cd ~/domains/luxurperfumeria.elezdev.com/public_html
mkdir -p storage/app/public/uploads
chmod -R 775 storage/app/public
```

### Subir imágenes desde local (Mac)

La importación de BD **no incluye archivos**. Copiar `storage/app/public`:

```bash
scp -r /Users/macbook/Desktop/Permuferia/perfumeria-api/storage/app/public/* \
  u321920935@us-bos-web1933.hostinger.com:~/domains/luxurperfumeria.elezdev.com/public_html/storage/app/public/
```

### Limpiar / regenerar caché de config

```bash
cd ~/domains/luxurperfumeria.elezdev.com/public_html
php artisan config:clear
php artisan config:cache
```

Si no tienes SSH, borrar manualmente: `bootstrap/cache/config.php`

---

## 5. API — Verificación

```bash
# Health
curl https://luxurperfumeria.elezdev.com/api/health

# Categorías
curl https://luxurperfumeria.elezdev.com/api/categories

# Imagen (reemplazar ruta)
curl -I https://luxurperfumeria.elezdev.com/storage/uploads/archivo.jpg
```

Respuesta esperada de health:

```json
{"status":"ok","app":"Perfumeria API"}
```

---

## 6. Frontend — Variables de entorno

Vite **incrusta las variables en el build**. El `.env` en la carpeta desplegada **no sirve** después del build.

### Archivo `.env.production` en el repo (recomendado)

En `perfumeria-front/.env.production`:

```env
VITE_API_URL=https://luxurperfumeria.elezdev.com/api
VITE_CURRENCY=COP
VITE_WHATSAPP_NUMBER=+573248191025
VITE_WHATSAPP_MESSAGE=Hola! Quiero consultar por productos en Permuferia.
```

Subir a Git y **Redesplegar** en Hostinger para que `npm run build` use estas variables.

### Build local de prueba

```bash
cd perfumeria-front
npm run build
grep -o 'luxurperfumeria[^`"]*' dist/assets/*.js | head -3
```

Debe mostrar `luxurperfumeria.elezdev.com/api`, **no** `localhost:8000`.

---

## 7. Frontend — SPA routing (404 al recargar)

Archivo `public/.htaccess` (se copia a `dist/` al hacer build):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    RewriteRule ^index\.html$ - [L]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.html [L]
</IfModule>
```

Sin esto, rutas como `/catalogo` dan **404** al recargar (F5).

---

## 8. Frontend — Deploy Git

1. Push a rama `develop`.
2. Hostinger → **Redesplegar** en `scenxialperfums.elezdev.com`.
3. Verificar en DevTools → Network que las peticiones van a `luxurperfumeria.elezdev.com/api`.

---

## 9. CORS (frontend ↔ API)

| Variable        | Dónde              | Valor                                      |
|-----------------|--------------------|--------------------------------------------|
| `FRONTEND_URL`  | `.env` de la API   | `https://scenxialperfums.elezdev.com`      |
| `VITE_API_URL`  | `.env.production`  | `https://luxurperfumeria.elezdev.com/api`  |

Después de cambiar `FRONTEND_URL`:

```bash
php artisan config:clear
php artisan config:cache
```

---

## 10. Composer — Problemas resueltos en el repo

El proyecto incluye ajustes para Hostinger:

- Versiones PHP 8.2 compatibles (`mercadopago`, `jwt-auth`, `spatie/permission`).
- `paragonie/sodium_compat_ext_sodium` (ext-sodium).
- `PackageDiscover` sin `proc_open` en scripts de Composer.
- `config/cors.php` normaliza barras finales en `FRONTEND_URL`.

Si el deploy Git falla en `composer install`, hacer push de `composer.json` y `composer.lock` actualizados y **Redesplegar**.

---

## 11. Comandos rápidos (cheat sheet)

```bash
# --- SSH: entrar al servidor ---
ssh u321920935@us-bos-web1933.hostinger.com

# --- API ---
cd ~/domains/luxurperfumeria.elezdev.com/public_html
chmod -R 775 storage bootstrap/cache
cd public && rm -rf storage && ln -s ../storage/app/public storage
php artisan config:clear && php artisan config:cache

# --- Front (local, antes de push) ---
cd /Users/macbook/Desktop/Permuferia/perfumeria-front
git add .env.production public/.htaccess
git commit -m "Production deploy config"
git push origin develop
# → Redesplegar en hPanel

# --- API (local, cambios de código) ---
cd /Users/macbook/Desktop/Permuferia/perfumeria-api
git push origin develop
# → Redesplegar en hPanel
```

---

## 12. Errores frecuentes

| Error | Causa | Solución |
|-------|--------|----------|
| 403 Forbidden (API) | Document root apunta a raíz Laravel | `.htaccess` raíz o Document Root → `public/` |
| 404 al recargar (Front) | SPA sin fallback | `public/.htaccess` → `index.html` |
| `localhost:8000` en producción | Build sin `.env.production` | Agregar `.env.production` y redesplegar |
| CORS blocked | `FRONTEND_URL` incorrecto o con `/` final | Corregir `.env` API, `config:cache` |
| Imágenes rotas | Falta symlink `public/storage` | `ln -s ../storage/app/public storage` |
| `storage:link` falla | `exec()` deshabilitado | Crear symlink manual por SSH |
| Composer `proc_open` | Scripts de artisan en install | Ya resuelto con `PackageDiscover` |

---

## 13. URLs finales

- **API base:** `https://luxurperfumeria.elezdev.com/api`
- **Frontend:** `https://scenxialperfums.elezdev.com`
- **Health:** `https://luxurperfumeria.elezdev.com/api/health`
- **Login:** `POST https://luxurperfumeria.elezdev.com/api/auth/login`
