-- Migration para suportar CNPJ Alfanumérico (julho 2026)
-- Altera colunas CNPJ de CHAR(14) para VARCHAR(14) para suportar novo formato

-- Companies
ALTER TABLE companies MODIFY cnpj VARCHAR(14) NOT NULL UNIQUE;

-- Company partners  
ALTER TABLE company_partners MODIFY cnpj VARCHAR(14) NULL;
ALTER TABLE company_partners MODIFY partner_cnpj VARCHAR(14) NULL;

-- Company enrichments
ALTER TABLE company_enrichments MODIFY cnpj VARCHAR(14) NOT NULL;

-- Company source payloads
ALTER TABLE company_source_payloads MODIFY cnpj VARCHAR(14) NOT NULL;

-- Company query logs
ALTER TABLE company_query_logs MODIFY cnpj VARCHAR(14) NOT NULL;

-- Company changes
ALTER TABLE company_changes MODIFY cnpj VARCHAR(14) NOT NULL;

-- Company removal requests
ALTER TABLE company_removal_requests MODIFY cnpj VARCHAR(14) NOT NULL;

-- Company competitors
ALTER TABLE company_competitors MODIFY competitor_cnpj VARCHAR(14) NOT NULL;

-- Partner history
ALTER TABLE partner_history MODIFY original_cnpj VARCHAR(14) NOT NULL;
ALTER TABLE partner_history MODIFY related_cnpj VARCHAR(14) NOT NULL;

-- Company mentions
ALTER TABLE company_mentions MODIFY cnpj VARCHAR(14) NOT NULL;
ALTER TABLE company_mentions_history MODIFY cnpj VARCHAR(14) NOT NULL;

-- Favorite groups
ALTER TABLE favorite_groups MODIFY entity_cnpj VARCHAR(14) NULL;

-- User favorites  
ALTER TABLE user_favorites MODIFY entity_cnpj VARCHAR(14) NULL;

-- CNPJ blacklist
ALTER TABLE cnpj_blacklist MODIFY cnpj VARCHAR(14) NOT NULL UNIQUE;

-- API access logs
ALTER TABLE api_access_logs MODIFY cnpj VARCHAR(14) NULL;