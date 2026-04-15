# 🔴 Problema: Tabela 'companies' não é criada na instalação

## 📋 Diagnóstico

### Erro Relatado
```
SQLSTATE[42S02]: Base table or view not found: 1146 
Table 'u249984479_MySQL26platta.companies' doesn't exist
```

**Localização:** `src/Repositories/CompanyRepository.php:184`

### Raiz do Problema

A tabela `companies` está definida no arquivo `database/schema.sql` (linha 146), mas **não está sendo criada automaticamente** durante a instalação. O fluxo é:

1. **InstallController::save()** → Salva configurações no `.env`
2. **SetupService::runInitialSetup()** → Inicia o setup
3. **DatabaseSetupService::connectApplicationDatabase()** → Conecta ao banco
4. **SetupService::runSchemaIfNeeded()** → Deveria executar `schema.sql`
5. ❌ **FALHA:** A tabela `companies` não é criada

---

## 🔍 Análise do Código

### SetupService::runSchemaIfNeeded() (linha 184-213)

```php
private function runSchemaIfNeeded(PDO $pdo): void
{
    try {
        // Verifica se a tabela 'companies' já existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'companies'");
        $exists = $stmt->fetch();
        if ($exists) {
            return;  // Se existe, sai
        }
    } catch (PDOException $exception) {
        Logger::warning('Setup: ' . $exception->getMessage());
        Logger::warning('Schema check failed: ' . $exception->getMessage());
        return;  // ⚠️ PROBLEMA: Se houver erro, sai sem executar schema!
    }

    $schemaFile = base_path('database/schema.sql');
    if (!is_file($schemaFile)) {
        return;  // ⚠️ PROBLEMA: Se arquivo não existir, sai sem erro
    }

    $sql = (string) file_get_contents($schemaFile);
    $statements = $this->schemaService->splitSqlStatements($sql);

    try {
        foreach ($statements as $statement) {
            $pdo->exec($statement);  // Executa cada statement
        }
    } catch (PDOException $exception) {
        Logger::warning('Setup: ' . $exception->getMessage());
        // ⚠️ PROBLEMA: Se der erro, apenas registra no log e continua!
    }
}
```

### Possíveis Causas

1. **Erro silencioso na verificação:** A query `SHOW TABLES LIKE 'companies'` falha, e a exceção é capturada sem executar o schema
2. **Arquivo schema.sql não encontrado:** O caminho `base_path('database/schema.sql')` não resolve corretamente
3. **Erro durante execução do SQL:** Um statement falha e a exceção é apenas registrada no log
4. **Ordem de execução:** SetupService foi chamado mas a verificação de 'companies' falhou

### Chamada do runSchemaIfNeeded()

```php
public function runInitialSetup(): void
{
    $this->databaseService->createDatabaseAndUserIfConfigured();
    $pdo = $this->databaseService->connectApplicationDatabase();
    if ($pdo === null) {
        return;  // ⚠️ Se conexão falhar, tudo para
    }

    // ... outras tabelas são criadas primeiro ...

    if ($this->isSetupLocked()) {
        return;  // ⚠️ Se setup estiver "locked", runSchemaIfNeeded não é executado
    }

    $this->runSchemaIfNeeded($pdo);  // ← Aqui deveria criar companies
    // ...
}
```

---

## 🛠️ Soluções Propostas

### Solução 1: Verificação Mais Robusta (Recomendada)

Melhorar o método `runSchemaIfNeeded()` para:
- Validar que o arquivo existe antes de tentar ler
- Registrar erros com mais contexto
- Garantir que nenhum erro silencioso ocorra
- Validar as tabelas críticas após execução

### Solução 2: Executar schema.sql como fallback

Se `runSchemaIfNeeded()` falhar, executar schema completo como backup.

### Solução 3: Criar tabela companies manualmente

Se schema.sql tiver problemas, criar tabela direto via código PHP.

---

## 📝 Recomendações Imediatas (Para o Usuário)

Se receber este erro em produção:

### Opção A: Executar schema.sql manualmente

1. Acesse o phpMyAdmin do Hostinger
2. Abra o banco de dados `u249984479_MySQL26platta`
3. Clique em "SQL"
4. Copie o conteúdo de `database/schema.sql`
5. Cole e execute

### Opção B: Usar script de correção

```bash
php scripts/fix-missing-tables.php
```

(Script ainda será criado)

### Opção C: Reinstalar

```bash
# Apague o arquivo de lock do setup
rm storage/.setup_lock

# Acesse /install novamente e refaça o setup
```

---

## 🔧 Implementação da Correção

Para corrigir permanentemente, vamos:

1. Melhorar validações no `SetupService::runSchemaIfNeeded()`
2. Adicionar logs mais detalhados
3. Criar método para validar tabelas críticas
4. Implementar correção automática se tabelas críticas estiverem faltando

**Status:** Aguardando confirmação para implementar

---

## ⚠️ Problemas Potenciais de Design

1. **Erros silenciosos:** Exceções são capturadas mas apenas registradas no log
2. **Sem feedback visual:** Usuário não sabe o que falhou
3. **Setup lock prematura:** Se `storage/.setup_lock` existir, setup não rodará novamente
4. **Ordem incorreta:** Algumas tabelas dependem da existência de outras

---

## Próximos Passos

- [ ] Implementar correção em `SetupService`
- [ ] Adicionar validação de tabelas críticas
- [ ] Criar script de recuperação (`fix-missing-tables.php`)
- [ ] Adicionar testes para o fluxo de instalação
