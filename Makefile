BACKEND_IMAGE ?= sarona/backend:local

.PHONY: dev docker-up docker-down test apk deploy

dev: docker-up

docker-up:
	docker compose up -d --build

docker-down:
	docker compose down --remove-orphans

test:
	cd backend && composer install && php artisan test
	cd mobile && flutter pub get && flutter test

apk:
	cd mobile && flutter pub get && flutter build apk --release --dart-define-from-file=.env.example

deploy:
	docker pull $(BACKEND_IMAGE)
	BACKEND_IMAGE=$(BACKEND_IMAGE) docker compose down
	BACKEND_IMAGE=$(BACKEND_IMAGE) docker compose up -d
	BACKEND_IMAGE=$(BACKEND_IMAGE) docker compose exec -T laravel php artisan migrate --force
