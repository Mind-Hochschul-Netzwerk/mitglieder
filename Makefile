check-traefik:
ifeq (,$(shell docker ps -f name=^traefik$$ -q))
	$(error docker container traefik is not running)
endif

.env:
	$(error .env is missing)

image:
	@echo "(Re)building docker image"
	docker build --no-cache -t mindhochschulnetzwerk/mitglieder:latest .

quick-image:
	@echo "Rebuilding docker image"
	docker build -t mindhochschulnetzwerk/mitglieder:latest .

dev: .env check-traefik
	@echo "Starting DEV Server"
	docker-compose -f docker-compose.base.yml -f docker-compose.dev.yml up -d --force-recreate --remove-orphans

prod: image .env check-traefik
	@echo "Starting Production Server"
	docker-compose -f docker-compose.base.yml -f docker-compose.prod.yml up -d --force-recreate --remove-orphans

adminer: .env check-traefik
	docker-compose -f docker-compose.base.yml -f docker-compose.dev.yml up -d mitglieder-adminer

database: .env
	docker-compose -f docker-compose.base.yml -f docker-compose.prod.yml up -d --force-recreate mitglieder-database
