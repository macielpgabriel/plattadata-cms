# Changelog - Histórico de Atualizações

Este arquivo documenta todas as alterações significativas do CMS Platadata. As mudanças são categorizadas em: **Nova Feature**, **Melhoria**, **Correção de Bug**, **Security** e **Breaking Change**.

---

## [1.0.0] - 2026-04-12

### 🔴 Versão Major - Release Inicial

#### ✨ Nova Feature
- **Sistema de Consulta CNPJ Multi-Fonte**: Implementada cadeia de fallback com BrasilAPI, ReceitaWS, CNPJ.ws e OpenCNPJ
- **Enriquecimento Automático de Dados**: Integração com IBGE (população, PIB), ViaCEP (endereços), Nominatim (geolocalização)
- **Sistema de Autenticação Completa**: Login com hash Argon2id, 2FA por e-mail, lockout por tentativas falhas
- **Sistema de Roles e Permissões**: Admin, moderator, editor, viewer com controle de acesso granular
- **Sistema de Remoção LGPD**: Fluxo completo de solicitação de remoção de empresas com verificação e aprovação
- **Sistema de Jobs**: Fila de processamento baseada em banco de dados com interface de gerenciamento
- **Observabilidade Avançada**: Healthcheck, dashboard de métricas, logs de consultas e API Tester

#### 🚀 Melhorias
- **SEO Técnico**: Canonical URLs, Open Graph, Schema.org para empresas, sitemap dinâmico
- **Indicadores Econômicos**: Cotações em tempo real (BCB/PTAX), histórico de câmbio, impostômetro
- **LGPD Compliance**: Mascaramento de dados por perfil, anonimização de IPs, auditoria completa
- **Sistema de Cache**: Multi-driver (file/Redis/Memcached) com TTL configurável
- **Interface Mobile-First**: Design responsivo com breakpoints otimizados para mobile
- **Segurança HTTP**: CSP com nonce, HSTS, headers de segurança completos

#### 🔧 Infraestrutura
- **Arquitetura MVC Customizada**: PHP 8.3+ nativo sem frameworks pesados
- **Setup Automático**: Instalador web e provisionamento automático de banco
- **Sistema de Migrações**: Schema e migrações incrementais para upgrades
- **Scripts de Manutenção**: Backup, restore, cleanup, sincronização de municípios

#### 📚 Documentação
- **README.md**: Documentação completa de instalação, configuração e uso
- **GEMINI.md**: Especificação técnica mestra com diretrizes de desenvolvimento
- **INSTRUCOES_HOSTINGER.md**: Guia detalhado para instalação em hospedagem compartilhada
- **docs/CLOUDFLARE.md**: Configuração de segurança e performance com Cloudflare
- **CHANGELOG.md**: Este arquivo com histórico de alterações

---

## Histórico de Versões Anteriores

### [0.9.0] - Desenvolvimento Inicial
- Primeiras versões do sistema de consulta CNPJ
- Implementação básica de autenticação
- Criação do schema do banco de dados
- Primeiras integrações com APIs externas

---

## Como Contribuir

Para contribuir com o projeto:

1. Fork o repositório
2. Crie uma branch: `git checkout -b feature/nova-feature`
3. Commit suas mudanças: `git commit -am 'Adiciona nova feature'`
4. Push para a branch: `git push origin feature/nova-feature`
5. Crie um Pull Request

Ao criar uma nova feature ou correção, documente as mudanças neste arquivo seguindo o formato:

```markdown
### ✨ Nova Feature
- Descrição da nova funcionalidade

### 🚀 Melhorias
- Descrição das melhorias

### 🐛 Correção de Bug
- Descrição da correção

### 🔒 Security
- Descrição de correções de segurança

### ⚠️ Breaking Change
- Descrição de mudanças que quebram compatibilidade
```

---

## Convenções de Versionamento

Este projeto segue [Semantic Versioning](https://semver.org/):

- **MAJOR** (1.0.0 → 2.0.0): Mudanças incompatíveis com versões anteriores
- **MINOR** (1.0.0 → 1.1.0): Nova funcionalidade compatível com versões anteriores
- **PATCH** (1.0.0 → 1.0.1): Correção de bugs compatível

---

## Lançamentos Futuros

### Planejado para próximas versões
- Dashboard de analytics avançado
- Integração com mais APIs de dados empresariais
- Sistema de notificações por WhatsApp
- API GraphQL
- Dashboard de monitoramento em tempo real
- Exportação de dados em diversos formatos (PDF, Excel)

---

*Este changelog será atualizado a cada nova versão ou alteração significativa.*