# Guia de Contribuição - Contributing Guide

Este documento fornece diretrizes para desenvolvedores que desejam contribuir com o projeto **CMS Platadata**. Sua colaboração é bem-vinda!

---

## 1. Código de Conduta

### 1.1 Valores do Projeto

- **Respeito**: Treat all contributors with respect
- **Inclusão**: Welcome people from all backgrounds
- **Qualidade**: Write clean, well-documented code
- **Colaboração**: Help others and share knowledge

### 1.2 Comportamento Esperado

- Use linguagem acolhedora e inclusiva
- Sea paciente e respeitoso com diferentes níveis de experiência
- Accept constructive criticism comgrace
- Foque no que é melhor para a comunidade e o projeto

### 1.3 Comportamento Inaceitável

- Comentários ofensivos ou depreciativos
- Assédio moral ou discriminação
- Publicação de informações privadas de terceiros
- Comportamento antiético ou ilegal

---

## 2. Como Contribuir

### 2.1 Formas de Contribuir

| Tipo | Descrição |
|------|------------|
| 🐛 **Reportar Bugs** | Encontrar e reportar problemas |
| 💡 **Sugerir Features** | Propor novas funcionalidades |
| 📖 **Melhorar Documentação** | Criar ou corrigir documentação |
| 💻 **Desenvolver Código** | Implementar features ou correções |
| 🎨 **UX/UI** | Melhorar interface e experiência |
| 🧪 **Testes** | Escrever e executar testes |

### 2.2 Fluxo de Trabalho

```
1. Fork o repositório
       ↓
2. Clone localmente
       ↓
3. Crie uma branch (feature/fix/docs)
       ↓
4. Faça suas alterações
       ↓
5. Execute os testes
       ↓
6. Commit com mensagens claras
       ↓
7. Push para seu fork
       ↓
8. Crie um Pull Request
```

---

## 3. Ambiente de Desenvolvimento

### 3.1 Requisitos

- **PHP**: 8.1 ou superior (recomendado 8.3+)
- **MySQL**: 8.0 ou superior
- **Composer**: Para gerenciamento de dependências
- **Git**: Para controle de versão
- **Editor**: VS Code, PhpStorm ou similar

### 3.2 Configuração do Ambiente

```bash
# Clone o repositório
git clone https://github.com/seu-usuario/platadata-cms.git
cd platadata-cms

# Instale as dependências
composer install

# Configure o ambiente
cp .env.example .env

# Edite as configurações do banco no .env
nano .env

# Importe o schema do banco
mysql -u usuario -p banco < database/schema.sql

# Inicie o servidor local
php -S localhost:8000 -t public
```

### 3.3 Variáveis de Ambiente de Desenvolvimento

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Banco de dados local
DB_HOST=localhost
DB_PORT=3306
DB_NAME=plattadata
DB_USER=root
DB_PASS=

# Cache para desenvolvimento
CACHE_DRIVER=file

# Log para desenvolvimento
LOG_LEVEL=debug
```

---

## 4. Padrões de Código

### 4.1 Estilo de Código (PSR-12)

O projeto segue o padrão PSR-12 do PHP-FIG:

```php
<?php

declare(strict_types=1);

namespace App\Services;

final class ExampleService
{
    public function process(string $input): array
    {
        $result = $this->transform($input);
        
        return [
            'status' => 'success',
            'data' => $result,
        ];
    }
    
    private function transform(string $input): string
    {
        return strtoupper($input);
    }
}
```

### 4.2 Regras de Codificação

| Regra | Exemplo |
|-------|---------|
| **Tipagem Estrita** | `declare(strict_types=1);` no topo de todos os arquivos |
| **Type Hints** | Parâmetros e retornos tipados |
| **Nomeclatura** | `camelCase` para variáveis/métodos, `PascalCase` para classes |
| **Comentários** | DocBlocks para classes e métodos públicos |
| **Constantes** | UPPER_CASE com underscores |

### 4.3 Estrutura de Arquivos

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\ServicesExampleService;

final class ExampleController extends Controller
{
    private ExampleService $service;
    
    public function __construct()
    {
        parent::__construct();
        $this->service = new ExampleService();
    }
    
    public function index(): void
    {
        $data = $this->service->getAll();
        
        $this->view('example/index', [
            'title' => 'Exemplo',
            'data' => $data,
        ]);
    }
    
    public function store(): void
    {
        $input = $this->request->all();
        
        $result = $this->service->create($input);
        
        $this->json($result);
    }
}
```

### 4.4 Nomenclatura de Branches

```
feature/nova-funcionalidade   # Novas funcionalidades
fix/correcao-bug              # Correção de bugs
docs/atualizacao-docs         # Documentação
refactor/otimizacao-codigo    # Refatoração
test/novos-testes             # Testes
```

### 4.5 Mensagens de Commit

Use commits atômicos e descritivos:

```
feat: adicionar sistema de favoritos

- Implementação de sistema de favoritos com grupos
- Adição de endpoint para adicionar/remover favoritos
- Criação de UI para gerenciamento de favoritos
- Integração com export CSV

Closes #123
```

**Tipos de commit:**

| Tipo | Descrição |
|------|------------|
| `feat` | Nova funcionalidade |
| `fix` | Correção de bug |
| `docs` | Documentação |
| `style` | Formatação (sem mudança de lógica) |
| `refactor` | Refatoração de código |
| `test` | Testes |
| `chore` | Tarefas de manutenção |

### 4.6 Refatoração com IA (obrigatório)

Para qualquer refatoração assistida por IA:

- Siga `docs/REFATORACAO_SEGURA_IA.md`
- Não misture refatoração estrutural com mudança de regra de negócio no mesmo PR
- Execute smoke test mínimo em `/empresas/{cnpj}` e `POST /empresas/{cnpj}/atualizar`
- Registre handoff do lote (escopo, validação, riscos)

---

## 5. Estrutura do Projeto

### 5.1 Visão Geral dos Diretórios

```
src/
├── Core/              # Componentes do framework
├── Controllers/       # Controladores HTTP
├── Repositories/     # Acesso a dados (DAO)
├── Services/         # Lógica de negócio
├── Middleware/       # Middlewares de segurança
├── Views/            # Templates PHP
└── Support/          # Helpers e utilitários
```

### 5.2 Adicionando Novo Service

1. Crie o arquivo em `src/Services/NovoService.php`
2. Use tipagem estrita e finais
3. Documente com DocBlocks
4. Adicione testes unitários

```php
<?php

declare(strict_types=1);

namespace App\Services;

final class NovoService
{
    public function process(int $id): ?array
    {
        // Implementação
    }
}
```

### 5.3 Adicionando Nova Rota

Edite `routes/web.php` ou `routes/api.php`:

```php
// Web
Router::get('/novo-recurso', [NovoController::class, 'index']);
Router::post('/novo-recurso', [NovoController::class, 'store']);

// API
Router::get('/api/v1/novo-recurso', [NovoController::class, 'apiIndex']);
```

---

## 6. Testes

### 6.1 Executando Testes

```bash
# Todos os testes
php tests/run.php

# Com PHPStan
./vendor/bin/phpstan analyse

# Verificação de sintaxe
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

### 6.2 Padrão de Testes

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\ExampleService;

final class ExampleServiceTest extends TestCase
{
    private ExampleService $service;
    
    protected function setUp(): void
    {
        $this->service = new ExampleService();
    }
    
    public function testProcessReturnsArray(): void
    {
        $result = $this->service->process(1);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
    }
}
```

### 6.3 Cobertura de Testes

Áreas que precisam de testes:
- Services (lógica de negócio)
- Repositories (consultas ao banco)
- Controllers (fluxo de requisições)
- Validações
- Edge cases

### 6.4 Refatorações Grandes com IA

Para refatorações grandes, siga obrigatoriamente:

- `docs/REFATORACAO_SEGURA_IA.md`

Regras mínimas:
- trabalhar por lotes pequenos (1 domínio por vez)
- não misturar refatoração e mudança de negócio no mesmo PR
- anexar handoff técnico ao final do PR
- executar matriz de regressão mínima após cada lote

---

## 7. Pull Requests

### 7.1 Checklist antes de Criar PR

- [ ] Branch criada a partir de `main` ou `develop`
- [ ] Código segue os padrões do projeto
- [ ] Testes foram executados e passaram
- [ ] PHPStan não retornou erros
- [ ] Documentação foi atualizada (se necessário)
- [ ] Commits seguem o padrão de nomenclatura

### 7.2 Template de Pull Request

```markdown
## Descrição
Breve descrição das alterações realizadas.

## Tipo de Mudança
- [ ] Nova funcionalidade
- [ ] Correção de bug
- [ ] Documentação
- [ ] Refatoração

## Screenshots (se aplicável)
Adicione screenshots das mudanças na UI.

## Checklist
- [ ] Testes foram adicionados/atualizados
- [ ] Documentação foi atualizada
- [ ] O código segue os padrões de estilo
- [ ] Os testes passaram localmente

## Informações Adicionais
Qualquer informação adicional sobre a mudança.
```

### 7.3 Processo de Review

1. **Revisão Automática**: CI verifica sintaxe e testes
2. **Revisão Manual**: Mantenedor revisa código e lógica
3. **Feedback**: Solicitações de mudanças podem ser feitas
4. **Merge**: Após aprovação, o PR será mesclado

---

## 8. Seguridad

### 8.1 Não Exponha Dados Sensíveis

- **NÃO** adicione credenciais reais no código
- **NÃO** faça commit de arquivos `.env` ou `.env.local`
- **NÃO** exponha tokens ou chaves de API
- Use `.env.example` para示例 de variáveis

### 8.2 Vulnerabilidades

Se encontrar uma vulnerabilidade:

1. ** NÃO** crie uma issue pública
2. Envie um email para: security@plattadata.com
3. Descreva o problema com detalhes
4. Aguarde resposta em até 48 horas

---

## 9. Recursos Adicionais

### 9.1 Documentação

- [README.md](README.md) - Visão geral do projeto
- [GEMINI.md](GEMINI.md) - Especificações técnicas
- [docs/REFATORACAO_SEGURA_IA.md](docs/REFATORACAO_SEGURA_IA.md) - Playbook de refatoração segura com IA
- [INSTRUCOES_HOSTINGER.md](INSTRUCOES_HOSTINGER.md) - Instalação
- [docs/CLOUDFLARE.md](docs/CLOUDFLARE.md) - Configuração Cloudflare

### 9.2 Links Úteis

- [PSR-12](https://www.php-fig.org/psr/psr-12/) - Padrão de código PHP
- [PHPStan](https://phpstan.org/) - Análise estática
- [PHPUnit](https://phpunit.de/) - Framework de testes

---

## 10. Perguntas Frequentes

### Posso contribuir sem ser desenvolvedor?
Sim! Você pode:
- Reportar bugs
- Sugerir funcionalidades
- Melhorar documentação
- Traduzir arquivos

### Como obtenho ajuda?
- Crie uma issue para dúvidas
- Use a tag `help-wanted` para pedir assistência
- Entre em contato pela comunidade

### Quem mantém o projeto?
O projeto é mantido pela equipe Plattadata com contribuições da comunidade.

---

## Licença

Ao contribuir com este projeto, você concorda que suas contribuições serão licenciadas sob a licença proprietária do projeto.

---

*Agradecemos sua contribuição!*
