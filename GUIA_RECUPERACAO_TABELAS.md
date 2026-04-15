# 🔧 Guia de Recuperação: Tabela 'companies' não encontrada

## 🎯 Problema

Ao instalar o CMS em um novo banco de dados, você recebe o erro:

```
SQLSTATE[42S02]: Base table or view not found: 1146 
Table 'u249984479_MySQL26platta.companies' doesn't exist
```

## ✅ Solução

A correção foi implementada em `src/Services/SetupService.php`. Agora o sistema:

1. **Valida melhor** se o schema.sql foi executado
2. **Registra logs detalhados** de cada statement
3. **Valida tabelas críticas** após a instalação
4. **Fornece script de recuperação** se houver problema

---

## 🚀 Como Usar a Correção

### Opção 1: Reinstalar (Recomendado se for primeira instalação)

Se você está na **primeira instalação**:

```bash
# 1. Apague o arquivo de lock do setup
rm storage/.setup_completed

# 2. Acesse /install novamente e refaça o setup
# A tabela agora deve ser criada corretamente
```

### Opção 2: Usar Script de Recuperação (Se já tem dados)

Se você já tem dados em produção e precisa recuperar as tabelas:

```bash
# Acesse via SSH/terminal no seu servidor
cd /home/u249984479/domains/plattadata.com/public_html

# Execute o script de recuperação
php scripts/fix-missing-tables.php
```

**Resultado esperado:**
```
========================================
Ferramenta de Recuperação de Tabelas
========================================

✓ Conexão com banco de dados estabelecida

Verificando tabelas críticas...
  ✗ users - FALTANDO
  ✗ companies - FALTANDO
  ✗ site_settings - FALTANDO

Encontradas 3 tabela(s) faltando

Tentando recuperar tabelas...
(Isso pode levar um tempo)

Encontrados 677 statements SQL
.................................................  (50)
.................................................  (100)
...

========================================
Resultado:
  Executados: 677
  Falhados: 0
  Pulados: 0
========================================

Validando tabelas novamente...
  ✓ users - RECUPERADA COM SUCESSO
  ✓ companies - RECUPERADA COM SUCESSO
  ✓ site_settings - RECUPERADA COM SUCESSO

✓ SUCESSO! Todas as tabelas foram recuperadas
```

### Opção 3: Executar Manualmente (Último recurso)

Se o script não funcionar:

1. Acesse **phpMyAdmin** do Hostinger
2. Selecione o banco de dados
3. Clique em **"SQL"**
4. Copie o conteúdo de `database/schema.sql`
5. Cole e clique em **"Executar"**

---

## 📊 O que foi melhorado

### Antes (Problema)
```
❌ Erros silenciosos - exceções capturadas mas não reportadas
❌ Sem feedback - usuário não sabe o que falhou
❌ Sem validação - tabelas podem não ser criadas sem aviso
```

### Depois (Solução)
```
✅ Logs detalhados - cada step é rastreado
✅ Validação automática - verifica tabelas críticas
✅ Script de recuperação - fixa problemas automaticamente
✅ Melhor UX - mensagens claras de progresso
```

---

## 🔍 Verificar Logs

Para entender o que aconteceu:

```bash
# Ver logs de setup
tail -100 storage/logs/php_errors.log

# Ver logs do CMS
tail -100 storage/logs/cms.log

# Ver logs da aplicação
tail -100 storage/logs/app.log
```

Procure por mensagens com "Setup:" para ver o que foi executado.

---

## 🛠️ Troubleshooting

### Erro: "Tabela ainda está faltando após execução"

1. Verifique permissões no banco de dados
2. Certifique-se que o usuário tem permissão `CREATE`
3. Verifique o espaço em disco do servidor
4. Tente executar manualmente via phpMyAdmin

### Erro: "Schema.sql não encontrado"

1. Verifique que o arquivo `database/schema.sql` existe
2. Verifique permissões de leitura
3. Clone o repositório novamente

### Erro: "Conexão com banco falhou"

1. Verifique host, usuário e senha em `.env`
2. Verifique que o banco de dados foi criado
3. Verifique firewall/acesso ao MySQL

---

## 📝 Para Desenvolvedores

O código foi melhorado em:
- `src/Services/SetupService.php::runSchemaIfNeeded()` - Melhor validação e logging
- `src/Services/SetupService.php::validateCriticalTables()` - Nova função de validação
- `scripts/fix-missing-tables.php` - Novo script de recuperação

Veja `ANALISE_PROBLEMA_INSTALACAO.md` para análise técnica completa.

---

## ✨ Resultado

Agora você pode:
- ✅ Instalar com confiança
- ✅ Recuperar automaticamente de falhas
- ✅ Ver logs detalhados do setup
- ✅ Validar que tudo foi criado corretamente

**A instalação do CMS agora é muito mais robusta!** 🎉
