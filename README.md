# CMS Empresarial Platadata - Consulta de CNPJ Brasil

## Visão Geral

O **Platadata CMS** é um sistema de gestão de conteúdo (CMS) desenvolvido em **PHP 8.3+** nativo (sem frameworks), projetado especificamente para consulta e gerenciamento de dados de empresas brasileiras via CNPJ. O sistema integra múltiplas fontes de dados oficiais, incluindo Receita Federal, IBGE, Banco Central e outras APIs públicas, oferecendo uma solução completa para busca de informações empresariais.

Este projeto foi desenvolvido com foco em **performance, segurança e conformidade** com a LGPD (Lei Geral de Proteção de Dados), sendo adequado para uso em produção com alta disponibilidade.

---

## Características Principais

### 🔐 Sistema de Autenticação e Segurança

- **Hash de Senhas**: Algoritmo Argon2id (recomendado pelo OWASP)
- **Proteção contra Brute Force**: Lockout após 5 tentativas falhas de login
- **Autenticação de Dois Fatores (2FA)**: Obrigatório para administradores (configurável)
- **Proteção CSRF**: Tokens em todos os formulários POST
- **Session Security**: Regeneração de ID de sessão no login
- **Rate Limiting**: Limitação de requisições por IP e usuário autenticado

### 📊 Sistema de Roles e Permissões

| Role | Descrição | Permissões |
|------|-----------|------------|
| `admin` | Administrador | Acesso total ao sistema |
| `moderator` | Moderador | Gerenciar remoções de empresas |
| `editor` | Editor | Visualizar e buscar empresas |
| `viewer` | Leitor | Apenas visualização básica |

### 🔍 Consulta CNPJ Multi-Fonte

O sistema utiliza uma **cadeia de fallback** para garantir alta disponibilidade:

```
BrasilAPI → ReceitaWS → CNPJ.ws → OpenCNPJ
```

- Cache local para evitar consultas repetidas
- Timeout configurável (padrão: 10 segundos)
- Dados armazenados: razão social, nome fantasia, situação cadastral, QSA, CNAEs, Simples Nacional

### 🔄 Atualização Dinâmica de Dados da Empresa

Para evitar campos vazios na tela da empresa (`/empresas/{cnpj}`), o sistema combina:

- Dados persistidos no banco (`companies`)
- `raw_data` da última consulta
- Fallback de múltiplos formatos de payload dos provedores
- Revalidação automática quando dados essenciais estão faltando

#### Regras de atualização

- **Atualização manual por usuário** (`POST /empresas/{cnpj}/atualizar`):
  - `admin`/`editor`: permitido (com rate limit por hora)
  - demais usuários autenticados: bloqueio por janela de cooldown (padrão 15 dias)
- **Atualização automática em visualização**:
  - só dispara quando faltam dados essenciais (ex.: telefone, e-mail, natureza jurídica, porte, abertura)
  - só dispara se o último sync estiver antigo (padrão 7 dias)
  - possui trava de cache para evitar loop/requisições repetidas (padrão 6h)

#### Variáveis de ambiente de atualização

```dotenv
CNPJ_REFRESH_USER_COOLDOWN_DAYS=15
CNPJ_REFRESH_RATE_LIMIT_PER_HOUR=10
CNPJ_AUTO_REFRESH_MIN_DAYS=7
CNPJ_AUTO_REFRESH_LOCK_SECONDS=21600
```

Para processo de refatoração com IA sem regressões, veja também:

- `docs/REFATORACAO_SEGURA_IA.md`

### 📈 Enriquecimento Automático de Dados

Ao consultar um CNPJ, o sistema automaticamente enriquece com:

- **Endereço**: Via BrasilAPI/ViaCEP
- **Município**: Código IBGE, população, PIB, frota de veículos
- **Geolocalização**: Latitude, longitude, links de mapas
- **DDD Telefônico**: Inferido automaticamente
- **CNAE**: Atividades principal e secundárias

### 🌐 SEO e Marketing

- Canonical URLs e Open Graph
- Schema.org para empresas
- Sitemap dinâmico
- Páginas de comparação de empresas
- URLs amigáveis

### 📊 Indicadores Econômicos

- Cotações de câmbio em tempo real (BCB/PTAX)
- Histórico de câmbio (30 dias)
- Impostômetro (arrecadação federal)
- Dados de arrecadação por período

### 🗑️ Sistema de Remoção (LGPD)

- Solicitação pública de remoção de empresas
- Verificação por e-mail ou documento
- Aprovação por moderadores
- Armazenamento de documentos em Google Drive

### 🗂️ Sistema de Jobs

- Fila de processamento baseada em banco de dados
- Interface de gerenciamento web
- Status: pending, processing, completed, failed
- Retry automático de jobs falhos

### 📈 Observabilidade

- Healthcheck JSON (`/health`)
- Dashboard com gráficos de uso
- Logs de consultas por hora
- Histórico de chamadas API

---

## Tecnologias Utilizadas

### Backend
- **PHP 8.3+** com tipagem estrita (`declare(strict_types=1)`)
- **MySQL 8.0+** com charset utf8mb4
- **PDO** para acesso ao banco de dados

### Frontend
- **Bootstrap 5** para interface responsiva
- **Bootstrap Icons** para ícones
- **Chart.js** para gráficos

### Bibliotecas (Composer)
- **google/apiclient**: Integração com Google APIs (OAuth, Drive)
- **phpmailer/phpmailer**: Envio de e-mails via SMTP
- **phpstan/phpstan**: Análise estática de código
- **phpunit/phpunit**: Testes unitários

### APIs Externas Integração
- **Receita Federal**: Dados de empresas (CNPJ)
- **IBGE**: Dados geográficos e demográficos
- **Banco Central**: Cotações de câmbio (PTAX)
- **ViaCEP / BrasilAPI**: Endereços por CEP
- **Nominatim (OpenStreetMap)**: Geocodificação

---

## Estrutura do Projeto

```
platadata-cms/
├── public/                  # Front controller e assets (CSS, JS, imagens)
│   ├── index.php           # Entry point da aplicação
│   ├── css/                # Arquivos CSS
│   ├── js/                 # Arquivos JavaScript
│   └── .htaccess           # Configurações Apache
│
├── bootstrap/              # Bootstrap da aplicação
│   └── app.php             # Inicialização do framework
│
├── routes/                 # Definição de rotas
│   ├── web.php             # Rotas HTTP (páginas)
│   └── api.php             # Rotas da API REST
│
├── src/
│   ├── Core/              # Componentes centrais do framework
│   │   ├── Router.php    # Sistema de roteamento
│   │   ├── Database.php  # Conexão PDO
│   │   ├── SafeDatabase.php # Wrapper seguro para queries
│   │   ├── Auth.php      # Autenticação e autorização
│   │   ├── Session.php   # Gerenciamento de sessões
│   │   ├── Cache.php     # Sistema de cache multi-driver
│   │   ├── Logger.php    # Sistema de logging
│   │   ├── View.php      # Renderização de templates
│   │   ├── Response.php  # Respostas HTTP
│   │   ├── Csrf.php      # Proteção CSRF
│   │   └── Env.php       # Variáveis de ambiente
│   │
│   ├── Controllers/       # Controladores (orquestradores)
│   │   ├── AuthController.php
│   │   ├── CompanyController.php
│   │   ├── AdminController.php
│   │   ├── DashboardController.php
│   │   ├── LocationController.php
│   │   └── ... (outros controladores)
│   │
│   ├── Repositories/     # Acesso a dados (padrão DAO)
│   │   ├── CompanyRepository.php
│   │   ├── UserRepository.php
│   │   ├── StateRepository.php
│   │   ├── MunicipalityRepository.php
│   │   └── ... (outros repositories)
│   │
│   ├── Services/         # Lógica de negócio e integrações
│   │   ├── CnpjService.php      # Consulta CNPJ multi-fonte
│   │   ├── IbgeService.php      # Dados do IBGE
│   │   ├── BcbService.php       # Banco Central
│   │   ├── MailService.php      # Envio de e-mails
│   │   ├── LgpdComplianceService.php # Conformidade LGPD
│   │   └── ... (outros serviços)
│   │
│   ├── Middleware/       # Middlewares de segurança
│   │   ├── AuthMiddleware.php
│   │   ├── AdminMiddleware.php
│   │   └── CsrfMiddleware.php
│   │
│   ├── Views/            # Templates (PHP)
│   │   ├── layouts/      # Layouts base
│   │   ├── admin/        # Páginas administrativas
│   │   ├── auth/         # Páginas de autenticação
│   │   ├── companies/   # Páginas de empresas
│   │   ├── dashboard/   # Páginas do dashboard
│   │   ├── public/      # Páginas públicas
│   │   └── errors/      # Páginas de erro
│   │
│   └── Support/         # Helpers e utilitários
│       ├── helpers.php   # Funções auxiliares globais
│       └── SiteSettings.php # Configurações dinâmicas
│
├── config/               # Arquivos de configuração
│   ├── app.php           # Configurações gerais
│   ├── database.php      # Configurações de banco
│   ├── mail.php          # Configurações de e-mail
│   └── roles.php         # Definição de papéis
│
├── database/             # Schema e migrações
│   ├── schema.sql        # Schema principal
│   └── migrations/       # Migrações incrementais
│
├── storage/              # Arquivos gerados pela aplicação
│   ├── logs/             # Logs de execução
│   ├── cache/            # Arquivos de cache
│   └── backups/          # Backups do banco
│
├── scripts/              # Scripts de manutenção
│   ├── backup_db.sh     # Script de backup
│   ├── restore_db.sh    # Script de restore
│   └── sync_municipalities.php # Sincronização IBGE
│
├── tests/                # Testes unitários
│   └── unit/             # Testes PHPUnit
│
├── resources/            # Recursos adicionais
│   └── lang/             # Arquivos de tradução
│
└── vendor/               # Dependências do Composer
```

---

## Instalação

### 1. Requisitos do Sistema

- **PHP**: 8.1 ou superior (recomendado 8.3+)
- **MySQL**: 8.0 ou superior
- **Composer**: Para gerenciamento de dependências
- **ext-pdo**: Extensão PHP para MySQL
- **ext-json**: Extensão PHP para JSON
- **ext-mbstring**: Extensão PHP para strings

### 2. Configuração do Ambiente

```bash
# Clone o repositório
git clone https://github.com/seu-repositorio/platadata-cms.git
cd platadata-cms

# Copie o arquivo de exemplo de variáveis de ambiente
cp .env.example .env

# Edite o arquivo .env com suas configurações
nano .env
```

### 3. Configurações do Banco de Dados

No arquivo `.env`, configure:

```dotenv
DB_HOST=localhost
DB_PORT=3306
DB_NAME=nome_do_banco
DB_USER=usuario_do_banco
DB_PASS=senha_do_banco
```

### 4. Importação do Schema

```bash
mysql -u usuario -p banco < database/schema.sql
```

Se o banco já existir e precisar de atualizações:

```bash
mysql -u usuario -p banco < database/migration_advanced_features.sql
```

### 5. Instalação das Dependências

```bash
composer install
```

### 6. Servidor de Desenvolvimento

```bash
# PHP built-in server
php -S localhost:8000 -t public
```

### 7. Acesso ao Sistema

Acesse: `http://localhost:8000/login`

**Credenciais padrão (primeiro acesso):**
- Email: `admin@local.test`
- Senha: `admin@123`

> ⚠️ **Importante**: Altere a senha padrão imediatamente após o primeiro acesso.

---

## Configuração para Produção

### Variáveis de Ambiente Recomendadas

```dotenv
# URL da aplicação
APP_URL=https://seu-dominio.com

# Fuso horário
APP_TIMEZONE=America/Sao_Paulo

# Segurança
SESSION_SECURE=true
ADMIN_2FA_REQUIRED=true
SECURITY_HSTS=true

# Cache
CACHE_DRIVER=redis
CACHE_REDIS_HOST=127.0.0.1
CACHE_REDIS_PORT=6379
```

### Configuração do Nginx (Production)

```nginx
server {
    listen 80;
    server_name seu-dominio.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name seu-dominio.com;
    root /var/www/platadata-cms/public;
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

## Rotas Principais

### Rotas Públicas

| Método | URL | Descrição |
|--------|-----|-----------|
| GET | `/` | Home com busca de CNPJ |
| POST | `/buscar-cnpj` | Processar busca de CNPJ |
| GET | `/empresas` | Listar empresas |
| GET | `/empresas/{cnpj}` | Detalhes da empresa |
| GET | `/localidades` | Listar estados |
| GET | `/localidades/{uf}` | Listar municípios |
| GET | `/brasil` | Dados gerais do Brasil |
| GET | `/atividades` | Listar CNAEs |
| GET | `/indicadores-economicos` | Indicadores econômicos |
| GET | `/impostometro` | Impostômetro |

### Rotas de Autenticação

| Método | URL | Descrição |
|--------|-----|-----------|
| GET | `/login` | Formulário de login |
| POST | `/login` | Efetuar login |
| GET | `/cadastro` | Formulário de registro |
| POST | `/cadastro` | Criar nova conta |
| GET | `/recuperar-senha` | Recuperação de senha |
| POST | `/logout` | Sair da sessão |

### Rotas Autenticadas

| Método | URL | Descrição |
|--------|-----|-----------|
| GET | `/dashboard` | Painel do usuário |
| GET | `/favoritos` | Gerenciar favoritos |
| POST | `/favoritos` | Adicionar favorito |
| GET | `/empresas/busca` | Busca avançada |
| POST | `/logout` | Sair da sessão |

### Rotas Administrativas

| Método | URL | Descrição |
|--------|-----|-----------|
| GET | `/admin` | Painel administrativo |
| GET | `/admin/configuracoes` | Configurações do sistema |
| GET | `/admin/observabilidade` | Dashboard de observabilidade |
| GET | `/admin/jobs` | Gerenciamento de jobs |
| GET | `/admin/remocoes` | Gerenciar remoções |
| POST | `/admin/backup/gerar` | Gerar backup |

### Rotas da API

| Método | URL | Descrição |
|--------|-----|-----------|
| GET | `/api/v1` | Informações da API |
| GET | `/api/v1/health` | Healthcheck |
| GET | `/api/v1/cnpj/{cnpj}` | Buscar empresa |
| GET | `/api/v1/companies` | Listar empresas |
| GET | `/api/v1/exchange-rates` | Cotações de câmbio |

---

## Configurações de Variáveis de Ambiente

### Cache

```dotenv
CACHE_DRIVER=file        # file, redis, memcached
CACHE_REDIS_HOST=127.0.0.1
CACHE_REDIS_PORT=6379
CACHE_MEMCACHED_HOST=127.0.0.1
CACHE_MEMCACHED_PORT=11211
```

### E-mail

```dotenv
MAIL_ENABLED=true
MAIL_MAILER=smtp
MAIL_SMTP_HOST=smtp.hostinger.com
MAIL_SMTP_PORT=587
MAIL_SMTP_USERNAME=seu-email@dominio.com
MAIL_SMTP_PASSWORD=sua-senha
MAIL_FROM_NAME=Platadata
MAIL_FROM_ADDRESS=contato@seudominio.com
```

### APIs Externas

```dotenv
# Consulta CNPJ
CNPJ_PROVIDER=receitaws
CNPJ_FALLBACK_CHAIN=receitaws,opencnpj,brasilapi,cnpjws
CNPJ_HTTP_TIMEOUT=10
CNPJ_CACHE_DAYS=30

# IBGE
IBGE_API_BASE=https://servicodados.ibge.gov.br/api/v1

# Banco Central
BCB_API_BASE=https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata

# Endereços
CEP_PROVIDER=brasilapi
```

### Rate Limiting

```dotenv
RL_CNPJ_PUBLIC_PER_MINUTE=10
RL_CNPJ_AUTH_USER_PER_MINUTE=30
RL_CNPJ_AUTH_IP_PER_MINUTE=50
```

### LGPD / Privacidade

```dotenv
LGPD_AUDIT_ENABLED=true
LGPD_RETENTION_ENABLED=true
RETENTION_COMPANY_QUERY_LOGS_DAYS=90
RETENTION_ACCESS_LOGS_DAYS=30
```

### Segurança

```dotenv
APP_KEY=chave-aleatoria-aqui
SESSION_LIFETIME=120
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15
ADMIN_2FA_REQUIRED=true
```

---

## Scripts de Manutenção

### Backup do Banco de Dados

```bash
sh scripts/backup_db.sh
```

O backup será salvo em `storage/backups/` com data e horário no nome.

### Restore do Banco de Dados

```bash
sh scripts/restore_db.sh storage/backups/backup_2024-01-01_120000.sql.gz
```

### Limpeza de Backups Antigos

```bash
sh scripts/cleanup_backups.sh 14
```

Remove backups com mais de 14 dias.

### Sincronização de Municípios

```bash
php scripts/sync_municipalities.php
```

Sincroniza todos os municípios brasileiros com dados do IBGE.

---

## Testes e Qualidade de Código

### Executar Testes Unitários

```bash
php tests/run.php
```

### Verificação de Sintaxe

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

### Análise Estática com PHPStan

```bash
./vendor/bin/phpstan analyse
```

### Refatoração Segura com IA

Para refatorações grandes sem quebrar funcionalidades, siga o playbook:

- `docs/REFATORACAO_SEGURA_IA.md`

Esse guia inclui:
- estratégia de trabalho em lotes pequenos
- matriz de regressão mínima por lote
- template de handoff entre IAs/sessões
- prompts prontos para execução e revisão

---

## Backup Automatizado (Cron)

### Backup Diário

Adicione ao crontab:

```cron
# Backup diário às 01:30
30 1 * * * /bin/sh /caminho/para/scripts/backup_db.sh >> /caminho/para/storage/logs/backup.log 2>&1
```

### Limpeza de Backups

```cron
# Limpeza de backups antigos às 02:10
10 2 * * * /bin/sh /caminho/para/scripts/cleanup_backups.sh 14 >> /caminho/para/storage/logs/backup.log 2>&1
```

---

## Troubleshooting

### Erro: "Access denied for user"

- Verifique se as credenciais estão corretas no `.env`
- Confirme se o usuário tem permissão para acessar o banco
- Em hospedagem compartilhada, use `localhost` como host

### Erro: "Database doesn't exist"

- Crie o banco de dados no painel de hospedagem
- Execute o schema: `mysql -u usuario -p banco < database/schema.sql`

### Erro: "PDO not found"

- Habilite a extensão `pdo_mysql` no PHP
- Verifique a versão do PHP (mínimo 8.1)

### Erro 403 Forbidden

- Confirme que o `mod_rewrite` está habilitado no Apache
- Verifique as permissões dos arquivos
- Confirme que o `AllowOverride` está ativo no `.htaccess`

---

## Contribuição

Para contribuir com o projeto:

1. Fork o repositório
2. Crie uma branch para sua feature (`git checkout -b feature/nova-feature`)
3. Commit suas mudanças (`git commit -am 'Adiciona nova feature'`)
4. Push para a branch (`git push origin feature/nova-feature`)
5. Crie um Pull Request

---

## Licença

Proprietário - Plattadata

---

## Suporte

Para dúvidas e problemas:
- Email: contato@plattadata.com
- Issues: https://github.com/seu-repositorio/platadata-cms/issues
