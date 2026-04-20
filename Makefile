.PHONY: up down logs stop restart build install

up:
	docker-compose up -d
	@echo "Plattadata rodando em http://localhost:8080"

down:
	docker-compose down

logs:
	docker-compose logs -f

stop:
	docker-compose stop

restart:
	docker-compose restart

build:
	docker-compose build

install:
	docker-compose up -d --build
	@echo "Instalando dependencias..."
	@docker-compose exec -T app composer install --no-interaction 2>/dev/null || true
	@echo "Pronto!"

# Setup inicial
setup: down build
	@echo "Criando volumes..."
	docker network create plattadata-network 2>/dev/null || true
	@echo "Iniciando MySQL..."
	docker-compose up -d db
	@sleep 10
	@echo "Configure o arquivo .env com os dados do Docker:"
	@echo "CP .env.docker .env"
	@echo "Edite o .env com as credenciais do banco"