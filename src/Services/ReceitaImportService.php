<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use Throwable;

/**
 * Serviço para importação massiva dos Dados Abertos da Receita Federal.
 */
final class ReceitaImportService
{
    private PDO $db;
    private int $batchSize = 1000;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Processa um arquivo de ESTABELECIMENTOS (Onde residem os dados de endereço e CNPJ completo).
     */
    public function importEstabelecimentos(string $filePath): int
    {
        if (!is_file($filePath)) {
            throw new \Exception("Arquivo não encontrado: $filePath");
        }

        $handle = fopen($filePath, "r");
        $count = 0;
        $batch = [];

        // SQL otimizado para INSERT/UPDATE em massa
        $sql = "INSERT INTO companies (cnpj, legal_name, trade_name, status, opened_at, street, address_number, district, postal_code, state, city, cnae_main_code) 
                VALUES (:cnpj, :legal_name, :trade_name, :status, :opened_at, :street, :address_number, :district, :postal_code, :state, :city, :cnae)
                ON DUPLICATE KEY UPDATE status = VALUES(status), trade_name = VALUES(trade_name)";
        
        $stmt = $this->db->prepare($sql);

        $this->db->beginTransaction();

        while (($data = fgetcsv($handle, 0, ";")) !== false) {
            // O layout da RFB para estabelecimentos tem ~30 colunas
            // Index 0: CNPJ Base, 1: CNPJ Ordem, 2: CNPJ DV, 3: Identificador, 4: Nome Fantasia, 5: Situação...
            
            $cnpj = $data[0] . $data[1] . $data[2];
            if (strlen($cnpj) !== 14) continue;

            $stmt->execute([
                'cnpj' => $cnpj,
                'legal_name' => 'Importado via RFB', // Razão social vem do arquivo de EMPRESAS
                'trade_name' => mb_convert_encoding($data[4], "UTF-8", "ISO-8859-1"),
                'status' => $this->mapStatus($data[5]),
                'opened_at' => $this->formatDate($data[10]),
                'street' => mb_convert_encoding($data[13] . " " . $data[14], "UTF-8", "ISO-8859-1"),
                'address_number' => $data[15],
                'district' => mb_convert_encoding($data[17], "UTF-8", "ISO-8859-1"),
                'postal_code' => $data[18],
                'state' => $data[19],
                'city' => 'ID_' . $data[20], // Código Tom (RFB) - Precisa de de-para para IBGE
                'cnae' => $data[11]
            ]);

            $count++;

            if ($count % $this->batchSize === 0) {
                $this->db->commit();
                $this->db->beginTransaction();
            }
        }

        $this->db->commit();
        fclose($handle);
        return $count;
    }

    /**
     * Mapeia o código de situação cadastral da RFB para o padrão do CMS.
     */
    private function mapStatus(string $code): string
    {
        return match ($code) {
            '02' => 'ATIVA',
            '04' => 'INAPTA',
            '08' => 'BAIXADA',
            '01' => 'NULA',
            '03' => 'SUSPENSA',
            default => 'OUTRA'
        };
    }

    private function formatDate(string $date): ?string
    {
        if (strlen($date) !== 8) return null;
        return substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
    }
}