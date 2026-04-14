# Cloudflare - Guia de Configuração de Segurança e Performance

Este documento fornece instruções detalhadas para configurar o **Cloudflare** como CDN e proteção para o CMS Platadata.

---

## 1. Visão Geral

O Cloudflare é uma plataforma de segurança e performance que atua como proxy entre os visitantes e seu servidor de origem. Ele oferece:

- **Proteção DDoS**: Mitigação de ataques Distribuídos de Negação de Serviço
- **SSL/TLS**: Criptografia do tráfego
- **Firewall (WAF)**: Proteção contra ataques comuns (SQL Injection, XSS)
- **CDN**: Aceleração da entrega de conteúdo estático
- **Analytics**: Monitoramento de tráfego e ameaças

---

## 2. Configuração Básica (Obrigatório)

### 2.1 Adicionar Site ao Cloudflare

1. Acesse [cloudflare.com](https://cloudflare.com) e faça login
2. Clique em **Add Site**
3. Insira seu domínio (ex: `seudominio.com`)
4. Selecione o plano (Free é suficiente para CMS)
5. O Cloudflare escaneará seus registros DNS
6. Atualize os nameservers do seu domínio

### 2.2 Ativar SSL/TLS

1. Acesse **SSL/TLS** → **Overview**
2. Selecione **Full (strict)**
3. Isso garante que todo o tráfego entre Cloudflare e visitantes seja criptografado

### 2.3 Forçar HTTPS

1. Vá para **SSL/TLS** → **Edge Certificates**
2. Ative as opções:
   - ✅ **Always Use HTTPS**
   - ✅ **Automatic HTTPS Rewrites**

---

## 3. Configurações de Segurança

### 3.1 WAF (Web Application Firewall)

O WAF protege contra ataques comuns na web.

#### Ativar Regras Gerenciadas

1. Acesse **Security** → **WAF**
2. Na seção **Managed Rules**, ative:
   - ✅ **SQL Injection**
   - ✅ **XSS Attack**
   - ✅ **Remote File Inclusion**
   - ✅ **Local File Inclusion**

#### Criar Regras Personalizadas

Acesse **Security** → **WAF** → **Custom Rules**

**Regra 1: Bloquear países específicos**
```
Field: Country
Operator: equals
Value: [CN, RU, KP]
Action: Block
```

**Regra 2: Bloquear IPs com muitas requisições**
```
Field: Threat Score
Operator: greater than
Value: 30
Action: Block
```

**Regra 3: Proteger área admin**
```
Field: URI
Operator: contains
Value: /admin
Action: Challenge (CAPTCHA)
```

**Regra 4: Proteger login**
```
Field: URI
Operator: contains
Value: /login
Action: Challenge (30 min)
```

### 3.2 Rate Limiting (Limitação de Requisições)

Protege contra ataques de força bruta e DDoS.

1. Acesse **Security** → **WAF** → **Rate limits**
2. Crie uma nova regra:

**Regra: Proteger login**
```
Endpoint: */login*
Requests: 5 per minute
Action: Challenge
```

**Regra: Proteger API**
```
Endpoint: */api/*
Requests: 60 per minute
Action: Block
```

### 3.3 Bot Fight Mode

Protege contra bots maliciosos.

1. Acesse **Security** → **Bots**
2. Ative **Bot Fight Mode** (plano Free)

### 3.4 DDoS Protection

1. Acesse **Security** → **DDoS**
2. Configure:
   - **DDoS alerts**: Ativar
   - **Adaptive Bit Rate**: Ativar

### 3.5 Under Attack Mode

Se seu site estiver sob ataque ativo:

1. Acesse **Overview**
2. Clique em **Under Attack Mode**
3. Isso exibirá uma página de verificação antes de carregar o site

---

## 4. Configurações de Performance

### 4.1 Optimizations (Speed)

1. Acesse **Speed** → **Optimization**
2. Configure:
   - ✅ **Auto Minify**: JavaScript, CSS, HTML
   - ✅ **Brotli**: Ativar
   - ✅ **Rocket Loader**: Desativar (pode afetar JavaScript)
   - ✅ **Early Hints**: Ativar

### 4.2 Caching

1. Acesse **Caching** → **Configuration**
2. Configure:
   - **Browser Cache TTL**: 4 hours
   - **Always Online**: Ativar

### 4.3 Argo (Premium)

Se tiver plano pago, ative **Argo** para roteamento otimizado.

---

## 5. Configurações DNS

### 5.1 Proxy Status

| Tipo | Uso | Proxy |
|------|-----|-------|
| A (raiz) | Site principal | Proxied (laranja) |
| CNAME (www) | Versão www | Proxied (laranja) |
| MX | E-mail | DNS only (cinza) |
| TXT | Verificações | DNS only (cinza) |
| CNAME (api) | API | Proxied (laranja) |

### 5.2 Records Recomendados

```
Tipo      Nome          Conteúdo              Proxy
---------------------------------------------------------------
A         @             192.0.2.1             Proxied
CNAME     www           @                     Proxied
MX        @             mail.seudominio.com   DNS Only
TXT       @             v=spf1...              DNS Only
```

---

## 6. Privacy e Security Settings

### 6.1 SSL/TLS

Em **SSL/TLS** → **Edge Certificates**:

- ✅ **Opportunistic Encryption**: On
- ✅ **TLS 1.3**: On
- ✅ **Automatic HTTPS Rewrites**: On

### 6.2 Security Settings

Em **Security** → **Settings**:

- **Challenge Passage**: 30 minutes
- **User Induction**: 10 seconds
- ✅ **Privacy Pass support**: On

### 6.3 CSP (Content Security Policy)

Adicione no `.htaccess` do seu CMS:

```apache
<IfModule mod_headers.c>
    Header set X-XSS-Protection "1; mode=block"
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
    Header set Cross-Origin-Embedder-Policy: "require-corp"
    Header set Cross-Origin-Opener-Policy: "same-origin"
    Header set Cross-Origin-Resource-Policy: "same-origin"
</IfModule>
```

---

## 7. Analytics e Monitoramento

### 7.1 Dashboard de Segurança

Acesse **Security** → **Overview** para ver:

- **Threats blocked**: Quantas ameaças foram bloqueadas
- **Top threats**: Principais tipos de ameaças
- **Traffic**: Padrões de tráfego

### 7.2 Analytics de Performance

Acesse **Analytics** para ver:

- **Requests**: Total de requisições
- **Bandwidth**: Uso de banda
- **Cache**: Taxa de acerto de cache
- **Latency**: Tempo de resposta

### 7.3 Notifications

Configure alertas:

1. Acesse **Security** → **Notifications** → **Configure**

Alertas recomendados:
- ✅ **Alert me when my origin IP is exposed**
- ✅ **Alert on new Cloudflare Apps**
- ✅ **Weekly summary of security events**
- ✅ **DDoS alert**

---

## 8. Configuração de Workers (Avançado)

Se precisar de funcionalidades customizadas:

### 8.1 Exemplo: Redirecionamento

```javascript
addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request))
})

async function handleRequest(request) {
  const url = new URL(request.url)
  
  if (url.pathname === '/old-page') {
    return Response.redirect('https://seudominio.com/new-page', 301)
  }
  
  return fetch(request)
}
```

---

## 9. Cloudflare API

### 9.1 Gerar API Token

1. Acesse **My Profile** → **API Tokens**
2. Crie um novo token com permissões:
   - ✅ Zone - Read
   - ✅ Cache Purge
   - ✅ DNS - Read/Write

### 9.2 Usar no CMS

Adicione ao `.env`:

```dotenv
CLOUDFLARE_API_TOKEN=seu_token_aqui
CLOUDFLARE_ZONE_ID=sua_zone_id_aqui
```

### 9.3 Purge de Cache via API

```bash
# Limpar cache entire
curl -X DELETE "https://api.cloudflare.com/client/v4/zones/ZONE_ID/purge_cache" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  --data '{"purge_everything": true}'
```

---

## 10. Troubleshooting

### 10.1 Problemas Comuns

| Problema | Causa | Solução |
|----------|-------|---------|
| Erro 525 | SSL não configurado no servidor | Use "Full" em SSL/TLS |
| Erro 520 | Erro desconhecido | Verifique logs do servidor |
| Err_ssl_protocol_error | TLS muito antigo | Atualize o servidor |
| Slow loading | Cache não working | Verifique configurações |

### 10.2 Verificar Status

1. Acesse **Support** → **System Status**
2. Verifique se há problemas conhecidos

### 10.3 Debugging

1. Ative **Development Mode** (15 min)
2. Desative o proxy em um registro específico para testar
3. Use **cloudflare-trace** para verificar configurações

---

## 11. Checklist de Configuração

| Item | Prioridade | Status |
|------|------------|--------|
| Adicionar domínio | 🔴 Obrigatório | ☐ |
| SSL Full (strict) | 🔴 Obrigatório | ☐ |
| Always Use HTTPS | 🔴 Obrigatório | ☐ |
| WAF Rules | 🟡 Recomendado | ☐ |
| Bot Fight Mode | 🟡 Recomendado | ☐ |
| Rate Limiting | 🟡 Recomendado | ☐ |
| DDoS Protection | 🟡 Recomendado | ☐ |
| Auto Minify | 🟢 Opcional | ☐ |
| Notifications | 🟢 Opcional | ☐ |
| Workers | 🟢 Opcional | ☐ |

---

## 12. Plano Recomendado por Tipo de Uso

| Uso | Plano | Recursos |
|-----|-------|----------|
| Pessoal/Blog | Free | SSL, WAF básico, cache |
| Pequeno negócio | Pro ($20/mês) | Rate limit, analytics avançado |
| E-commerce | Business ($200/mês) | WAF avançado, DDoS protection |
| Enterprise | Enterprise | Full suporte, SLA |

---

## Suporte Cloudflare

- **Documentação**: developers.cloudflare.com
- **Community**: community.cloudflare.com
- **Support**: support.cloudflare.com

---

*Última atualização: Abril 2026*