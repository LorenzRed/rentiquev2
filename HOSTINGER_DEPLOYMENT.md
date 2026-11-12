# Rentique: GitHub to Hostinger PHP/HTML hosting

Use a PHP/HTML website. Rentique has an Ionic/Vue frontend, a Laravel PHP API, and a database. The frontend is built locally into static files; a Node.js application server is not required.

Examples below use `yourdomain.com`, `api.yourdomain.com`, and the hosting username `u123456789`. Replace them with your real domain and account paths.

## 1. Publish the source to GitHub

Create a private, empty GitHub repository named `rentique`. Leave the README, license, and .gitignore initialization options unchecked because the local project already exists.

From the project root on your PC:

```powershell
git add .
git status
git commit -m "Prepare Rentique for deployment"
git remote add origin https://github.com/YOUR_USERNAME/rentique.git
git push -u origin master
```

The checked local branch is `master`; select that branch when configuring deployment. If you have already added a remote, inspect `git remote -v` before changing it. Complete GitHub authentication when Git prompts you. Do not share access tokens in chat.

The repository should contain `src`, `backend`, `public`, both Composer files, and both npm package files. Environment files, installed dependencies, local database files, and uploaded documents/images are excluded. `.env.example` templates are included. The Firebase service-account JSON stays outside this repository.

[GitHub instructions](https://docs.github.com/en/migrations/importing-source-code/using-the-command-line-to-import-source-code/adding-locally-hosted-code-to-github)

## 2. Set up Hostinger

1. Create a **PHP/HTML Website** for the main domain.
2. Create the `api` subdomain for Laravel. Record its actual document-root folder from hPanel.
3. Enable HTTPS for both addresses.
4. Select PHP 8.3 or newer for the API. Verify the SSH CLI version with `php -v` too.
5. Enable SSH access and create a MySQL database/user in hPanel. Keep the database connection details for the API environment file.

Do not replace an existing live website while following these first-deployment instructions. Use a new site or staging subdomain if your domain already serves another application.

## 3. Deploy source outside the public website folder

Use hPanel **Advanced → Git**, connect GitHub, select the repository and `master`, and set its deployment destination to a private directory outside `public_html`, for example:

```text
/home/u123456789/domains/yourdomain.com/rentique-repo/
```

Hostinger's Git destination is a server directory, not a frontend build command. The repository root includes private backend code and is not the website document root. If your panel cannot select a private destination, use SSH/Git with a GitHub deploy key or ask Hostinger to help place the repository outside the public directory.

[Hostinger Git instructions](https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/)

## 4. Configure the Laravel API

Connect with SSH and enter the actual backend directory:

```bash
cd /home/u123456789/domains/yourdomain.com/rentique-repo/backend
php -v
composer2 install --no-dev --prefer-dist --optimize-autoloader
composer2 check-platform-reqs --no-dev
cp .env.example .env
```

Copy the template only for a new installation; keep the existing `.env` on subsequent deployments. Ensure CLI PHP meets the requirements before running Composer.

Edit the server's `backend/.env`:

```dotenv
APP_NAME=Rentique
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=YOUR_HOSTINGER_DATABASE_HOST
DB_PORT=3306
DB_DATABASE=YOUR_HOSTINGER_DATABASE_NAME
DB_USERNAME=YOUR_HOSTINGER_DATABASE_USER
DB_PASSWORD="YOUR_DATABASE_PASSWORD"

FIREBASE_PROJECT_ID=console-cloths
FIREBASE_CREDENTIALS=/home/u123456789/private/firebase-admin.json
FRONTEND_ORIGINS=https://yourdomain.com,https://www.yourdomain.com
```

Use your actual Firebase project ID. Upload the existing service-account JSON separately into the private server folder referenced by `FIREBASE_CREDENTIALS`. The Windows path `C:/Users/balle/.rentique/firebase-admin.json` is only for your PC. No Firebase Storage bucket is needed.

For the first installation:

```bash
php artisan key:generate --force
php artisan migrate --seed --force
php artisan config:cache
php artisan view:cache
```

Generate `APP_KEY` once and preserve it afterward. Laravel needs write access to `storage` and `bootstrap/cache`.

Migrations create tables; they do not transfer existing local records. If you need existing data online, arrange a separate database transfer and copy the existing `storage/app/private/rentique` files as well.

[Composer on Hostinger](https://www.hostinger.com/support/5792078-how-to-use-composer-at-hostinger/), [Laravel deployment requirements](https://laravel.com/docs/13.x/deployment)

## 5. Expose only Laravel's public entry point

The API document root must expose the Laravel public entry point, with the rest of the backend kept private.

If the hosting configuration can point the API document root directly to `rentique-repo/backend/public`, use that.

If hPanel gives the API a fixed public folder:

1. Copy the **contents** of `backend/public/`, including its `.htaccess`, into that API public folder.
2. Replace only that copied `index.php` with the following entry point, using the real private backend path:

```php
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$laravelRoot = '/home/u123456789/domains/yourdomain.com/rentique-repo/backend';

if (file_exists($maintenance = $laravelRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
```

Keep `.env`, `vendor`, `storage`, and the Firebase JSON outside the public folder. Open `https://api.yourdomain.com/up` and confirm a successful response before deploying the frontend.

## 6. Build the frontend on your PC

Your existing `.env.local` supplies Firebase web configuration. Create `.env.production.local` at the project root to override the API URL for production:

```dotenv
VITE_API_URL=https://api.yourdomain.com/api/v1
```

This file is ignored by Git. Build from the root:

```powershell
npm ci
npm run build
```

Upload the **contents** of `dist/` into the main domain's public folder, normally `public_html`. Include the hidden `.htaccess` file; it allows refreshing Vue routes such as `/profile`. The site's entry file should be `index.html` at the document root.

Rebuild after changing frontend environment values. Built frontend assets contain public Firebase web configuration; the private service-account JSON belongs only on the API server.

## 7. Configure Firebase domains

In Firebase Console, open Authentication → Settings → Authorized domains. Add the actual frontend hostname, and its `www` hostname if used. Keep Email/Password sign-in enabled.

## 8. Schedule reminders

Create a cron job in hPanel that runs every minute, using the correct PHP binary and private backend path:

```text
* * * * * php /home/u123456789/domains/yourdomain.com/rentique-repo/backend/artisan schedule:run >> /dev/null 2>&1
```

The configured application reminder command runs hourly. Hostinger serves the static frontend and PHP requests; development terminals do not need to remain open.

## 9. Check the live application

- Open the main website and refresh `/profile` directly.
- Register a real test account and check email verification and profile editing.
- Register an owner, submit a shop application, review it with an administrator, and check public visibility after approval.
- Upload a profile/shop/product image and confirm the API serves it.
- Check a private document link, the map, and one full rental workflow.
- Check `storage/logs/laravel.log` if an API call fails, without sharing credentials or tokens.

Administrator roles are assigned through the existing trusted administrative process; public registration cannot create them.

## 10. Future updates

Push source changes to GitHub. Update the private backend checkout, run production Composer installation and additive migrations, and refresh Laravel caches. Rebuild the frontend locally using the production API URL, then upload its new `dist` contents.

Preserve the production `.env`, `APP_KEY`, Firebase JSON, database, and uploaded storage files across updates. Review Hostinger's automatic deployment behavior before enabling it for a directory containing persistent uploads.
