<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ArrecadacaoRepository
{
    public function salvarCache(int $ano, array $dados): bool
    {
        // Compatibilidade com chamadas legadas do service.
        return $this->salvar($ano, $dados);
    }

    public function salvar(int $ano, array $dados): bool
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            INSERT INTO impostometro_arrecadacao 
            (ano, mes, total, rfb, outros, fonte, data_publicacao, oficial, created_at, updated_at)
            VALUES (:ano, :mes, :total, :rfb, :outros, :fonte, :data_publicacao, :oficial, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                total = VALUES(total),
                rfb = VALUES(rfb),
                outros = VALUES(outros),
                fonte = VALUES(fonte),
                data_publicacao = VALUES(data_publicacao),
                oficial = VALUES(oficial),
                updated_at = NOW()
        ");

        foreach ($dados as $mes => $dadosMes) {
            $stmt->execute([
                ':ano' => $ano,
                ':mes' => $mes,
                ':total' => (float) ($dadosMes['total'] ?? 0),
                ':rfb' => (float) ($dadosMes['rfb'] ?? 0),
                ':outros' => (float) ($dadosMes['outros'] ?? 0),
                ':fonte' => $dadosMes['fonte'] ?? 'Receita Federal',
                ':data_publicacao' => $dadosMes['data_publicacao'] ?? date('Y-m-d'),
                ':oficial' => (int) ($dadosMes['oficial'] ?? 1),
            ]);
        }

        return true;
    }

    public function salvarMes(int $ano, int $mes, array $dados): bool
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            INSERT INTO impostometro_arrecadacao 
            (ano, mes, total, rfb, outros, fonte, data_publicacao, oficial, created_at, updated_at)
            VALUES (:ano, :mes, :total, :rfb, :outros, :fonte, :data_publicacao, :oficial, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                total = VALUES(total),
                rfb = VALUES(rfb),
                outros = VALUES(outros),
                fonte = VALUES(fonte),
                data_publicacao = VALUES(data_publicacao),
                oficial = VALUES(oficial),
                updated_at = NOW()
        ");

        return $stmt->execute([
            ':ano' => $ano,
            ':mes' => $mes,
            ':total' => (float) ($dados['total'] ?? 0),
            ':rfb' => (float) ($dados['rfb'] ?? 0),
            ':outros' => (float) ($dados['outros'] ?? 0),
            ':fonte' => $dados['fonte'] ?? 'Receita Federal',
            ':data_publicacao' => $dados['data_publicacao'] ?? date('Y-m-d'),
            ':oficial' => (int) ($dados['oficial'] ?? 1),
        ]);
    }

    public function buscar(int $ano): array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            SELECT mes, total, rfb, outros, fonte, data_publicacao, oficial, updated_at
            FROM impostometro_arrecadacao
            WHERE ano = :ano
            ORDER BY mes ASC
        ");
        
        $stmt->execute([':ano' => $ano]);
        
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $dados = [];
        foreach ($resultados as $row) {
            $dados[(int) $row['mes']] = [
                'total' => (float) $row['total'],
                'rfb' => (float) $row['rfb'],
                'outros' => (float) $row['outros'],
                'fonte' => $row['fonte'],
                'data_publicacao' => $row['data_publicacao'],
                'oficial' => (bool) $row['oficial'],
                'updated_at' => $row['updated_at'],
            ];
        }
        
        return $dados;
    }

    public function buscarMes(int $ano, int $mes): ?array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            SELECT * FROM impostometro_arrecadacao
            WHERE ano = :ano AND mes = :mes
        ");
        
        $stmt->execute([':ano' => $ano, ':mes' => $mes]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        return [
            'total' => (float) $row['total'],
            'rfb' => (float) $row['rfb'],
            'outros' => (float) $row['outros'],
            'fonte' => $row['fonte'],
            'data_publicacao' => $row['data_publicacao'],
            'oficial' => (bool) $row['oficial'],
        ];
    }

    public function getTotalAcumulado(int $ano): float
    {
        $dados = $this->buscar($ano);
        
        $total = 0;
        foreach ($dados as $mes => $dadosMes) {
            $total += $dadosMes['total'] ?? 0;
        }
        
        return $total;
    }

    public function getUltimaAtualizacao(int $ano): ?string
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->prepare("
            SELECT MAX(updated_at) as ultima_att
            FROM impostometro_arrecadacao
            WHERE ano = :ano
        ");
        
        $stmt->execute([':ano' => $ano]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['ultima_att'] ?? null;
    }

    public function getAnosDisponiveis(): array
    {
        $pdo = Database::connection();
        
        $stmt = $pdo->query("
            SELECT DISTINCT ano FROM impostometro_arrecadacao
            ORDER BY ano DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}
