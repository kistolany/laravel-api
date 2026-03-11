# Sarona Monorepo

This repository combines the existing Laravel API and Flutter Android app into a single monorepo with Docker-based backend infrastructure and a GitHub Actions CI/CD pipeline.

## Repository Structure

```text
repo/
â”œ backend/                # Existing Laravel API
â”œ mobile/                 # Existing Flutter Android app
â”œ docker/
â”‚  â”” backend/
â”‚     â”œ Dockerfile
â”‚     â”” entrypoint.sh
â”œ nginx/
â”‚  â”” default.conf
â”œ .github/
â”‚  â”” workflows/
â”‚     â”” ci.yml
â”œ docker-compose.yml
â”œ Makefile
â”” README.md
```

## Backend

Local Laravel commands from [`backend/`](/e:/sarona-project/backend):

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

Useful backend endpoints:

- `http://localhost/api`
- `http://localhost/api/health`
- `http://localhost/up`

The Docker image uses PHP 8.2, MySQL support, optional Redis environment variables, queue worker support, storage permission fixes, and a multi-stage build.

## Mobile

Local Flutter commands from [`mobile/`](/e:/sarona-project/mobile):

```bash
flutter pub get
flutter analyze
flutter test
flutter build apk --release --dart-define-from-file=.env.example
```

APK output:

```text
build/app/outputs/flutter-apk/app-release.apk
```

Set the API base URL in [`mobile/.env.example`](/e:/sarona-project/mobile/.env.example) or override it in CI with the `MOBILE_API_BASE_URL` repository variable.
If you run Laravel with `php artisan serve` instead of Docker, use `http://localhost:8000/api` for desktop testing or `http://10.0.2.2:8000/api` on the Android emulator.

## Docker Development

Build and start the backend stack:

```bash
docker compose up -d
```

Services included:

- `nginx`
- `laravel`
- `queue`
- `mysql`

The API is exposed at `http://localhost/api`, and nginx forwards PHP requests to the Laravel container.

## CI/CD Pipeline

The GitHub Actions workflow lives at [`.github/workflows/ci.yml`](/e:/sarona-project/.github/workflows/ci.yml) and runs automatically on every push to `main`.

It performs:

- Backend dependency install and `php artisan test`
- Flutter dependency install, `flutter analyze`, `flutter test`, and release APK build
- APK artifact upload to GitHub Actions
- Backend Docker image build and push to `ghcr.io/<owner>/backend:latest`
- Automatic deployment over SSH after successful checks

Caching is enabled for Composer dependencies, Flutter pub packages, Gradle, and Docker build layers.

## Automatic Deployment

Configure these GitHub secrets before enabling deployment:

- `SERVER_HOST`
- `SERVER_USER`
- `SERVER_SSH_KEY`

Optional:

- `SERVER_PATH` to override the default remote path of `~/sarona-project`

The deployment job will:

1. SSH into the server.
2. `git pull origin main`
3. Pull `ghcr.io/<owner>/backend:latest`
4. Restart the Docker Compose stack
5. Run `php artisan migrate --force`

Make sure the server already has Docker, Docker Compose, and this repository cloned at the deployment path.

## Make Targets

From the repository root:

```bash
make dev
make docker-up
make docker-down
make test
make apk
make deploy BACKEND_IMAGE=ghcr.io/<owner>/backend:latest
```

## Environment Files

- [`backend/.env.example`](/e:/sarona-project/backend/.env.example) contains the Laravel/MySQL/JWT defaults.
- [`mobile/.env.example`](/e:/sarona-project/mobile/.env.example) contains `API_BASE_URL=http://localhost/api`.


