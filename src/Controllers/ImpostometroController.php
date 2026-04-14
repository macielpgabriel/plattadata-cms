<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\ImpostometroService;
use App\Services\IbgeService;

final class ImpostometroController
{
    private ImpostometroService $service;
    private IbgeService $ibgeService;

    public function __construct()
    {
        $this->service = new ImpostometroService();
        $this->ibgeService = new IbgeService();
    }

    public function index(): void
    {
        $federal = $this->service->getArrecadacaoFederal();
        $contadores = $this->service->getContadoresNacionais();
        $comparativo = $this->service->getComparativoAnual();
        $porEstado = $this->ibgeService->getArrecadacaoPorEstado();
        $evolucao = $this->service->getEvolucaoAnual();
        $internacional = $this->service->getComparativoInternacional();

        $totalArrecadado = $federal['total_arrecadado'] ?? 0;
        $schemaData = [
            '@context' => 'https://schema.org',
            '@type' => 'GovernmentService',
            'name' => 'Impostometro do Brasil',
            'description' => 'Acompanhe em tempo real a arrecadacao de impostos no Brasil. Dados oficiais da Receita Federal.',
            'url' => config('app.url') . '/impostometro',
            'provider' => [
                '@type' => 'GovernmentOrganization',
                'name' => 'Receita Federal do Brasil',
                'url' => 'https://www.gov.br/receitafederal',
            ],
            'areaServed' => [
                '@type' => 'Country',
                'name' => 'Brasil',
            ],
            'serviceType' => 'Arrecadacao de Impostos',
        ];

        View::render('public/impostometro', [
            'title' => 'Impostometro - Arrecadacao de Impostos no Brasil',
            'federal' => $federal,
            'contadores' => $contadores,
            'comparativo' => $comparativo,
            'porEstado' => $porEstado,
            'evolucao' => $evolucao,
            'internacional' => $internacional,
            'metaRobots' => 'index,follow',
            'metaTitle' => 'Impostometro Brasil | Arrecadacao de Impostos 2026',
            'metaDescription' => 'Acompanhe em tempo real a arrecadacao de impostos no Brasil. Dados oficiais do governo federal sobre tributacao, impostos e contribuicoes.',
            'schemaData' => $schemaData,
        ]);
    }

    public function api(): void
    {
        header('Content-Type: application/json');

        $federal = $this->service->getArrecadacaoFederal();
        $contadores = $this->service->getContadoresNacionais();
        $comparativo = $this->service->getComparativoAnual();
        $porEstado = $this->ibgeService->getArrecadacaoPorEstado();

        echo json_encode([
            'sucesso' => true,
            'data' => [
                'federal' => $federal,
                'contadores' => $contadores,
                'comparativo' => $comparativo,
                'por_estado' => $porEstado,
            ],
            'atualizado_em' => date('d/m/Y H:i:s'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
