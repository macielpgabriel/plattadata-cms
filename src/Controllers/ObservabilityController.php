public function getTopQueriesAjax(): void
    {
        $service = new ObservabilityService();
        $metrics = $service->adminMetrics();
        $topQueries = $metrics['top_queries'] ?? [];
        
        $html = '<div class="card-body"><h5 class="card-title"><i class="bi bi-award me-2 text-warning"></i>Termos de Busca mais Populares</h5><div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Termo</th><th class="text-end">Buscas</th></tr></thead><tbody>';
        
        foreach ($topQueries as $query) {
            $html .= '<tr><td>' . e($query['search_term'] ?? '-') . '</td><td class="text-end"><strong>' . number_format($query['total']) . '</strong></td></tr>';
        }
        
        $html .= '</tbody></table></div></div>';
        
        Response::json(['success' => true, 'html' => $html]);
    }
    
    public function getTopCompaniesAjax(): void
    {
        $service = new ObservabilityService();
        $metrics = $service->adminMetrics();
        $topCompanies = $metrics['top_companies'] ?? [];
        
        $html = '<div class="card-body"><h5 class="card-title"><i class="bi bi-star me-2 text-warning"></i>Empresas Mais Procuradas</h5><div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Empresa</th><th class="text-end">Buscas</th></tr></thead><tbody>';
        
        foreach ($topCompanies as $company) {
            $html .= '<tr><td>' . e($company['trade_name'] ?? $company['cnpj'] ?? '-') . '</td><td class="text-end"><strong>' . number_format($company['total']) . '</strong></td></tr>';
        }
        
        $html .= '</tbody></table></div></div>';
        
        Response::json(['success' => true, 'html' => $html]);
    }