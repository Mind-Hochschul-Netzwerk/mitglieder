SERVICENAME=$(shell grep SERVICENAME .env | sed -e 's/^.\+=//' -e 's/^"//' -e 's/"$$//')

check-traefik:
ifeq (,$(shell docker ps -f name=^traefik$$ -q))
	$(error docker container traefik is not running)
endif

.env:
	$(error .env is missing)

image:
	@echo "(Re)building docker image"
	docker build --no-cache -t mindhochschulnetzwerk/$(SERVICENAME):latest .

quick-image:
	@echo "Rebuilding docker image"
	docker build -t mindhochschulnetzwerk/$(SERVICENAME):latest .

dev: .env check-traefik
	@echo "Starting DEV Server"
	docker-compose -f docker-compose.base.yml -f docker-compose.dev.yml up -d --force-recreate --remove-orphans

prod: image .env check-traefik
	@echo "Starting Production Server"
	docker-compose -f docker-compose.base.yml -f docker-compose.prod.yml up -d --force-recreate --remove-orphans

adminer: .env check-traefik
	docker-compose -f docker-compose.base.yml -f docker-compose.dev.yml up -d $(SERVICENAME)-adminer

database: .env
	docker-compose -f docker-compose.base.yml -f docker-compose.prod.yml up -d --force-recreate $(SERVICENAME)-database

shell:
	docker-compose -f docker-compose.base.yml exec $(SERVICENAME) sh

MYSQL_PASSWORD=$(shell grep MYSQL_PASSWORD .env | sed -e 's/^.\+=//' -e 's/^"//' -e 's/"$$//')
mysql: .env
	@echo "docker-compose exec $(SERVICENAME)-database mysql --user=user --password=\"...\" database"
	@docker-compose -f docker-compose.base.yml exec $(SERVICENAME)-database mysql --user=user --password="$(MYSQL_PASSWORD)" database
