# Instruções de Instalação - Hostinger

Este guia detalha o passo a passo para instalar o **CMS Platadata** na hospedagem Hostinger.

---

## 1. Requisitos Prévios

### 1.1 Conta na Hostinger
- Conta ativa na Hostinger
- Plano de hospedagem (Hobby, Premium ou Business)
- Acesso ao painel de controle

### 1.2 Versão do PHP
- PHP 8.1 ou superior (recomendado 8.3)
- Extensões necessárias:
  - `pdo_mysql`
  - `mbstring`
  - `json`
  - `curl`

Para verificar/alterar a versão do PHP na Hostinger:
1. Acesse o **Painel de Controle**
2. Vá em **PHP** → **Versão do PHP**
3. Selecione 8.3

---

## 2. Preparação dos Arquivos

### 2.1 Estrutura de Arquivos

Antes do upload, certifique-se de que todos os arquivos estão presentes:

```
platadata-cms/
├── .env                    # Arquivo de configurações (criar)
├── .env.example            # Modelo de configurações
├── .htaccess               # Configurações Apache
├── composer.json           # Dependências PHP
├── public/
│   ├── index.php          # Entry point
│   ├── css/               # Arquivos de estilo
│   └── js/                # Arquivos JavaScript
├── src/                   # Código fonte
├── bootstrap/             # Inicialização
├── config/                # Configurações
├── database/              # Schema do banco
├── routes/                # Rotas
├── storage/              # Logs e cache
├── scripts/               # Scripts de manutenção
└── vendor/               # Dependências (após composer install)
```

### 2.2 Arquivos Essenciais para Upload

| Arquivo/Diretório | Importância | Ação |
|-------------------|-------------|------|
| `public/` | 🔴 Obrigatório | Upload completo |
| `src/` | 🔴 Obrigatório | Upload completo |
| `config/` | 🔴 Obrigatório | Upload completo |
| `bootstrap/` | 🔴 Obrigatório | Upload completo |
| `routes/` | 🔴 Obrigatório | Upload completo |
| `database/` | 🔴 Obrigatório | Upload completo |
| `storage/` | 🔴 Obrigatório | Precisa permissão 777 |
| `.env` | 🔴 Obrigatório | Criar antes do upload |
| `composer.json` | 🟡 Recomendado | Upload |
| `.htaccess` | 🟡 Recomendado | Upload |

---

## 3. Configuração do Banco de Dados

### 3.1 Criar Banco de Dados

1. Acesse o **Painel de Controle da Hostinger**
2. Vá em **Banco de Dados** → **Gerenciador de MySQL**
3. Clique em **Criar Banco de Dados**
4. Preencha os dados:

```
Nome do banco: u249984479_seunome
Usuário: u249984479_seuuser
Senha: sua_senha_segura
```

> ⚠️ **Anote as credenciais** - você precisará delas para o arquivo `.env`

### 3.2 Configurações de Conexão

Na Hostinger, use SEMPRE `localhost` como host:

```
Host: localhost
Porta: 3306
Banco: u249984479_seunome
Usuário: u249984479_seuuser
Senha: sua_senha_segura
```

---

## 4. Configuração do Arquivo .env

### 4.1 Criar o Arquivo

Crie o arquivo `.env` na raiz do projeto com o seguinte conteúdo:

```dotenv
# ===========================================
# CONFIGURAÇÕES DA APLICAÇÃO
# ===========================================
APP_NAME=Platadata CMS
APP_URL=https://seudominio.com
APP_TIMEZONE=America/Sao_Paulo
APP_KEY=gerar-chave-aleatoria-aqui

# ===========================================
# BANCO DE DADOS
# ===========================================
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u249984479_seunome
DB_USER=u249984479_seuuser
DB_PASS=sua_senha_segura
DB_CHARSET=utf8mb4

# ===========================================
# ADMINISTRADOR INICIAL
# ===========================================
CMS_ADMIN_NAME=Seu Nome
CMS_ADMIN_EMAIL=seu@email.com
CMS_ADMIN_PASSWORD=sua_senha_segura

# ===========================================
# SEGURANÇA
# ===========================================
SESSION_SECURE=true
ADMIN_2FA_REQUIRED=true
SECURITY_HSTS=true

# ===========================================
# CACHE
# ===========================================
CACHE_DRIVER=file

# ===========================================
# E-MAIL (SMTP Hostinger)
# ===========================================
MAIL_ENABLED=true
MAIL_MAILER=smtp
MAIL_SMTP_HOST=smtp.hostinger.com
MAIL_SMTP_PORT=587
MAIL_SMTP_USERNAME=seu_email@seudominio.com
MAIL_SMTP_PASSWORD=sua_senha_email
MAIL_FROM_NAME=Platadata
MAIL_FROM_ADDRESS=contato@seudominio.com
```

### 4.2 Gerar APP_KEY

Gere uma chave aleatória segura:

```bash
# Via terminal
php -r "echo bin2hex(random_bytes(32));"
```

---

## 5. Upload dos Arquivos

### 5.1 Método 1: Gerenciador de Arquivos (Recomendado)

1. Acesse **Arquivos** → **Gerenciador de Arquivos**
2. Navegue até `public_html`
3. Delete arquivos existentes (se houver)
4. Clique em **Upload**
5. Arraste todos os arquivos do CMS
6. Aguarde o upload completar

### 5.2 Método 2: FTP

1. Use um cliente FTP (FileZilla, Cyberduck)
2. Configure a conexão:
   - Host: `seudominio.com` ou IP do servidor
   - Usuário: encontrado no painel Hostinger
   - Senha: senha FTP
   - Porta: 21
3. Navegue até `public_html`
4. Arraste os arquivos

### 5.3 Permissões de Arquivos

Após o upload, ajuste as permissões:

```bash
# Via terminal (SSH) ou configure no Gerenciador de Arquivos
chmod -R 755 public_html/
chmod -R 755 bootstrap/
chmod -R 755 config/
chmod -R 755 src/
chmod -R 755 scripts/
chmod -R 777 storage/
chmod -R 777 storage/logs/
chmod -R 777 storage/cache/
chmod -R 777 storage/backups/
```

> ℹ️ **Nota**: Na Hostinger, você pode ajustar permissões no Gerenciador de Arquivos:
> - Clique com botão direito no arquivo/pasta
> - Selecione **Permissions**
> - Insira o valor numérico

---

## 6. Instalação via Navegador

### 6.1 Executar o Instalador

1. Acesse `https://seudominio.com/install`
2. O sistema irá:
   - Verificar conexão com o banco
   - Criar as tabelas do schema
   - Criar o usuário administrador inicial
   - Configurar permissões

### 6.2 Verificação de Sucesso

Se tudo estiver correto, você verá:
- ✅ Conexão estabelecida
- ✅ Tabelas criadas
- ✅ Admin criado

### 6.3 Primeiro Acesso

1. Acesse `https://seudominio.com/login`
2. Use as credenciais definidas no `.env`:
   - Email: seu@email.com
   - Senha: sua_senha_segura

---

## 7. Configurações Adicionais

### 7.1 SSL/HTTPS

A Hostinger oferece SSL gratuito via Let's Encrypt:

1. Acesse **Sites** → **Gerenciar**
2. Vá em **SSL**
3. Ative **Free SSL**

### 7.2 Domínio Personalizado

Se usar um domínio próprio:

1. Acesse **Domínios** → **Adicionar Domínio**
2. Conecte o domínio
3. Atualize os nameservers (se necessário)
4. Configure o `.env` com o novo URL

---

## 8. Solução de Problemas

### 8.1 Erro: "Access denied for user"

**Causa**: Credenciais incorretas ou usuário sem permissão

**Solução**:
1. Verifique no painel Hostinger:
   - Banco de dados existe?
   - Usuário existe?
   - Usuário tem permissões no banco?
2. Copie EXATAMENTE as credenciais do painel
3. Use sempre `localhost` como host

### 8.2 Erro: "Database doesn't exist"

**Causa**: Banco de dados não foi criado

**Solução**:
1. Acesse **Banco de Dados** no painel Hostinger
2. Crie o banco com nome exato
3. Atualize o `.env` com o nome correto

### 8.3 Erro: "PDO not found"

**Causa**: Extensão PHP faltando

**Solução**:
1. Vá em **PHP** → **Gerenciar**
2. Verifique se `pdo_mysql` está habilitado
3. Habilite se necessário

### 8.4 Erro 403 Forbidden

**Causa**: Permissões incorretas ou mod_rewrite desabilitado

**Solução**:
1. Verifique permissões (755 para pastas, 644 para arquivos)
2. Confirme que `mod_rewrite` está enabled
3. Verifique o `.htaccess` está presente

### 8.5 Erro 500 Internal Server Error

**Causa**: Erro no PHP ou permissões

**Solução**:
1. Verifique o arquivo `storage/logs/app.log`
2. Confirme que `.env` está correto
3. Verifique versão do PHP (mínimo 8.1)

### 8.6 Página em Branco

**Causa**: Erro fatal silenciado

**Solução**:
1. Adicione ao início do `public/index.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```
2. Após debugar, remova as linhas

---

## 9. Configurações Recomendadas

### 9.1 PHP (php.ini)

Se tiver acesso ao php.ini, configure:

```ini
memory_limit = 256M
max_execution_time = 60
upload_max_filesize = 64M
post_max_size = 64M
```

### 9.2 Cron Jobs (Opcional)

Para backups automáticos, configure no painel Hostinger:

```
30 1 * * * /bin/sh /home/u249984479/public_html/scripts/backup_db.sh
```

---

## 10. Manutenção Pós-Instalação

### 10.1 Tarefas Recomendadas

1. ✅ Alterar senha do admin imediatamente
2. ✅ Configurar SSL (se não自动)
3. ✅ Testar login e logout
4. ✅ Verificar se busca de CNPJ funciona
5. ✅ Configurar e-mail de contato
6. ✅ Revisar configurações em `/admin/configuracoes`

### 10.2 Limpeza de Arquivos Temporários

Após instalação concluída:
- Delete arquivos de setup temporários
- Limpe a pasta `storage/cache/`
- Verifique logs em `storage/logs/`

---

## 11. Checklist de Verificação

| Item | Status |
|------|--------|
| Banco de dados criado | ☐ |
| Arquivos uploadados | ☐ |
| Permissões ajustadas | ☐ |
| Arquivo .env configurado | ☐ |
| Instalador executado | ☐ |
| Login funcionando | ☐ |
| SSL ativado | ☐ |
| E-mail configurado | ☐ |
| Busca CNPJ funcionando | ☐ |

---

## 12. Suporte

Se encontrar problemas:

1. **Verifique os logs**: `storage/logs/app.log`
2. **Consulte a documentação**: `README.md`
3. **Entre em contato**: suporte@plattadata.com

---

*Última atualização: Abril 2026*