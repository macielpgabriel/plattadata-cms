<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

final class MunicipalityImportService
{
    public function importMunicCsv(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return ['success' => 0, 'errors' => 0, 'message' => "Arquivo não encontrado: $filePath"];
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['success' => 0, 'errors' => 0, 'message' => 'Erro ao abrir arquivo'];
        }

        $db = Database::connection();
        $success = 0;
        $errors = 0;
        $skipped = 0;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if (count($data) < 6) {
                $skipped++;
                continue;
            }

            try {
                $codigoTom = trim($data[0] ?? '');
                $codigoIbge = trim($data[1] ?? '');
                $municipioTom = trim($data[2] ?? '');
                $municipioIbge = trim($data[3] ?? '');
                $uf = trim($data[4] ?? '');

                if (empty($codigoIbge) || empty($municipioIbge)) {
                    $skipped++;
                    continue;
                }

                $stmt = $db->prepare('
                    INSERT INTO municipalities (ibge_code, name, state_uf, created_at)
                    VALUES (:ibge_code, :name, :uf, NOW())
                    ON DUPLICATE KEY UPDATE 
                        name = :name_upd,
                        state_uf = :uf_upd,
                        updated_at = NOW()
                ');
                $stmt->execute([
                    'ibge_code' => (int) $codigoIbge,
                    'name' => $municipioIbge,
                    'uf' => $uf,
                    'name_upd' => $municipioIbge,
                    'uf_upd' => $uf,
                ]);

                $success++;
            } catch (Throwable $e) {
                $errors++;
            }
        }

        fclose($handle);

        return [
            'success' => $success,
            'errors' => $errors,
            'skipped' => $skipped,
            'message' => "Importação concluída: $success importados, $errors erros, $skipped ignorados"
        ];
    }

    public function importFromUrl(string $url): array
    {
        $tempFile = sys_get_temp_dir() . '/munic_' . time() . '.csv';

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Plattadata/1.0');

            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$content) {
                return ['success' => 0, 'errors' => 0, 'message' => "Erro ao baixar: HTTP $httpCode"];
            }

            file_put_contents($tempFile, $content);
            $result = $this->importMunicCsv($tempFile);
            unlink($tempFile);

            return $result;
        } catch (Throwable $e) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            return ['success' => 0, 'errors' => 0, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }
}