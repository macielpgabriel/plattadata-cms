-- Migration: Tabela de dados gerais do Brasil
-- Criado em: 2026-04-14

CREATE TABLE IF NOT EXISTS brasil_info (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    indicador VARCHAR(50) NOT NULL UNIQUE,
    valor DECIMAL(18,2) NULL,
    texto VARCHAR(100) NULL,
    fuente VARCHAR(100) NULL,
    ano INT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_indicador (indicador)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir dados iniciais do Brasil (IBGE 2022)
INSERT INTO brasil_info (indicador, valor, texto, fuente, ano) VALUES
    ('populacao', 203080400, '203.080.400', 'IBGE - Census 2022', 2022),
    ('pib', 9983000000000, '9,983 trilhões', 'IBGE - PIB 2020', 2020),
    ('pib_per_capita', 49186, 'R$ 49.186', 'IBGE - PIB 2020', 2020),
    ('area_km2', 8515767, '8.515.767', 'IBGE', 2022),
    ('municipios', 5570, '5.570', 'IBGE', 2022),
    ('estados', 27, '27', 'IBGE', 2022)
ON DUPLICATE KEY UPDATE 
    valor = VALUES(valor), 
    texto = VALUES(texto), 
    fuente = VALUES(fuente),
    ano = VALUES(ano),
    updated_at = NOW();