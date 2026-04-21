-- Migration: Índices para Performance com 1M+ empresas
-- Executar no banco de produção

-- Índices para buscas frequentes
CREATE INDEX IF NOT EXISTS idx_companies_cnae_main ON companies(cnae_main_code);
CREATE INDEX IF NOT EXISTS idx_companies_opened_at ON companies(opened_at);
CREATE INDEX IF NOT EXISTS idx_companies_status_opened ON companies(status, opened_at);

-- Índices para API e listagens
CREATE INDEX IF NOT EXISTS idx_companies_city_state ON companies(city, state);
CREATE INDEX IF NOT EXISTS idx_companies_trade_name_idx ON companies(trade_name(50));

-- Índices para logs e auditoria (se existirem)
CREATE INDEX IF NOT EXISTS idx_company_changes_cnpj_changed ON company_changes(cnpj, changed_at);
CREATE INDEX IF NOT EXISTS idx_company_changes_type_changed ON company_changes(change_type, changed_at);

-- Otimização para queries de agregação
CREATE INDEX IF NOT EXISTS idx_companies_state_status ON companies(state, status);

-- Index para API de empresas (busca por municipio)
CREATE INDEX IF NOT EXISTS idx_companies_ibge_code ON companies(municipal_ibge_code);