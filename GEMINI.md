# GEMINI.md - Especificação Técnica Mestra

Este arquivo é a **Especificação Técnica Mestra** do projeto **CMS Empresarial Platadata - Consulta de CNPJ Brasil**. Ele serve como guia definitivo para manter a integridade arquitetural, segurança e o funcionamento das regras de negócio.

> ⚠️ **Importante**: Não altere fluxos centrais sem consultar estas diretrizes.

> 📌 **Refatorações com IA**: Para trabalhos grandes em múltiplas sessões/agentes, seguir também `docs/REFATORACAO_SEGURA_IA.md`.

---

## 1. Stack Tecnológico e Arquitetura

### 1.1 Tecnologias Principais

| Tecnologia | Versão Mínima | Descrição |
|------------|---------------|------------|
| PHP | 8.1+ (recomendado 8.3+) | Linguagem principal com tipagem estrita |
| MySQL | 8.0+ | Banco de dados relacional |
| Bootstrap | 5.x | Framework CSS responsivo |
| Composer | - | Gerenciador de dependências PHP |

### 1.2 Arquitetura MVC Customizada

O projeto segue uma arquitetura **MVC (Model-View-Controller)** adaptada para PHP nativo, sem utilização de frameworks pesados como Laravel ou Symfony.

#### Estrutura de Diretórios

```
src/
├── Core/              # Componentes de infraestrutura do framework
│   ├── Router.php     # Sistema de roteamento
│   ├── Database.php   # Conexão PDO com MySQL
│   ├── Auth.php       # Autenticação e autorização
│   ├── Cache.php      # Sistema de cache
│   ├── Logger.php     # Sistema de logging
│   └── ...            # Outros componentes
│
├── Controllers/       # Orquestradores de requisições
│   ├── AuthController.php
│   ├── CompanyController.php
│   └── ...
│
├── Repositories/      # Acesso a dados (padrão DAO/Repository)
│   ├── CompanyRepository.php
│   ├── UserRepository.php
│   └── ...
│
├── Services/          # Lógica de negócio e integrações externas
│   ├── CnpjService.php
│   ├── IbgeService.php
│   └── ...
│
├── Middleware/        # Middlewares de segurança
│   ├── AuthMiddleware.php
│   ├── AdminMiddleware.php
│   └── ...
│
├── Views/             # Templates (PHP puro)
│   ├── admin/
│   ├── auth/
│   ├── companies/
│   └── ...
│
└── Support/           # Helpers e utilitários
    ├── helpers.php
    └── SiteSettings.php
```

#### Responsabilidades de Cada Camada

| Camada | Responsabilidade | Responsável por |
|--------|-----------------|-----------------|
| **Core** | Infraestrutura do framework | Roteamento, autenticação, cache, logging, sessões |
| **Controllers** | Orquestrar requisições | Receber input, chamar services, retornar views |
| **Repositories** | Acesso a dados | Consultas SQL, joins, queries no banco |
| **Services** | Lógica de negócio | Integrações APIs, validações, transformações |
| **Middleware** | Interceptar requisições | Autenticação, autorização, CSRF, rate limit |
| **Views** | Renderizar interface | HTML, CSS, dados para templates |

### 1.3 Padrões de Código

- **PSR-12**: Estilo de código遵循 PSR-12
- **Tipagem Estrita**: Todos os arquivos devem ter `declare(strict_types=1);`
- **Tipagem de Retorno**: Métodos devem declarar tipo de retorno
- **命名约定**: camelCase para variáveis e métodos, PascalCase para classes

---

## 2. Fluxo de Consulta CNPJ e Enriquecimento

Este é o **coração do sistema**. O fluxo **deve** seguir esta ordem exata:

### 2.1 Fluxo Principal

```
┌─────────────────────────────────────────────────────────────────────────┐
│                      FLUXO DE CONSULTA CNPJ                            │
└─────────────────────────────────────────────────────────────────────────┘

1. INPUT (Usuário)
   └─ CNPJ informado (ex: 12.345.678/0001-90)

2. SANITIZAÇÃO
   ├─ Remover caracteres especiais (., /, -)
   ├─ Validar dígito verificador (módulo 11)
   └─ Validar tamanho (14 dígitos)

3. RATE LIMIT
   ├─ Verificar por IP público
   ├─ Verificar por usuário autenticado
   └─ Bloquear se exceder limite configurado

4. CACHE LOCAL (companies table)
   ├─ Buscar por CNPJ
   ├─ Verificar data de atualização
   └─ Se cache válido (< CNPJ_CACHE_DAYS) → RETORNAR

5. FETCH EXTERNO (Fallback Chain)
   │
   ├─► BrasilAPI (primário)
   │    └─ Se sucesso → SALVAR → ENRIQUECER
   │
   ├─► ReceitaWS (fallback 1)
   │    └─ Se sucesso → SALVAR → ENRIQUECER
   │
   ├─► CNPJ.ws (fallback 2)
   │    └─ Se sucesso → SALVAR → ENRIQUECER
   │
   └─► OpenCNPJ (fallback 3)
        └─ Se sucesso → SALVAR → ENRIQUECER

6. ENRIQUECIMENTO AUTOMÁTICO
   │
   ├─► _cep_details (BrasilAPI/ViaCEP)
   │    └─ Endereço completo, logradouro, bairro
   │
   ├─► _municipality_details (IBGE)
   │    └─ Código IBGE, população, PIB
   │
   ├─► _map_links (Nominatim)
   │    └─ Latitude, longitude, Google Maps
   │
   └─► _ddd_inference
        └─ DDD baseado no estado/município

7. PERSISTÊNCIA
   ├─ company_source_payloads (JSON bruto)
   ├─ company_snapshots (histórico)
   └─ company_changes (alterações detectadas)

8. OUTPUT (View)
   └─ Renderizar página com dados enriquecidos
```

### 2.2 Cadeia de Fallback

O sistema utiliza múltiplos provedores para garantir alta disponibilidade:

```php
// Configuração em .env
CNPJ_PROVIDER=receitaws
CNPJ_FALLBACK_CHAIN=receitaws,opencnpj,brasilapi,cnpjws
```

**Ordem de tentativa:**
1. **BrasilAPI** - Mais completo (inclui QSA e CNAEs)
2. **ReceitaWS** - Dados oficiais da Receita
3. **CNPJ.ws** - Serviço alternativo
4. **OpenCNPJ** - Open source

### 2.3 Enriquecimento de Dados

Diferente de consultas simples, este CMS realiza um "Join de APIs":

| Dado | Fonte | Descrição |
|------|-------|------------|
| Endereço | BrasilAPI / ViaCEP | Logradouro, complemento, bairro, cidade, estado |
| Município | IBGE | Código IBGE, população, PIB, frota de veículos |
| Geocoordenadas | Nominatim/OpenStreetMap | Latitude, longitude |
| DDD | Inferido | Telefone da região |
| Simples Nacional | Receita Federal | Optante pelo Simples |

---

## 3. Localidades e SEO

### 3.1 Cache de Estados e Municípios

O sistema **não consulta IBGE em tempo real** para listagens. Ele popula as tabelas `states` e `municipalities` e as mantém em cache.

```php
// Sincronização automática via script
php scripts/sync_municipalities.php
```

**Tabela `states`:**
- id, code (UF), name, region

**Tabela `municipalities`:**
- id, ibge_code (único), name, state_id, population, gdp, ddd, region, mesoregion, microregion, lat, long

### 3.2 Sistema de Slugs

- Municípios utilizam slugs amigáveis: `/localidades/sp/sao-paulo`
- CNAEs: `/atividades/6202-3/consultoria-em-presquisa-e-desenvolvimento`
- Empresas: `/empresas/12345678000190`

> ⚠️ **Importante**: Nunca mude a lógica de `slugify()` sem migrar o banco.

### 3.3 Relacionamento Empresa-Município

O campo `municipal_ibge_code` na tabela `companies` é vital para que a empresa apareça na página da cidade.

```sql
-- Exemplo de consulta
SELECT c.*, m.name as city_name 
FROM companies c
JOIN municipalities m ON c.municipal_ibge_code = m.ibge_code
WHERE c.cnpj = '12345678000190';
```

### 3.4 Sitemap Dinâmico

O sitemap é gerado automaticamente respeitando o limite configurado:

```dotenv
SITEMAP_COMPANY_LIMIT=1000
```

---

## 4. Segurança e Compliance (Não Negociável)

### 4.1 LGPD - Lei Geral de Proteção de Dados

O `LgpdComplianceService` implementa:

#### Mascaramento de Dados
O método `maskCompanyPayload` aplica regras baseadas no perfil do usuário:

| Perfil | Acesso |
|--------|--------|
| `public` | Dados básicos, e-mail/telefone mascarados |
| `viewer` | Dados básicos + telefone |
| `editor` | Todos os dados exceto QSA detalhado |
| `admin` | Acesso total ao dado bruto |

```php
// Exemplo de uso
$service = new LgpdComplianceService();
$data = $service->maskCompanyPayload($company, Auth::role());
```

#### Anonimização de IPs
IPs em logs devem ser armazenados em formato de rede:
- IPv4:Máscara `/24` → `192.168.1.0`
- IPv6: Máscara `/48`

#### Auditoria
Todo acesso a dados sensíveis deve gerar registro em `lgpd_audit_logs`.

```php
// Campos registrados:
- user_id
- action_type
- entity_type
- entity_id
- fields_accessed
- masking_profile
- ip_address (anonimizado)
- timestamp
```

### 4.2 Autenticação

```php
// Hash de senhas - Argon2id (OWASP recommended)
password_hash($password, PASSWORD_ARGON2ID);

// Lockout após tentativas falhas
if ($failedAttempts >= LOGIN_MAX_ATTEMPTS) {
    // Bloquear por LOGIN_LOCKOUT_MINUTES minutos
}

// 2FA para administradores
if (ADMIN_2FA_REQUIRED && $user->role === 'admin') {
    // Enviar código por email
}
```

### 4.3 Proteção CSRF

- Tokens únicos para cada sessão/formulário
- Validação em todos os POST/PUT/DELETE

### 4.4 Content Security Policy (CSP)

O sistema implementa CSP restrito:

```php
// SecurityHeadersService
Header::set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'nonce-{nonce}'");
```

### 4.5 Rate Limiting

| Tipo | Limite | Janela |
|------|--------|--------|
| IP Público | 10 | por minuto |
| IP Autenticado | 50 | por minuto |
| Usuário Autenticado | 30 | por minuto |

---

## 5. Resiliência e Performance

### 5.1 Timeouts

Todas as integrações externas devem ter timeout máximo de **5 segundos**:

```php
$options = [
    'timeout' => 5,
    'connect_timeout' => 3,
];
```

### 5.2 Failsafe

Se uma API secundária falhar (ex: Clima, Notícias):
- Use `Logger::error()`
- Defina a variável como vazia/nula
- **A página nunca deve exibir erro 500 por falha em APIs secundárias**

### 5.3 Performance de Banco de Dados

- Use **Bulk Upserts** para dados de municípios
- Nunca faça queries dentro de loops (N+1)
- Use Joins ou Eager Loading manual
- Crie índices apropriados

```sql
-- Índices importantes
CREATE INDEX idx_companies_cnpj ON companies(cnpj);
CREATE INDEX idx_companies_state_city ON companies(state, city);
CREATE INDEX idx_municipalities_ibge ON municipalities(ibge_code);
```

### 5.4 SetupService

O `SetupService.php` gerencia atualizações de schema automaticamente:
- Novos campos são adicionados automaticamente
- Migrações são aplicadas em background
- Garante consistência entre ambientes

---

## 6. Interface e UX (Mobile-First)

### 6.1 Breakpoints

O design deve ser testado primeiramente em **360px** (mobile).

### 6.2 Acessibilidade

- Botões e inputs devem ter altura mínima de **44px**
- Inputs numéricos devem usar `inputmode="numeric"`
- Labels devem estar associados aos inputs

### 6.3 Assets

- CSS customizado em `public/css/app.css`
- Use classes utilitárias de carregamento (`skeleton`, `loading`) para feedback visual

---

## 7. Manutenção e Operação

### 7.1 Logs

| Arquivo | Descrição |
|---------|------------|
| `storage/logs/app.log` | Erros gerais do sistema |
| `storage/logs/app-YYYY-MM-DD.log` | Logs rotacionados por dia |
| `storage/logs/setup.log` | Erros de instalação e migrações |
| `storage/logs/php_errors.log` | Erros PHP |

### 7.2 Backups

Scripts em `scripts/` para automação:
```bash
sh scripts/backup_db.sh           # Backup completo
sh scripts/restore_db.sh <file>   # Restore
sh scripts/cleanup_backups.sh 14 # Limpar backups antigos
```

### 7.3 Observabilidade

- **Healthcheck**: `GET /health` (retorna JSON)
- **Dashboard**: `GET /admin/observabilidade`
- **Métricas**: Consultas por hora, tentativas de API

---

## 8. Ciclo de Vida do Dado

### 8.1 Pipeline de Dados

```
1. INPUT
   └─ CNPJ sanitizado (apenas números, 14 dígitos)

2. INGESTÃO
   └─ Captura do JSON bruto da API
   └─ Armazena em company_source_payloads

3. NORMALIZAÇÃO
   └─ Transforma JSON em colunas relacionais
   └─ Razão Social, Situação, Endereço, etc.

4. ENRIQUECIMENTO
   └─ Adição de metadados (Coordenadas, PIB, DDD)

5. MASCARAMENTO
   └─ Aplica regras LGPD baseadas no perfil

6. OUTPUT
   └─ Renderiza View com dados enriquecidos
```

### 8.2 Retenção de Dados

| Tabela | Retenção Padrão |
|--------|-----------------|
| company_query_logs | 90 dias |
| company_source_payloads | 180 dias |
| lgpd_audit_logs | 2 anos |
| access_logs | 30 dias |

---

## 9. Cache e Performance

### 9.1 Tipos de Cache

| Tipo | Driver | Uso |
|------|--------|-----|
| Resposta de API | File/Redis | Câmbio, lista de estados |
| Dados de Município | MySQL | População, PIB |
| CNAE Descriptions | File/Redis | Descrições de atividades |

### 9.2 Estratégia

- **Cache de Curto Prazo**: Dados que mudam frequentemente (câmbio)
- **Cache de Longo Prazo**: Dados estáticos (municípios, CNAEs)
- **Invalidação Manual**: Quando dados são atualizados

---

## 10. Integrações Externas

### 10.1 APIs de Consulta CNPJ

| API | Provider Key | Dados Incluídos |
|-----|--------------|-----------------|
| BrasilAPI | `brasilapi` | QSA, CNAEs, Simples |
| ReceitaWS | `receitaws` | Dados completos |
| CNPJ.ws | `cnpjws` | Dados básicos |
| OpenCNPJ | `opencnpj` | Dados básicos |

### 10.2 APIs de Endereço

| API | Provider Key | Descrição |
|-----|--------------|------------|
| BrasilAPI CEP | `brasilapi` | Endereço por CEP |
| ViaCEP | `viacep` | Endereço por CEP (fallback) |

### 10.3 APIs de Dados Geográficos

| API | Dados |
|-----|-------|
| IBGE | População, PIB, municípios |
| Nominatim | Coordenadas, geocodificação |

### 10.4 APIs Econômicas

| API | Dados |
|-----|-------|
| Banco Central (PTAX) | Cotações de câmbio |
| Portal da Transparência | Dados governamentais |

---

## 11. Variáveis de Ambiente

### Variáveis Obrigatórias

```dotenv
APP_URL=https://seudominio.com
APP_KEY=chave-aleatoria-segura
DB_HOST=localhost
DB_NAME=nome_banco
DB_USER=usuario
DB_PASS=senha
```

### Variáveis Opcionais

```dotenv
# Cache
CACHE_DRIVER=file

# Rate Limiting
RL_CNPJ_PUBLIC_PER_MINUTE=10

# LGPD
LGPD_AUDIT_ENABLED=true

# 2FA
ADMIN_2FA_REQUIRED=true
```

---

## 12. Testes e Validação

### 12.1 Testes Unitários

```bash
php tests/run.php
```

### 12.2 Análise Estática

```bash
./vendor/bin/phpstan analyse
```

### 12.3 Verificação de Sintaxe

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

---

## 13. Fluxo de Desenvolvimento

### 13.1 Adicionando Novo Service

1. Criar arquivo em `src/Services/NovoService.php`
2. Implementar classe com lógica de negócio
3. Registrar no autoload
4. Criar testes unitários

### 13.2 Adicionando Nova Rota

1. Editar `routes/web.php` ou `routes/api.php`
2. Definir método, caminho e controller
3. Adicionar middleware se necessário

### 13.3 Adicionando Nova Migration

1. Criar arquivo em `database/migration_nome.sql`
2. Adicionar instruções SQL
3. Testar em ambiente local
4. Documentar no CHANGELOG

---

## 14. Boas Práticas

### 14.1 Segurança

- Nunca exponha credenciais em logs
- Use Prepared Statements para todas as queries
- Valide e sanitize todas as entradas
- Use HTTPS em produção

### 14.2 Performance

- Cacheie dados que não mudam frequentemente
- Use índices apropriados no banco
- Minimize chamadas a APIs externas
- Use Lazy Loading quando possível

### 14.3 Código

- Siga PSR-12 para estilo de código
- Comente código complexo
- Escreva testes para novas funcionalidades
- Documente APIs e serviços

---

## Histórico de Atualizações

| Data | Alteração | Autor |
|------|-----------|-------|
| 2024-06 | Versão inicial do GEMINI.md | - |
| 2025-04 | Atualização com novas features | - |

---

*Este arquivo deve ser atualizado sempre que houver mudanças significativas na arquitetura ou fluxos principais do sistema.*
