# Refatoração Segura com IA (Playbook Operacional)

Este guia existe para permitir refatorações grandes sem quebrar funções do CMS, mesmo quando o trabalho for dividido entre múltiplas IAs/sessões.

## 1) Regras Não Negociáveis

1. Não alterar comportamento externo sem evidência de compatibilidade.
2. Refatorar em lotes pequenos (1 domínio por vez).
3. Nunca misturar refatoração + mudança de regra de negócio no mesmo PR.
4. Toda mudança deve deixar trilha de validação (o que foi testado e resultado).
5. Em caso de dúvida, preservar fluxo atual e abrir item de risco no handoff.

## 2) Invariantes Críticos do Sistema

- Fluxo de consulta CNPJ: sanitize -> validação -> cache -> fallback de provedores -> enriquecimento -> persistência.
- Fluxo da página de empresa: `CompanyController::show` deve sempre montar fallback entre colunas persistidas + `raw_data` normalizado.
- Controle de acesso: rotas e middlewares (`AuthMiddleware`, `AdminMiddleware`, `StaffMiddleware`, `CsrfMiddleware`).
- LGPD: mascaramento por perfil e trilhas de auditoria.
- Rotas públicas principais:
  - `/`
  - `/empresas`
  - `/empresas/{cnpj}`
  - `/localidades`, `/localidades/{uf}`, `/localidades/{uf}/{slug}`
  - `/atividades`, `/comparacoes`, `/ranking`
- Rotas de atualização:
  - `POST /empresas/{cnpj}/atualizar`
  - `POST /admin/migrations/run`

### Regras obrigatórias para `/empresas/{cnpj}`

1. Nunca depender de um único formato de payload de provedor externo.
2. Sempre mapear aliases de chaves (ex.: `nome_fantasia`/`trade_name`, `telefone`/`phone`).
3. Se dados essenciais estiverem ausentes, permitir auto-refresh com trava (throttle) para evitar loop.
4. Em updates SQL, validar colunas existentes antes de montar `SET` quando a coluna pode não existir em ambientes antigos.
5. `manifest.json` e outros 404 de bot/scanner não devem interromper renderização da página de empresa.

## 3) Estratégia para Não Estourar Contexto

Trabalhar em lotes:

1. Lote 1: `Core` e helpers (sem alterar contratos públicos).
2. Lote 2: `Repositories` (queries e retorno compatível).
3. Lote 3: `Services` por domínio (CNPJ, IBGE, BCB, Compliance).
4. Lote 4: `Controllers` (orquestração e tratamento de erro).
5. Lote 5: `Views/JS` (sem quebrar seletores e endpoints usados pelo front).

Regra por lote:
- Máximo de 3-6 arquivos por PR.
- Diferença orientada por comportamento observável.
- Atualizar documentação de risco do lote.

## 4) Matriz de Regressão (Smoke Test)

Executar após cada lote:

1. Home carrega sem erro.
2. Página de empresa por CNPJ existente abre sem loop.
3. Atualização de empresa (`/atualizar`) funciona sem warning crítico.
4. Atualização manual respeita cooldown por perfil e rate limit por hora.
5. Auto-refresh em `show` só ocorre quando faltam dados essenciais e o `last_synced_at` está acima do limite.
6. Busca/listagem de empresas funciona com paginação.
7. Login/logout e autorização em rotas admin.
8. Endpoints de SEO (`/robots.txt`, `/sitemap.xml`, `/manifest.json`).
9. Healthcheck (`/health`) retorna JSON válido.

## 5) Checklist de PR de Refatoração

- [ ] Escopo pequeno e isolado.
- [ ] Sem alteração de contrato externo (ou documentado).
- [ ] Sem alteração de schema destrutiva.
- [ ] Smoke test executado.
- [ ] Logs revisados (sem novos 500/SQLSTATE).
- [ ] Handoff preenchido.

## 6) Template de Handoff Entre IAs

Use no final de cada lote:

```md
## Handoff - Lote X

### Escopo
- Arquivos alterados:
- Objetivo técnico:

### Compatibilidade
- Contratos preservados:
- Contratos alterados:

### Validação executada
- Testes/comandos:
- Rotas verificadas:
- Resultado:

### Riscos pendentes
- Risco 1:
- Risco 2:

### Próximo lote recomendado
- Arquivos alvo:
- Dependências:
```

## 7) Prompt Curto para Outras IAs

```md
Refatore apenas [DOMÍNIO], sem mudar comportamento externo.
Respeite `docs/REFATORACAO_SEGURA_IA.md` e `GEMINI.md`.
Faça alterações em no máximo [N] arquivos.
No fim, entregue:
1) diff resumido
2) riscos
3) checklist de regressão executado
4) handoff do próximo lote
```

## 8) Prompt de Revisão (QA de Refatoração)

```md
Faça code review focado em regressão:
- contratos quebrados
- mudanças de status code
- mudanças de payload/shape
- efeitos colaterais de DB
- riscos de performance/timeout
Liste achados por severidade com arquivo/linha.
```
