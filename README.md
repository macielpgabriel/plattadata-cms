# CMS Empresarial Platadata - Consulta de CNPJ Brasil

## Visão Geral

O **Platadata CMS** é um sistema de gestão de conteúdo (CMS) desenvolvido em **PHP 8.3+** nativo (sem frameworks), projetado especificamente para consulta e gerenciamento de dados de empresas brasileiras via CNPJ. O sistema integra múltiplas fontes de dados oficiais, incluindo Receita Federal, IBGE, Banco Central e outras APIs públicas, oferecendo uma solução completa para busca de informações empresariais.

Este projeto foi desenvolvido com foco em **performance, segurança e conformidade** com a LGPD (Lei Geral de Proteção de Dados), sendo adequado para uso em produção com alta disponibilidade.

---

## Tabela de Conteúdos

1. [Características Principais](#1-características-principais)
2. [Tecnologias Utilizadas](#2-tecnologias-utilizadas)
3. [Estrutura do Projeto](#3-estrutura-do-projeto)
4. [Instalação](#4-instalação)
5. [Configuração](#5-configuração)
6. [Rotas Principais](#6-rotas-principais)
7. [Variáveis de Ambiente](#7-variáveis-de-ambiente)
8. [Scripts de Manutenção](#8-scripts-de-manutenção)
9. [Testes e Qualidade](#9-testes-e-qualidade)
10. [Troubleshooting](#10-troubleshooting)
11. [Segurança](#11-segurança)
12. [Contribuição](#12-contribuição)
13. [Atualização e Migração](#13-atualização-e-migração)
14. [Suporte](#14-suporte)

---

## 1. Características Principais

### 1.1 Sistema de Autenticação e Segurança

| Feature | Descrição |
|---------|----------|
| **Hash de Senhas** | Algoritmo Argon2id (recomendado pelo OWASP) |
| **Proteção contra Brute Force** | Lockout após tentativas falhas de login |
| **Autenticação de Dois Fatores (2FA)** | Obrigatório para administradores (configurável) |
| **Proteção CSRF** | Tokens em todos os formulários POST |
| **Session Security** | Regeneração de ID de sessão no login |
| **Rate Limiting** | Limitação de requisições por IP e usuário autenticado |
| **Headers de Segurança** | CSP, HSTS, X-Frame-Options, etc. |

### 1.2 Sistema de Roles e Permissões

| Role | Descrição | Permissões |
|------|-----------|------------|
| `admin` | Administrador | Acesso total ao sistema |
| `moderator` | Moderador | Gerenciar remoções de empresas |
| `editor` | Editor | Visualizar, buscar e atualizar empresas |
| `viewer` | Leitor | Apenas visualização básica |

### 1.3 Consulta CNPJ Multi-Fonte

O sistema utiliza uma **cadeia de fallback** para garantir alta disponibilidade:

```
BrasilAPI → ReceitaWS → CNPJ.ws → OpenCNPJ
```

**Características:**
- Cache local para evitar consultas repetidas
- Timeout configurável (padrão: 10 segundos)
- Dados armazenaodos: razão social, nome fantasia, situação cadastral, QSA, CNAEs, Simples Nacional
- Retry automático em caso de falha

### 1.4 Atualização Dinâmica de Dados

Para evitar campos vazios na tela da empresa (`/empresas/{cnpj}`), o sistema combina:

- Dados persistidos no banco (`companies`)
- `raw_data` da última consulta
- Fallback de múltiplos formatos de payload dos provedores
- Revalidação automática quando dados essenciais estão faltando

**Regras de atualização:**

- **Atualização manual por usuário** (`POST /empresas/{cnpj}/atualizar`):
  - `admin`/`editor`: permitido (com rate limit por hora)
  - demais usuários autenticados: bloqueio por janela de cooldown
- **Atualização automática em visualização**:
  - Só dispara quando faltam dados essenciais
  - só dispara se o último sync estiver antigo
  - possui trava de cache para evitar loop

### 1.5 Enriquecimento Automático de Dados

Ao consultar um CNPJ, o sistema automaticamente enriquece com:

| Dado | Fonte | Descrição |
|------|-------|------------|
| Endereço | BrasilAPI/ViaCEP | Logradouro, complemento, bairro, cidade, estado |
| Município | IBGE | Código IBGE, população, PIB, frota de veículos |
| Geolocalização | Nominatim/OpenStreetMap | Latitude, longitude, Google Maps |
| DDD Telefônico | Inferido automaticamente | Telefone da região |
| CNAE | Receita Federal | Atividades principal e secundárias |

### 1.6 SEO e Marketing

- Canonical URLs e Open Graph
- Schema.org para empresas (JSON-LD)
- Sitemap dinâmico
- Páginas de comparação de empresas
- Rankings por estado/município
- URLs amigáveis (/empresas/12.345.678/0001-90)

### 1.7 Indicadores Econômicos

- Cotações de câmbio em tempo real (BCB/PTAX)
- Histórico de câmbio (30 dias)
- Impostômetro (arrecadação federal)
- Dados de arrecadação por período

### 1.8 Sistema de Remoção (LGPD)

- Solicitação pública de remoção de empresas
- Verificação por e-mail ou documento
- Aprovação por moderadores
- Armazenamento de documentos em Google Drive

### 1.9 Sistema de Jobs

- Fila de processamento baseada em banco de dados
- Interface de gerenciamento web
- Status: pending, processing, completed, failed
- Retry automático de jobs falhos
- Scheduling via cron

### 1.10 Observabilidade

- Healthcheck JSON (`GET /health`)
- Dashboard com gráficos de uso
- Logs de consultas por hora
- Histórico de chamadas API
- API Tester integrado

### 1.11 Dados Geográficos

- Listagem de estados brasileiros
- Listagem de municípios por UF
- Dados demográficos (população, PIB)
- Mapa de empresas por localização

---

## 2. Tecnologias Utilizadas

### 2.1 Backend

| Tecnologia | Versão | Descrição |
|------------|--------|------------|
| PHP | 8.1+ (8.3 recomendado) | Linguagem principal com tipagem estrita |
| MySQL | 8.0+ | Banco de dados relacional |
| PDO | - | Acesso ao banco de dados |

### 2.2 Frontend

| Tecnologia | Descrição |
|------------|------------|
| Bootstrap 5 | Framework CSS responsivo |
| Bootstrap Icons | Ícones |
| Chart.js | Gráficos |
| PHP Templates | Views nativas |

### 2.3 Bibliotecas (Composer)

| Pacote | Descrição |
|--------|------------|
| `google/apiclient` | Integração com Google APIs (OAuth, Drive) |
| `phpmailer/phpmailer` | Envio de e-mails via SMTP |
| `phpstan/phpstan` | Análise estática de código |
| `phpunit/phpunit` | Testes unitários |

### 2.4 APIs Externas

| API | Dados | Provider Key |
|-----|-------|--------------|
| Receita Federal | Dados de empresas (CNPJ) | `receitaws` |
| IBGE | Dados geográficos e demográficos | `ibge` |
| Banco Central | Cotações de câmbio (PTAX) | `bcb` |
| BrasilAPI | Endereços por CEP | `brasilapi` |
| ViaCEP | Endereços por CEP (fallback) | `viacep` |
| Nominatim | Geocodificação | `nominatim` |

---

## 3. Estrutura do Projeto

```
platadata-cms/
├── .env                     # Configurações (NUNCA commitar)
├── .env.example             # Modelo de configurações
├── .gitignore               # Arquivos ignorados pelo Git
├── .htaccess                # Configurações Apache
├── composer.json           # Dependências PHP
├── phpstan.neon             # Configuração PHPStan
│
├── public/                  # Front controller e assets
│   ├── index.php           # Entry point da aplicação
│   ├── css/                # Arquivos CSS
│   │   ├── app.css        # Estilo principal
│   │   └── components.css # Componentes
│   └── js/                 # Arquivos JavaScript
│
├── bootstrap/              # Bootstrap da aplicação
│   └── app.php             # Inicialização
│
├── routes/                 # Definição de rotas
│   ├── web.php             # Rotas HTTP (páginas)
│   └── api.php             # Rotas da API REST
│
├── src/
│   ├── Core/              # Componentes centrais
│   │   ├── Router.php    # Sistema de roteamento
│   │   ├── Database.php  # Conexão PDO
│   │   ├── SafeDatabase.php # Wrapper seguro
│   │   ├── Auth.php      # Autenticação e autorização
│   │   ├── Session.php   # Gerenciamento de sessões
│   │   ├── Cache.php     # Sistema de cache
│   │   ├── Logger.php    # Sistema de logging
│   │   ├── View.php      # Renderização de templates
│   │   ├── Response.php # Respostas HTTP
│   │   ├── Controller.php # Controller base
│   │   ├── Csrf.php      # Proteção CSRF
│   │   └── Env.php       # Variáveis de ambiente
│   │
│   ├── Controllers/      # Controladores HTTP
│   │   ├── AuthController.php        # Autenticação
│   │   ├── CompanyController.php   # Empresas
│   │   ├── AdminController.php     # Administração
│   │   ├── DashboardController.php # Dashboard
│   │   ├── LocationController.php # Localidades
│   │   ├── Api/                     # API REST
│   │   │   ├── BaseApiController.php
│   │   │   ├── CompanyApiController.php
│   │   │   ├── InfoApiController.php
│   │   │   └── WeatherApiController.php
│   │   ├── Comparison/               # Comparações
│   │   │   ├── ComparisonRankingsService.php
│   │   │   ├── ComparisonSearchService.php
│   │   │   └── ComparisonToolsService.php
│   │   ├── Company/                 # Serviços de empresa
│   │   │   ├── CompanyShowService.php
│   │   │   └── CompanySearchService.php
│   │   ├── Location/                # Localidades
│   │   │   ├── LocationBrasilService.php
│   │   │   ├── LocationStatesService.php
│   │   │   └── LocationMunicipalityService.php
│   │   ├── Observability/           # Observabilidade
│   │   │   ├── JobsService.php
│   │   │   ├── LogsService.php
│   │   │   └── SyncService.php
│   │   ├── Integration/             # Integrações
│   │   │   ├── CompanySearchService.php
│   │   │   └── ApiKeyService.php
│   │   └── ... (outros controladores)
│   │
│   ├── Repositories/      # Acesso a dados (DAO)
│   │   ├── CompanyRepository.php
│   │   ├── UserRepository.php
│   │   ├── StateRepository.php
│   │   ├── MunicipalityRepository.php
│   │   ├── FavoriteRepository.php
│   │   ├── ArrecadacaoRepository.php
│   │   ├── ExchangeRateRepository.php
│   │   ├── ImpostometroRepository.php
│   │   ├── LgpdAuditRepository.php
│   │   ├── MarketAnalyticsRepository.php
│   │   └── ... (outros repositories)
│   │
│   ├── Services/        # Lógica de negócio
│   │   ├── CnpjService.php         # Consulta CNPJ
│   │   ├── IbgeService.php         # Dados IBGE
│   │   ├── BcbService.php         # Banco Central
│   │   ├── MailService.php         # Envio de e-mails
│   │   ├── LgpdComplianceService.php # LGPD
│   │   ├── AddressService.php      # Endereços
│   │   ├── SetupService.php        # Instalação
│   │   │   # Servicios CNPJ
│   │   ├── Cnpj/
│   │   │   ├── CnpjApiService.php
│   │   │   ├── CnpjEnrichmentService.php
│   │   │   └── CnpjValidationService.php
│   │   └── ... (outros serviços)
│   │
│   ├── Middleware/       # Middlewares de segurança
│   │   ├── AuthMiddleware.php   # Autenticação
│   │   ├── AdminMiddleware.php # Admin only
│   │   ├── StaffMiddleware.php # Staff (mod/editor)
│   │   └── CsrfMiddleware.php # CSRF
│   │
│   ├── Views/           # Templates (PHP)
│   │   ├── layouts/     # Layouts base
│   │   │   └── app.php # Layout principal
│   │   ├── admin/      # Páginas administrativas
│   │   ├── auth/      # Autenticação
│   │   ├── companies/ # Empresas
│   │   ├── dashboard/ # Dashboard
│   │   ├── public/    # Páginas públicas
│   │   ├── errors/    # Erros
│   │   ├── removal/   # LGPD removal
│   │   └── favorites/ # Favoritos
│   │
│   └── Support/        # Helpers e utilitários
│       ├── helpers.php   # Funções auxiliares
│       ├── SiteSettings.php
│       └── Translation/
│
├── config/             # Configurações
│   ├── app.php
│   ├── database.php
│   ├── mail.php
│   └── roles.php
│
├── database/           # Schema e migrações
│   ├── schema.sql     # Schema principal
│   └── migrations/   # Migrações incrementais
│
├── storage/           # Arquivos gerados
│   ├── logs/        # Logs de execução
│   ├── cache/       # Arquivos de cache
│   └── backups/     # Backups do banco
│
├── scripts/          # Scripts de manutenção
│   ├── backup_db.sh           # Backup
│   ├── restore_db.sh         # Restore
│   ├── cleanup_backups.sh     # Limpeza backups
│   ├── sync_municipalities.php # IBGE
│   ├── fix-setup.php          # Correção setup
│   ├── check-db.php          # Verificação DB
│   ├── clear-cache.php       # Limpa cache
│   └── ... (outros scripts)
│
├── tests/            # Testes
│   └── unit/        # Testes PHPUnit
│
├── resources/        # Recursos
│   └── lang/       # Traduções
│
├── docs/            # Documentação adicional
│   ├── REFATORACAO_SEGURA_IA.md
│   └── CLOUDFLARE.md
│
└── vendor/          # Dependências Composer
```

---

## 4. Instalação

### 4.1 Requisitos do Sistema

- **PHP**: 8.1 ou superior (recomendado 8.3+)
- **MySQL**: 8.0 ou superior
- **Composer**: Para gerenciamento de dependências
- **Extensões PHP**:
  - `pdo_mysql`
  - `mbstring`
  - `json`
  - `curl`

### 4.2 Instalação Rápida

```bash
# 1. Clone o repositório
git clone https://github.com/plattadata/plattadata-cms.git
cd plattadata-cms

# 2. Copie o arquivo de exemplo
cp .env.example .env

# 3. Edite o .env com suas configurações
nano .env

# 4. Instale as dependências
composer install

# 5. Acesse o instalador web
# Navegue até http://localhost:8000/install

# 6. Configure o admin inicial no .env
CMS_ADMIN_NAME=Administrador
CMS_ADMIN_EMAIL=admin@seudominio.com
CMS_ADMIN_PASSWORD=sua_senha_segura
```

### 4.3 Instalação Manual

```bash
# 1. Crie o banco de dados
mysql -u usuario -p banco < database/schema.sql

# 2. Configure o .env
cp .env.example .env

# 3. Instale dependências
composer install

# 4. Inicie o servidor
php -S localhost:8000 -t public
```

### 4.4 Primeiro Acesso

| Campo | Valor Padrão |
|-------|--------------|
| URL | `http://localhost:8000/login` |
| Email | `admin@plattadata.com` |
| Senha | `Plattadata#2026!` |

> ⚠️ **Importante**: Altere a senha padrão imediatamente após o primeiro acesso.

### 4.5 Instalação em Hospedagem Compartilhada

Consulte o guia detalhado: `INSTRUCOES_HOSTINGER.md`

---

## 5. Configuração

### 5.1 Arquivo .env

O arquivo `.env` deve conter todas as configurações sensíveis. Veja `.env.example` para template.

```dotenv
# ===========================================
# APLICAÇÃO
# ===========================================
APP_NAME=Platadata CMS
APP_URL=https://seudominio.com
APP_TIMEZONE=America/Sao_Paulo
APP_KEY=gerar-chave-com-php-rtrim-bin2hex-random_bytes-32

# ===========================================
# BANCO DE DADOS
# ===========================================
DB_HOST=localhost
DB_PORT=3306
DB_NAME=nome_do_banco
DB_USER=usuario
DB_PASS=senha
DB_CHARSET=utf8mb4

# ===========================================
# ADMINISTRADOR
# ===========================================
CMS_ADMIN_NAME=Administrador
CMS_ADMIN_EMAIL=admin@seudominio.com
CMS_ADMIN_PASSWORD=sua_senha

# ===========================================
# SEGURANÇA
# ===========================================
SESSION_SECURE=true
SESSION_LIFETIME=120
ADMIN_2FA_REQUIRED=true
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15

# ===========================================
# CACHE
# ===========================================
CACHE_DRIVER=file

# ===========================================
# E-MAIL
# ===========================================
MAIL_ENABLED=true
MAIL_MAILER=smtp
MAIL_SMTP_HOST=smtp.hostinger.com
MAIL_SMTP_PORT=587
MAIL_SMTP_USERNAME=email@seudominio.com
MAIL_SMTP_PASSWORD=senha_email
MAIL_FROM_NAME=Platadata
MAIL_FROM_ADDRESS=contato@seudominio.com
```

### 5.2 Configuração de API Keys

```dotenv
# Receitaws (opcional)
RECEITAWS_API_KEY=sua_chave

# BrasilAPI (opcional)
BRASILAPI_TOKEN=sua_chave

# Google (Drive para LGPD)
GOOGLE_CLIENT_ID=client_id
GOOGLE_CLIENT_SECRET=client_secret
GOOGLE_REDIRECT_URI=https://seudominio.com/admin/drive/callback
```

### 5.3 Nginx para Produção

```nginx
server {
    listen 80;
    server_name seu-dominio.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name seu-dominio.com;
    root /var/www/plattadata-cms/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/seu-dominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/seu-dominio.com/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

---

## 6. Rotas Principais

### 6.1 Rotas Públicas

| Método | URL | Descrição |
|--------|-----|-----------|
| GET | `/` | Home com busca de CNPJ |
| POST | `/buscar-cnpj` | Processar busca de CNPJ |
| GET | `/empresas` | Listar empresas |
| GET | `/empresas/{cnpj}` | Detalhes da empresa |
| GET | `/empresas/{cnpj}/historico` | Histórico de alterações |
| GET | `/empresas/{cnpj}/comparar` | Comparar empresas |
| GET | `/empresas/mapa` | Mapa de empresas |
| GET | `/localidades` | Listar estados |
| GET | `/localidades/{uf}` | Lista municípios |
| GET | `/localidades/{uf}/{slug}` | Município específico |
| GET | `/brasil` | Dados gerais do Brasil |
| GET | `/atividades` | Listar CNAEs |
| GET | `/atividades/{cnae}` | CNAE específico |
| GET | `/comparacoes` | Página de comparações |
| GET | `/rankings` | Rankings empresariais |
| GET | `/indicadores-economicos` | Indicadores econômicos |
| GET | `/impostometro` | Impostômetro |
| GET | `/parceiros` | Parceiros |
| GET | `/privacidade` | Política de privacidade |
| GET | `/termos` | Termos de uso |

### 6.2 Rotas de Autenticação

| Método | URL | Descrição |
|--------|-----|-----------|
| GET | `/login` | Formulário de login |
| POST | `/login` | Efetuar login |
| GET | `/cadastro` | Formulário de registro |
| POST | `/cadastro` | Criar nova conta |
| GET | `/esqueci-senha` | Recuperação de senha |
| POST | `/esqueci-senha` | Enviar e-mail de recuperação |
| GET | `/resetar-senha/{token}` | Formulário de reset |
| POST | `/resetar-senha/{token}` | Resetar senha |
| GET | `/verificar-email/{token}` | Verificar e-mail |
| GET | `/logout` | Sair da sessão |
| GET | `/2fa` | Formulário 2FA |
| POST | `/2fa` | Verificar código 2FA |
| GET | `/cancelar-inscricao` | Cancelamento GDPR |

### 6.3 Rotas Autenticadas

| Método | URL | Descrição |
|--------|-----|-----------|
| GET | `/dashboard` | Painel do usuário |
| GET | `/favoritos` | Gerenciar favoritos |
| POST | `/favoritos` | Adicionar favorito |
| DELETE | `/favoritos/{id}` | Remover favorito |
| GET | `/empresas/busca` | Busca avançada |
| POST | `/empresas/{cnpj}/atualizar` | Atualizar dados |
| GET | `/perfil` | Editar perfil |
| POST | `/perfil` | Salvar perfil |
| GET | `/conta` | Configurações da conta |
| POST | `/conta/alterar-senha` | Alterar senha |

### 6.4 Rotas Administrativas

| Método | URL | Descrição | Role mínima |
|--------|-----|-----------|------------|
| GET | `/admin` | Painel administrativo | admin |
| GET | `/admin/dashboard` | Dashboard admin | admin |
| GET | `/admin/configuracoes` | Configurações | admin |
| POST | `/admin/configuracoes` | Salvar configurações | admin |
| GET | `/admin/observabilidade` | Observabilidade | admin |
| GET | `/admin/jobs` | Gerenciamento de jobs | admin |
| POST | `/admin/jobs/{id}/retry` | Retry job | admin |
| GET | `/admin/remocoes` | Gerenciar remoções | moderator |
| POST | `/admin/remocoes/{id}/aprovar` | Aprovar remoção | moderator |
| POST | `/admin/remocoes/{id}/rejeitar` | Rejeitar remoção | moderator |
| GET | `/admin/integrations` | Integrações | admin |
| GET | `/admin/usuarios` | Gerenciar usuários | admin |
| POST | `/admin/usuarios` | Criar usuário | admin |
| POST | `/admin/usuarios/{id}/ativar` | Ativar usuário | admin |
| POST | `/admin/usuarios/{id}/desativar` | Desativar usuário | admin |
| POST | `/admin/backup/gerar` | Gerar backup | admin |
| GET | `/admin/analytics` | Analytics | admin |
| GET | `/admin/analytics/compare` | Comparar períodos | admin |
| GET | `/admin/api-tester` | Testador de API | admin |
| GET | `/admin/upload-drive` | Upload para Drive | admin |

### 6.5 Rotas da API

| Método | URL | Descrição |
|--------|-----|-----------|
| GET | `/api/v1` | Informações da API |
| GET | `/api/v1/health` | Healthcheck |
| GET | `/api/v1/cnpj/{cnpj}` | Buscar empresa |
| GET | `/api/v1/companies` | Listar empresas |
| GET | `/api/v1/exchange-rates` | Cotações de câmbio |
| GET | `/api/v1/weather` | Clima (cidade) |

### 6.6 Rotas de SEO

| URL | Descrição |
|-----|-----------|
| `/robots.txt` | Robots.txt |
| `/sitemap.xml` | Sitemap |
| `/manifest.json` | PWA Manifest |
| `/.well-known/change-password` | Password change |

---

## 7. Variáveis de Ambiente

### 7.1 Aplicação

| Variável | Padrão | Descrição |
|----------|--------|------------|
| `APP_NAME` | Platadata CMS | Nome da aplicação |
| `APP_URL` | http://localhost | URL pública |
| `APP_TIMEZONE` | America/Sao_Paulo | Fuso horário |
| `APP_KEY` | - | Chave de criptografia |
| `APP_ENV` | local | Ambiente |

### 7.2 Banco de Dados

| Variável | Padrão | Descrição |
|----------|--------|------------|
| `DB_HOST` | localhost | Host do banco |
| `DB_PORT` | 3306 | Porta |
| `DB_NAME` | plattadata | Nome do banco |
| `DB_USER` | root | Usuário |
| `DB_PASS` | - | Senha |
| `DB_CHARSET` | utf8mb4 | Charset |

### 7.3 Segurança

| Variável | Padrão | Descrição |
|----------|--------|------------|
| `SESSION_SECURE` | false | HTTPS only |
| `SESSION_LIFETIME` | 120 | Minutos |
| `LOGIN_MAX_ATTEMPTS` | 5 | Tentativas |
| `LOGIN_LOCKOUT_MINUTES` | 15 | Minutos de bloqueio |
| `ADMIN_2FA_REQUIRED` | false | 2FA obrigatório admin |

### 7.4 Cache

| Variável | Padrão | Descrição |
|----------|--------|------------|
| `CACHE_DRIVER` | file | driver (file/redis/memcached) |
| `CACHE_REDIS_HOST` | 127.0.0.1 | Host Redis |
| `CACHE_REDIS_PORT` | 6379 | Porta Redis |
| `CACHE_MEMCACHED_HOST` | 127.0.0.1 | Host Memcached |
| `CACHE_MEMCACHED_PORT` | 11211 | Porta Memcached |

### 7.5 Consulta CNPJ

| Variável | Padrão | Descrição |
|----------|--------|------------|
| `CNPJ_PROVIDER` | receitaws | Provedor principal |
| `CNPJ_FALLBACK_CHAIN` | receitaws,opencnpj,brasilapi,cnpjws | Cadeia fallback |
| `CNPJ_HTTP_TIMEOUT` | 10 | Timeout (segundos) |
| `CNPJ_CACHE_DAYS` | 30 | Dias em cache |
| `CNPJ_REFRESH_USER_COOLDOWN_DAYS` | 15 | Cooldown atualização |
| `CNPJ_REFRESH_RATE_LIMIT_PER_HOUR` | 10 | Rate limit/hora |
| `CNPJ_AUTO_REFRESH_MIN_DAYS` | 7 | Auto-refresh mínimo |
| `CNPJ_AUTO_REFRESH_LOCK_SECONDS` | 21600 | Trava auto-refresh |

### 7.6 Rate Limiting

| Variável | Padrão | Descrição |
|----------|--------|------------|
| `RL_CNPJ_PUBLIC_PER_MINUTE` | 10 | IP público/minuto |
| `RL_CNPJ_AUTH_USER_PER_MINUTE` | 30 | Usuário autenticado/minuto |
| `RL_CNPJ_AUTH_IP_PER_MINUTE` | 50 | IP autenticado/minuto |

### 7.7 E-mail

| Variável | Padrão | Descrição |
|----------|--------|------------|
| `MAIL_ENABLED` | false | Envio habilitado |
| `MAIL_MAILER` | smtp | Mailer |
| `MAIL_SMTP_HOST` | - | Host SMTP |
| `MAIL_SMTP_PORT` | 587 | Porta |
| `MAIL_SMTP_USERNAME` | - | Usuário |
| `MAIL_SMTP_PASSWORD` | - | Senha |
| `MAIL_FROM_NAME` | - | Nome remetente |
| `MAIL_FROM_ADDRESS` | - | E-mail remetente |

### 7.8 LGPD / Privacidade

| Variável | Padrão | Descrição |
|----------|--------|------------|
| `LGPD_AUDIT_ENABLED` | true | Auditoria habilitada |
| `LGPD_RETENTION_ENABLED` | true | Retenção habilitada |
| `RETENTION_COMPANY_QUERY_LOGS_DAYS` | 90 | Retenção logs consulta |
| `RETENTION_ACCESS_LOGS_DAYS` | 30 | Retenção logs acesso |

---

## 8. Scripts de Manutenção

### 8.1 Backup do Banco de Dados

```bash
sh scripts/backup_db.sh
```

O backup será salvo em `storage/backups/` com data e horário no nome.

### 8.2 Restore do Banco de Dados

```bash
sh scripts/restore_db.sh storage/backups/backup_2026-04-14_120000.sql.gz
```

### 8.3 Limpeza de Backups Antigos

```bash
sh scripts/cleanup_backups.sh 14
```

Remove backups com mais de 14 dias.

### 8.4 Sincronização de Municípios

```bash
php scripts/sync_municipalities.php
```

Sincroniza todos os municípios brasileiros com dados do IBGE.

### 8.5 Limpeza de Cache

```bash
php scripts/clear-cache.php
```

Remove todos os arquivos de cache.

### 8.6 Verificação do Banco

```bash
php scripts/check-db.php
```

Verifica a integridade das tabelas do banco.

### 8.7 Correção do Setup

```bash
php scripts/fix-setup.php
```

Executa o setup novamente se necessário.

### 8.8 Cron Jobs Recomendados

```cron
# Backup diário às 01:30
30 1 * * * /bin/sh /caminho/scripts/backup_db.sh >> /caminho/storage/logs/backup.log 2>&1

# Limpeza de backups antigos às 02:10
10 2 * * * /bin/sh /caminho/scripts/cleanup_backups.sh 14 >> /caminho/storage/logs/backup.log 2>&1

# Sincronização de municípios (semanal)
0 3 * * 0 /usr/bin/php /caminho/scripts/sync_municipalities.php >> /caminho/storage/logs/sync.log 2>&1
```

---

## 9. Testes e Qualidade

### 9.1 Executar Testes Unitários

```bash
php tests/run.php
```

### 9.2 Verificação de Sintaxe

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

### 9.3 Análise Estática com PHPStan

```bash
./vendor/bin/phpstan analyse
```

### 9.4 Refatoração Segura com IA

Para refatorações grandes sem quebrar funcionalidades, siga o playbook:

- `docs/REFATORACAO_SEGURA_IA.md`

---

## 10. Troubleshooting

### 10.1 Erro: "Access denied for user"

- Verifique se as credenciais estão corretas no `.env`
- Confirme se o usuário tem permissão para acessar o banco
- Em hospedagem compartilhada, use `localhost` como host

### 10.2 Erro: "Database doesn't exist"

- Crie o banco de dados no painel de hospedagem
- Execute o schema: `mysql -u usuario -p banco < database/schema.sql`

### 10.3 Erro: "PDO not found"

- Habilite a extensão `pdo_mysql` no PHP
- Verifique a versão do PHP (mínimo 8.1)

### 10.4 Erro 403 Forbidden

- Confirme que o `mod_rewrite` está habilitado no Apache
- Verifique as permissões dos arquivos
- Confirme que o `AllowOverride` está ativo no `.htaccess`

### 10.5 Erro 500 Internal Server Error

- Verifique o arquivo `storage/logs/app.log`
- Confirme que `.env` está correto
- Verifique versão do PHP (mínimo 8.1)

### 10.6 Página em Branco

- Adicione ao início do `public/index.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```
- Após debugar, remova as linhas

### 10.7 Tabela 'companies' não existe

- Execute: `php scripts/fix-setup.php`
- Ou instale manualmente: `mysql -u usuario -p banco < database/schema.sql`

---

## 11. Segurança

### 11.1 Regras do .env

**NUNCA** commit o arquivo `.env` ao Git. Ele está protegido no `.gitignore`.

Veja `SEGURANCA_ENV.md` para detalhes completos.

### 11.2 Proteções Habilitadas

- Hash de senhas com Argon2id
- CSRF tokens em todos os formulários
- Rate limiting por IP e usuário
-Headers de segurança (CSP, HSTS, X-Frame-Options)
- Auditoria LGPD
- Mascaramento de dados por perfil

### 11.3 Reporting de Vulnerabilidades

Se encontrar uma vulnerabilidade:

1. **NÃO** crie uma issue pública
2. Envie e-mail para: security@plattadata.com
3. Descreva o problema com detalhes
4. Aguarde resposta em até 48 horas

---

## 12. Contribuição

### 12.1 Como Contribuir

1. Fork o repositório
2. Crie uma branch: `git checkout -b feature/nova-feature`
3. Commit suas mudanças: `git commit -am 'Adiciona nova feature'`
4. Push para a branch: `git push origin feature/nova-feature`
5. Crie um Pull Request

### 12.2 Padrões de Código

- **PSR-12**: Estilo de código
- **Tipagem Estrita**: `declare(strict_types=1);`
- **Type Hints**: Parâmetros e retornos tipados

### 12.3 Testes Obrigatórios

Antes de criar PR:

- [ ] Execute `php tests/run.php`
- [ ] Execute `./vendor/bin/phpstan analyse`
- [ ] Verifique sintaxe: `php -l arquivo.php`

### 12.4 Documentação

- Atualize README.md se necessário
- Atualize CHANGELOG.md com suas mudanças

### 12.5 Recursos

- [README.md](README.md) - Visão geral
- [GEMINI.md](GEMINI.md) - Especificação técnica
- [docs/REFATORACAO_SEGURA_IA.md](docs/REFATORACAO_SEGURA_IA.md) - Refatoração segura
- [CONTRIBUTING.md](CONTRIBUTING.md) - Guia de contribuição

---

## 13. Atualização e Migração

### 13.1 Atualização via Installer

Acesse `/install` para executar migrações automaticamente.

### 13.2 Atualização Manual

```bash
# Backup primeiro
sh scripts/backup_db.sh

# Execute migrações
mysql -u usuario -p banco < database/migration_nome.sql

# Limpe cache
php scripts/clear-cache.php
```

### 13.3 Versões Anteriores

Consulte `CHANGELOG.md` para histórico de versões.

---

## 14. Suporte

### 14.1 Canais de Ajuda

- **E-mail**: contato@plattadata.com
- **Issues**: https://github.com/plattadata/plattadata-cms/issues

### 14.2 Documentação Adicional

| Arquivo | Descrição |
|---------|------------|
| `GEMINI.md` | Especificação técnica mestra |
| `CONTRIBUTING.md` | Guia de contribuição |
| `SEGURANCA_ENV.md` | Guia de segurança do .env |
| `INSTRUCOES_HOSTINGER.md` | Instalação Hostinger |
| `docs/CLOUDFLARE.md` | Configuração Cloudflare |
| `docs/REFATORACAO_SEGURA_IA.md` | Playbook de refatoração |
| `CHANGELOG.md` | Histórico de alterações |

---

## Licença

Proprietário - Plattadata

---

*Última atualização: Abril 2026*