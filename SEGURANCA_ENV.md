# 🔒 Guia de Segurança - Arquivo .env

## ⚠️ IMPORTANTE: NUNCA COMMITAR O ARQUIVO `.env`

O arquivo `.env` contém credenciais sensíveis e deve ser **NUNCA** enviado ao GitHub ou qualquer repositório público/privado.

---

## 📋 Regras de Proteção

### 1. **Proteção do Git**

O arquivo `.env` está protegido no `.gitignore`:

```gitignore
# Environment Variables
.env
.env.local
.env.*.local
```

### 2. **Variáveis Sensíveis No `.env`**

Nunca commit arquivos que contenham:

- ✗ Senhas de banco de dados (`DB_PASS`, `DB_ROOT_PASS`)
- ✗ Chaves de criptografia (`APP_KEY`)
- ✗ Tokens de API (`BRAPI_TOKEN`, `PORTAL_TRANSPARENCIA_TOKEN`, `API_KEY`)
- ✗ Credenciais de email (`MAIL_SMTP_PASSWORD`)
- ✗ Chaves privadas (`GOOGLE_DRIVE_PRIVATE_KEY`)
- ✗ Secrets OAuth (`GOOGLE_OAUTH_CLIENT_SECRET`)
- ✗ Qualquer informação de autenticação ou credencial

---

## 🔑 Variáveis Públicas vs. Privadas

### Públicas (PODEM estar em `.env.example`):
- `APP_NAME`
- `APP_URL`
- `APP_TIMEZONE`
- `DB_HOST`
- `DB_PORT`
- `MAIL_SMTP_HOST`
- URLs de APIs externas

### Privadas (NUNCA em repositório):
- Todas as senhas
- Todas as chaves e tokens
- Credenciais de autenticação
- Informações de acesso

---

## 📝 Como Compartilhar Configurações

### ✅ Use `.env.example` para documentar:

```bash
# Documentação pública - SEGURA para GitHub
cp .env .env.example

# Remova todos os valores sensíveis do .env.example
# Deixe apenas placeholders ou valores públicos
```

### ✅ Compartilhe credenciais via:
- WhatsApp/Telegram (direto com o desenvolvedora)
- Email criptografado
- LastPass/1Password compartilhado
- Gerenciador de senhas corporativo

---

## 🛡️ Verificação de Segurança

### Verificar se `.env` está no Git:
```bash
git ls-files | grep ".env"
```

**Resultado esperado:** Nenhuma linha com `.env` (vazio)

### Verificar se `.env` será ignorado:
```bash
git check-ignore .env
```

**Resultado esperado:** `.env` é ignorado

### Ver arquivos que serão commitados:
```bash
git status
```

**Nunca deve aparecer:** `.env` ou variações

---

## ⚡ Fluxo de Desenvolvimento

### Primeiro Setup:

```bash
# 1. Clone o repositório
git clone https://github.com/macielpgabriel/plattadata-cms.git
cd plattadata-cms

# 2. Copie o arquivo de exemplo
cp .env.example .env

# 3. Configure suas credenciais locais
# Edite .env com suas senhas/tokens
nano .env

# 4. Verifique se está ignorado
git check-ignore .env
```

### Desenvolvimento Normal:

```bash
# Sempre trabalhe com seu .env local
# Nunca faça commit dele

# Verifique status antes de commitar
git status  # Não deve aparecer .env

# Faça commits normalmente (o .env será ignorado)
git add .
git commit -m "sua mensagem"
```

---

## 🚨 Cenários de Risco

### ❌ RISCO: Commitar `.env` com credenciais reais

```bash
git add .  # PERIGO! Pode incluir .env
git commit -m "atualizar configurações"
git push
```

**Resultado:** Credenciais expostas no GitHub

### ✅ SEGURO: Usar `.gitignore`

```bash
git status  # Mostra que .env é ignorado
git add .   # Não inclui .env
git commit -m "atualizar configurações"
git push
```

**Resultado:** `.env` protegido, nunca é enviado

---

## 🔄 Se Acidentalmente Commitar `.env`

### ⚠️ AÇÃO IMEDIATA:

1. **Revogue as credenciais:**
   - Mude senha do banco de dados
   - Gere novo `APP_KEY`
   - Revogue tokens de API
   - Altere credenciais de email

2. **Remova do histórico Git:**
```bash
# Opção 1: Remova de um commit específico
git filter-branch --tree-filter 'rm -f .env' -- --all

# Opção 2: Remova e force push
git push origin --force --all

# ⚠️ AVISO: Force push pode afetar colaboradores!
```

3. **Comunique a equipe** sobre o incidente

---

## 📊 Checklist de Segurança

- [ ] `.gitignore` contém `.env`
- [ ] `.env` está localmente mas não no Git
- [ ] `git check-ignore .env` retorna `.env`
- [ ] `.env.example` contém apenas valores públicos
- [ ] Nenhuma senha em arquivos versionados
- [ ] Nenhum token exposto no GitHub
- [ ] Credenciais compartilhadas via canal seguro

---

## 🔗 Recursos

- [Git Ignore Documentation](https://git-scm.com/docs/gitignore)
- [GitHub - Removing Sensitive Data](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository)
- [OWASP - Sensitive Data Exposure](https://owasp.org/www-project-top-ten/)

---

**Última atualização:** 2026-04-14  
**Status:** ✅ Implementado com sucesso
