<?php declare(strict_types=1);
$comparison = $comparison ?? [];
$cnpjs = $cnpjs ?? [];
?>

<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/admin">Admin</a></li>
        <li class="breadcrumb-item"><a href="/admin/analytics">Analytics</a></li>
        <li class="breadcrumb-item active" aria-current="page">Comparar empresas</li>
    </ol>
</nav>

<style>
        :root {
            --primary: var(--brand, #0d9488);
            --primary-dark: var(--brand-hover, #0f766e);
            --success: #198754;
            --info: #0d6efd;
            --warning: #d97706;
            --danger: #dc3545;
        }

        [data-theme="dark"] .section-header {
            background: linear-gradient(135deg, var(--primary) 0%, #155e63 100%);
        }

        .section-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            padding: 18px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .section-header p {
            margin: 0;
            opacity: 0.85;
            font-size: 0.9rem;
        }

        .card {
            border: 0;
            border-radius: 12px;
            box-shadow: var(--shadow-sm, 0 0.125rem 0.5rem rgba(15, 23, 42, 0.08));
            background: var(--surface, #fff);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            border-radius: 12px 12px 0 0 !important;
        }
        
        .company-selector {
            position: relative;
        }
        
        [data-theme="dark"] .company-selector .form-control {
            border-color: var(--border);
            background: var(--surface);
            color: var(--text);
        }
        
        .company-selector .form-control {
            padding-right: 40px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .company-selector .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.1);
        }
        
        .company-selector .clear-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 4px 8px;
            display: none;
            border-radius: 4px;
        }
        
        [data-theme="dark"] .company-selector .clear-btn:hover { 
            color: var(--danger);
            background: var(--danger-bg);
        }
        
        [data-theme="dark"] .section-header h4,
        [data-theme="dark"] .section-header p {
            color: #fff !important;
        }
        
        [data-theme="dark"] .section-header .btn-outline-light {
            border-color: rgba(255,255,255,0.5);
            color: #fff;
        }
        
        [data-theme="dark"] .section-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }

        [data-theme="dark"] .company-selector .clear-btn {
            color: var(--text-muted);
        }

        [data-theme="dark"] .company-selector.has-value .clear-btn { display: block; }

        [data-theme="dark"] .autocomplete-item:hover, 
        [data-theme="dark"] .autocomplete-item.active { 
            background: var(--brand-light);
        }
        
        [data-theme="dark"] .compare-box.has-company { 
            border-color: var(--primary);
            background: var(--brand-light);
        }
        
        [data-theme="dark"] .compare-vs {
            background: var(--surface);
            box-shadow: var(--shadow-sm);
        }
        
        [data-theme="dark"] .company-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            margin: -20px -20px 20px -20px;
        }

        [data-theme="dark"] .metric-value.winner {
            background: var(--success-bg);
            color: var(--success);
            font-weight: 700;
        }
        
        [data-theme="dark"] .info-badge.status-inativa { 
            background: var(--danger-bg); 
            color: var(--danger-text); 
        }

        [data-theme="dark"] .info-badge.simples { 
            background: var(--warning-bg); 
            color: var(--warning-text);
        }

        [data-theme="dark"] .info-badge.mei { 
            background: var(--info-bg); 
            color: var(--info-text);
        }

        [data-theme="dark"] .analytics-card:hover {
            box-shadow: var(--shadow);
        }

        [data-theme="dark"] .compare-panel .card-header h5 {
            color: #fff !important;
        }
        
        .company-selector.has-value .clear-btn { display: block; }
        
        .company-selector .clear-btn:hover { 
            color: var(--danger);
            background: #fef2f2;
        }
        
        [data-theme="dark"] .autocomplete-dropdown {
            background: var(--surface);
            border-color: var(--primary);
        }
        
        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1000;
            background: white;
            border: 2px solid var(--primary);
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 300px;
            overflow-y: auto;
            display: none;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .autocomplete-dropdown.show { display: block; }
        
        [data-theme="dark"] .autocomplete-item {
            border-color: var(--border);
        }
        
        [data-theme="dark"] .autocomplete-item:hover, 
        [data-theme="dark"] .autocomplete-item.active { 
            background: var(--brand-light);
        }
        
        .autocomplete-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }
        
        .autocomplete-item:hover, .autocomplete-item.active { 
            background: linear-gradient(135deg, rgba(15, 118, 110, 0.1) 0%, rgba(21, 94, 99, 0.1) 100%);
        }
        
        .autocomplete-item:last-child { border-bottom: none; }
        
        [data-theme="dark"] .autocomplete-item .company-name {
            color: var(--text);
        }
        
        .autocomplete-item .company-name { 
            font-weight: 600; 
            color: var(--primary-dark);
        }
        
        [data-theme="dark"] .autocomplete-item .company-cnpj { 
            color: var(--text-muted);
        }
        
        .autocomplete-item .company-cnpj { 
            font-family: monospace; 
            font-size: 0.8rem; 
            color: #64748b;
        }
        
        [data-theme="dark"] .selected-company {
            background: var(--brand-light);
            border-color: var(--primary);
        }
        
        .selected-company {
            background: linear-gradient(135deg, rgba(15, 118, 110, 0.05) 0%, rgba(21, 94, 99, 0.05) 100%);
            border: 2px solid var(--primary);
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
        }
        
        .compare-container {
            display: flex;
            gap: 20px;
            align-items: stretch;
        }
        
        [data-theme="dark"] .compare-box {
            background: var(--surface);
            border-color: var(--border);
        }
        
        [data-theme="dark"] .compare-box h6 {
            color: var(--text);
        }
        
        .compare-box h6 {
            color: var(--primary-dark);
        }
        
        .compare-box {
            flex: 1;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            min-height: 150px;
            transition: all 0.3s;
        }
        
        .compare-box.has-company { 
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(15, 118, 110, 0.02) 0%, rgba(21, 94, 99, 0.02) 100%);
        }
        
        [data-theme="dark"] .compare-vs {
            background: var(--surface);
            box-shadow: var(--shadow-sm);
        }
        
        .compare-vs {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
            background: #fff;
            padding: 10px 15px;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .company-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            margin: -20px -20px 20px -20px;
        }
        
        .company-header h5 { margin: 0; font-size: 1.1rem; }
        
        .winner-card {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%) !important;
            border: 2px solid var(--success) !important;
        }
        
        .winner-card .company-header {
            background: linear-gradient(135deg, var(--success) 0%, #047857 100%);
        }
        
        .winner-badge {
            position: absolute;
            top: -12px;
            right: 16px;
            background: var(--success);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        }
        
        .info-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 6px;
        }
        
        .info-badge.status-ativa { 
            background: rgba(5, 150, 105, 0.15); 
            color: var(--success); 
        }
        
        .info-badge.status-inativa { 
            background: rgba(220, 38, 38, 0.15); 
            color: var(--danger); 
        }
        
        .info-badge.simples { 
            background: rgba(217, 119, 6, 0.15); 
            color: var(--warning); 
        }
        
        .info-badge.mei { 
            background: rgba(2, 132, 199, 0.15); 
            color: var(--info); 
        }
        
        [data-theme="dark"] .winner-card {
            background: var(--success-bg) !important;
            border-color: var(--success) !important;
        }
        
        [data-theme="dark"] .winner-card .company-header {
            background: linear-gradient(135deg, var(--success) 0%, #047857 100%);
        }
        
        .winner-card {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%) !important;
            border: 2px solid var(--success) !important;
        }
        
        .winner-card .company-header {
            background: linear-gradient(135deg, var(--success) 0%, #047857 100%);
        }
        
        [data-theme="dark"] .metric-row {
            border-color: var(--border);
        }
        
        .metric-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .metric-row:last-child { border-bottom: none; }
        
        [data-theme="dark"] .metric-row {
            border-color: var(--border);
        }
        
        [data-theme="dark"] .metric-label { 
            color: var(--text-muted); 
        }
        
        .metric-label { 
            color: #475569; 
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .metric-value {
            display: inline-block;
            text-align: center;
            min-width: 80px;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.95rem;
            color: #334155;
            background: #f1f5f9;
        }
        
        [data-theme="dark"] .metric-value {
            background: var(--surface-alt);
            color: var(--text);
        }
        
        .metric-value.winner {
            background: linear-gradient(135deg, rgba(5, 150, 105, 0.15) 0%, rgba(52, 211, 153, 0.15) 100%);
            color: var(--success);
            font-weight: 700;
        }
        
        [data-theme="dark"] .diff-indicator.tie { 
            background: var(--surface-alt); 
            color: var(--text-muted); 
        }
        
        .diff-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .diff-indicator.winner-1 { 
            background: linear-gradient(135deg, rgba(5, 150, 105, 0.15) 0%, rgba(52, 211, 153, 0.15) 100%);
            color: var(--success); 
        }
        
        .diff-indicator.winner-2 { 
            background: linear-gradient(135deg, rgba(2, 132, 199, 0.15) 0%, rgba(56, 189, 248, 0.15) 100%);
            color: var(--info); 
        }
        
        .diff-indicator.tie { 
            background: #f1f5f9; 
            color: #64748b; 
        }
        
        [data-theme="dark"] .diff-indicator.winner-1 { 
            background: var(--success-bg);
            color: var(--success-text); 
        }
        
        [data-theme="dark"] .diff-indicator.winner-2 { 
            background: var(--info-bg);
            color: var(--info-text); 
        }
        
        .table thead th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            border-color: #e2e8f0;
            padding: 14px 16px;
            font-weight: 600;
        }
        
        .table tbody td {
            color: #334155;
            background: #fff;
            padding: 14px 16px;
            border-color: #f1f5f9;
            vertical-align: middle;
        }
        
        [data-theme="dark"] .table thead th {
            background: var(--surface-alt) !important;
            color: var(--text) !important;
            border-color: var(--border) !important;
        }
        
        [data-theme="dark"] .table tbody td {
            background: var(--surface) !important;
            color: var(--text) !important;
            border-color: var(--border) !important;
        }
        
        [data-theme="dark"] .table-hover > tbody > tr:hover > * {
            background-color: var(--brand-light);
        }
        
        .table tbody tr:nth-child(odd) td {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        [data-theme="dark"] .table tbody tr:nth-child(odd) td {
            background-color: rgba(255, 255, 255, 0.02);
        }
        
        .table-hover > tbody > tr:hover > * {
            background-color: rgba(15, 118, 110, 0.08);
        }
        
        .compare-panel {
            margin-bottom: 1.5rem;
        }

        .analytics-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .analytics-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.35rem 0.85rem rgba(15, 23, 42, 0.12);
        }

        #compareBtn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        @media (max-width: 991.98px) {
            .compare-container {
                flex-direction: column;
            }

            .compare-vs {
                border-radius: 999px;
                justify-content: center;
                width: 100%;
            }
        }
</style>

<div class="section-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="mb-1"><i class="bi bi-bar-chart-line me-2"></i>Comparar empresas</h4>
            <p>Selecione duas empresas para comparar consultas, visualizacoes e indicadores.</p>
        </div>
        <a href="/admin/analytics" class="btn btn-outline-light btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Voltar ao Analytics
        </a>
    </div>
</div>

        <!-- Seleção de Empresas -->
        <div class="card compare-panel analytics-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-search me-2"></i>Selecionar empresas para comparacao</h5>
            </div>
            <div class="card-body">
                <div class="compare-container">
                    <div class="compare-box" id="box1">
                        <h6 class="mb-3"><i class="bi bi-building me-2"></i>Empresa 1</h6>
                        <div class="company-selector" id="selector1">
                            <input type="text" class="form-control" id="search1" placeholder="Digite nome fantasia, razão social ou CNPJ..." autocomplete="off">
                            <button type="button" class="clear-btn" onclick="clearSelection(1)"><i class="bi bi-x-lg"></i></button>
                            <div class="autocomplete-dropdown" id="dropdown1"></div>
                        </div>
                        <div class="selected-company d-none" id="selected1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold" id="selected1-name"></div>
                                    <div class="small text-muted"><span id="selected1-cnpj"></span> · <span id="selected1-location"></span></div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearSelection(1)"><i class="bi bi-x"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="compare-vs"><span>VS</span></div>
                    <div class="compare-box" id="box2">
                        <h6 class="mb-3"><i class="bi bi-building me-2"></i>Empresa 2</h6>
                        <div class="company-selector" id="selector2">
                            <input type="text" class="form-control" id="search2" placeholder="Digite nome fantasia, razão social ou CNPJ..." autocomplete="off">
                            <button type="button" class="clear-btn" onclick="clearSelection(2)"><i class="bi bi-x-lg"></i></button>
                            <div class="autocomplete-dropdown" id="dropdown2"></div>
                        </div>
                        <div class="selected-company d-none" id="selected2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold" id="selected2-name"></div>
                                    <div class="small text-muted"><span id="selected2-cnpj"></span> · <span id="selected2-location"></span></div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearSelection(2)"><i class="bi bi-x"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button type="button" class="btn btn-brand btn-lg" id="compareBtn" onclick="compareCompanies()" disabled>
                        <i class="bi bi-graph-up me-2"></i>Comparar empresas
                    </button>
                </div>
            </div>
        </div>

        <!-- Resultados Comparativos -->
        <div id="results" class="d-none">
            <!-- Cards das Empresas -->
            <div class="row mb-4 g-3">
                <div class="col-md-6">
                    <div class="card h-100 position-relative analytics-card" id="card1">
                        <div class="winner-badge d-none" id="winner1"><i class="bi bi-trophy-fill me-1"></i>Lider</div>
                        <div class="company-header">
                            <h5 id="result1-name">-</h5>
                            <small id="result1-cnpj" class="opacity-75">-</small><br>
                            <span class="info-badge" id="result1-status">-</span>
                            <span class="info-badge" id="result1-porte">-</span>
                            <span class="info-badge" id="result1-simples">-</span>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="h3 mb-0 text-primary" id="result1-consults">-</div>
                                    <small class="text-muted">Consultas</small>
                                </div>
                                <div class="col-6">
                                    <div class="h3 mb-0" id="result1-views">-</div>
                                    <small class="text-muted">Visualizacoes</small>
                                </div>
                            </div>
                            <hr>
                            <div class="row text-center small mb-3">
                                <div class="col-6">
                                    <div id="result1-capital">-</div>
                                    <small class="text-muted">Capital Social</small>
                                </div>
                                <div class="col-6">
                                    <div id="result1-users">-</div>
                                    <small class="text-muted">Usuarios unicos</small>
                                </div>
                            </div>
                            <hr>
                            <div class="small text-muted">
                                <div class="mb-1"><i class="bi bi-geo-alt me-2"></i><span id="result1-location">-</span></div>
                                <div class="mb-1"><i class="bi bi-calendar3 me-2"></i>Data de abertura: <span id="result1-opened">-</span></div>
                                <div><i class="bi bi-clock-history me-2"></i>Ultima consulta: <span id="result1-lastconsult">-</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100 position-relative analytics-card" id="card2">
                        <div class="winner-badge d-none" id="winner2"><i class="bi bi-trophy-fill me-1"></i>Lider</div>
                        <div class="company-header">
                            <h5 id="result2-name">-</h5>
                            <small id="result2-cnpj" class="opacity-75">-</small><br>
                            <span class="info-badge" id="result2-status">-</span>
                            <span class="info-badge" id="result2-porte">-</span>
                            <span class="info-badge" id="result2-simples">-</span>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="h3 mb-0 text-primary" id="result2-consults">-</div>
                                    <small class="text-muted">Consultas</small>
                                </div>
                                <div class="col-6">
                                    <div class="h3 mb-0" id="result2-views">-</div>
                                    <small class="text-muted">Visualizacoes</small>
                                </div>
                            </div>
                            <hr>
                            <div class="row text-center small mb-3">
                                <div class="col-6">
                                    <div id="result2-capital">-</div>
                                    <small class="text-muted">Capital Social</small>
                                </div>
                                <div class="col-6">
                                    <div id="result2-users">-</div>
                                    <small class="text-muted">Usuarios unicos</small>
                                </div>
                            </div>
                            <hr>
                            <div class="small text-muted">
                                <div class="mb-1"><i class="bi bi-geo-alt me-2"></i><span id="result2-location">-</span></div>
                                <div class="mb-1"><i class="bi bi-calendar3 me-2"></i>Data de abertura: <span id="result2-opened">-</span></div>
                                <div><i class="bi bi-clock-history me-2"></i>Ultima consulta: <span id="result2-lastconsult">-</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabela de Diferenças -->
            <div class="card mb-4 analytics-card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-table me-2"></i>Comparativo detalhado de metricas</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="comparisonTable">
                            <thead>
                                <tr>
                                    <th style="width: 25%">Métrica</th>
                                    <th class="text-center" style="width: 20%">Empresa 1</th>
                                    <th class="text-center" style="width: 20%">Empresa 2</th>
                                    <th class="text-center" style="width: 35%">Diferença</th>
                                </tr>
                            </thead>
                            <tbody id="diffTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Gráfico -->
            <div class="card analytics-card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-bar-chart-fill me-2"></i>Grafico comparativo</h5></div>
                <div class="card-body">
                    <canvas id="compareChart"></canvas>
                </div>
            </div>
        </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        let selectedCompanies = { 1: null, 2: null };
        let searchTimeouts = { 1: null, 2: null };
        let chart = null;

        document.addEventListener('DOMContentLoaded', function() {
            setupSearch(1);
            setupSearch(2);
            updateTableTheme();
        });

        function setupSearch(num) {
            const input = document.getElementById('search' + num);
            const dropdown = document.getElementById('dropdown' + num);
            
            input.addEventListener('input', function() {
                const term = this.value.trim();
                if (term.length < 2) { dropdown.classList.remove('show'); return; }
                if (searchTimeouts[num]) clearTimeout(searchTimeouts[num]);
                searchTimeouts[num] = setTimeout(() => searchCompanies(num, term), 300);
            });

            input.addEventListener('focus', function() {
                if (this.value.trim().length >= 2) dropdown.classList.add('show');
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('#selector' + num)) dropdown.classList.remove('show');
            });
        }

        function updateChartTheme() {
            if (!chart) return;
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const textColor = isDark ? '#e6edf7' : '#155e63';
            const gridColor = isDark ? '#2d3c56' : '#e2e8f0';
            const tickColor = isDark ? '#b8c6d9' : '#475569';
            
            chart.options.plugins.legend.labels.color = textColor;
            chart.options.plugins.title.color = textColor;
            chart.options.scales.y.ticks.color = tickColor;
            chart.options.scales.y.grid.color = gridColor;
            chart.options.scales.x.ticks.color = tickColor;
            chart.options.scales.x.grid.color = gridColor;
            chart.update();
        }

        function updateTableTheme() {
            const table = document.getElementById('comparisonTable');
            if (!table) return;
            
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const theadCells = table.querySelectorAll('thead th');
            const rows = table.querySelectorAll('tbody tr');
            
            if (isDark) {
                theadCells.forEach(th => {
                    th.style.background = 'var(--surface-alt)';
                    th.style.color = '#fff';
                    th.style.borderColor = 'var(--border)';
                });
                rows.forEach((row, rowIndex) => {
                    const cells = row.querySelectorAll('td');
                    cells.forEach(cell => {
                        cell.style.background = rowIndex % 2 === 0 ? 'var(--surface)' : 'rgba(255,255,255,0.03)';
                        cell.style.color = '#fff';
                        cell.style.borderColor = 'var(--border)';
                    });
                });
            } else {
                theadCells.forEach(th => {
                    th.style.background = 'linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%)';
                    th.style.color = '#fff';
                    th.style.borderColor = '#e2e8f0';
                });
                rows.forEach((row, rowIndex) => {
                    const cells = row.querySelectorAll('td');
                    cells.forEach((cell, cellIndex) => {
                        cell.style.background = rowIndex % 2 === 0 ? '#fff' : 'rgba(0,0,0,0.02)';
                        cell.style.color = '#334155';
                        cell.style.borderColor = '#f1f5f9';
                    });
                });
            }
        }

        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'data-theme') {
                    updateChartTheme();
                    updateTableTheme();
                }
            });
        });
        observer.observe(document.documentElement, { attributes: true });
        
        async function searchCompanies(num, term) {
            const dropdown = document.getElementById('dropdown' + num);
            try {
                const response = await fetch('/admin/analytics/api/search?q=' + encodeURIComponent(term));
                const companies = await response.json();
                dropdown.innerHTML = '';
                if (companies.length === 0) {
                    dropdown.innerHTML = '<div class="autocomplete-item text-muted"><i class="bi bi-search me-2"></i>Nenhuma empresa encontrada</div>';
                    dropdown.classList.add('show');
                    return;
                }
                companies.forEach(company => {
                    const item = document.createElement('div');
                    item.className = 'autocomplete-item';
                    item.innerHTML = `
                        <div class="company-name">${company.name}</div>
                        <div class="text-muted small">
                            <span class="company-cnpj">${formatCnpj(company.cnpj)}</span>
                            <span class="badge bg-${company.status === 'ativa' ? 'success' : 'secondary'} ms-1">${company.status}</span>
                        </div>
                    `;
                    item.addEventListener('click', () => selectCompany(num, company));
                    dropdown.appendChild(item);
                });
                dropdown.classList.add('show');
            } catch (error) { 
                console.error('Erro na busca:', error); 
                dropdown.innerHTML = '<div class="autocomplete-item text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Erro ao buscar</div>';
                dropdown.classList.add('show');
            }
        }
        
        function selectCompany(num, company) {
            selectedCompanies[num] = company;
            const input = document.getElementById('search' + num);
            const dropdown = document.getElementById('dropdown' + num);
            const selected = document.getElementById('selected' + num);
            
            input.value = company.name;
            document.getElementById('selector' + num).classList.add('has-value');
            dropdown.classList.remove('show');
            selected.classList.remove('d-none');
            
            document.getElementById('selected' + num + '-name').textContent = company.name;
            document.getElementById('selected' + num + '-cnpj').textContent = formatCnpj(company.cnpj);
            document.getElementById('selected' + num + '-location').textContent = company.location;
            document.getElementById('box' + num).classList.add('has-company');
            updateCompareButton();
        }

        function clearSelection(num) {
            selectedCompanies[num] = null;
            document.getElementById('search' + num).value = '';
            document.getElementById('selector' + num).classList.remove('has-value');
            document.getElementById('selected' + num).classList.add('d-none');
            document.getElementById('box' + num).classList.remove('has-company');
            updateCompareButton();
        }

        function updateCompareButton() {
            document.getElementById('compareBtn').disabled = !(selectedCompanies[1] && selectedCompanies[2]);
        }

        function formatCnpj(cnpj) {
            if (!cnpj) return '-';
            const clean = cnpj.replace(/\D/g, '');
            if (clean.length === 14) {
                return clean.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
            }
            return cnpj;
        }
        
        function cleanCnpj(cnpj) {
            return cnpj.replace(/\D/g, '');
        }

        function formatCurrency(value) {
            if (value >= 1000000000) return 'R$ ' + (value / 1000000000).toFixed(2).replace('.', ',') + ' bi';
            if (value >= 1000000) return 'R$ ' + (value / 1000000).toFixed(2).replace('.', ',') + ' mi';
            if (value >= 1000) return 'R$ ' + (value / 1000).toFixed(1).replace('.', ',') + ' mil';
            return 'R$ ' + value.toFixed(2).replace('.', ',');
        }

        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('pt-BR');
        }

        function formatNumber(num) {
            return num.toLocaleString('pt-BR');
        }

        async function compareCompanies() {
            if (!selectedCompanies[1] || !selectedCompanies[2]) return;
            
            const resultsDiv = document.getElementById('results');
            resultsDiv.classList.remove('d-none');
            document.getElementById('compareBtn').innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Carregando...';
            document.getElementById('compareBtn').disabled = true;

            try {
                const cnpj1 = cleanCnpj(selectedCompanies[1].cnpj);
                const cnpj2 = cleanCnpj(selectedCompanies[2].cnpj);
                
                const response = await fetch('/admin/analytics/api/compare-detailed?cnpj1=' + cnpj1 + '&cnpj2=' + cnpj2);
                
                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Erro desconhecido');
                }
                
                const data = await response.json();

                // Update company cards
                updateCompanyCard(1, data.companies['1']);
                updateCompanyCard(2, data.companies['2']);

                // Highlight winners
                const isWinner1 = data.differences.total_consults.winner === '1';
                const isWinner2 = data.differences.total_consults.winner === '2';
                
                document.getElementById('card1').classList.toggle('winner-card', isWinner1);
                document.getElementById('card2').classList.toggle('winner-card', isWinner2);
                document.getElementById('winner1').classList.toggle('d-none', !isWinner1);
                document.getElementById('winner2').classList.toggle('d-none', !isWinner2);

                // Update differences table
                updateDiffTable(data.differences, data.companies);
                setTimeout(updateTableTheme, 100);

                // Update chart
                updateChart(data.companies);

            } catch (error) {
                console.error('Erro ao comparar:', error);
                alert('Erro ao carregar dados: ' + error.message);
            }
            
            document.getElementById('compareBtn').innerHTML = '<i class="bi bi-graph-up me-2"></i>Comparar empresas';
            document.getElementById('compareBtn').disabled = false;
            resultsDiv.scrollIntoView({ behavior: 'smooth' });
        }

        function updateCompanyCard(num, company) {
            document.getElementById('result' + num + '-name').textContent = company.name;
            document.getElementById('result' + num + '-cnpj').textContent = formatCnpj(company.cnpj);
            
            const statusEl = document.getElementById('result' + num + '-status');
            statusEl.textContent = company.status === 'ativa' ? 'Ativa' : (company.status || 'Desconhecido');
            statusEl.className = 'info-badge ' + (company.status === 'ativa' ? 'status-ativa' : 'status-inativa');
            
            document.getElementById('result' + num + '-porte').textContent = company.company_size || '-';
            document.getElementById('result' + num + '-porte').style.display = company.company_size && company.company_size !== '-' ? 'inline-block' : 'none';
            
            const simplesEl = document.getElementById('result' + num + '-simples');
            if (company.simples) {
                simplesEl.textContent = 'Simples Nacional';
                simplesEl.className = 'info-badge simples';
                simplesEl.style.display = 'inline-block';
            } else if (company.mei) {
                simplesEl.textContent = 'MEI';
                simplesEl.className = 'info-badge mei';
                simplesEl.style.display = 'inline-block';
            } else {
                simplesEl.style.display = 'none';
            }
            
            document.getElementById('result' + num + '-consults').textContent = formatNumber(company.total_consults);
            document.getElementById('result' + num + '-views').textContent = formatNumber(company.total_views);
            document.getElementById('result' + num + '-capital').textContent = formatCurrency(company.capital_social);
            document.getElementById('result' + num + '-users').textContent = formatNumber(company.unique_users);
            document.getElementById('result' + num + '-location').textContent = (company.city || '-') + '/' + (company.state || '-');
            document.getElementById('result' + num + '-opened').textContent = formatDate(company.opened_at);
            document.getElementById('result' + num + '-lastconsult').textContent = formatDate(company.last_consult);
        }

        function updateDiffTable(diffs, companies) {
            const tbody = document.getElementById('diffTableBody');
            tbody.innerHTML = '';

            const metrics = [
                { key: 'total_consults', label: 'Total de consultas', icon: 'bi-eye' },
                { key: 'total_views', label: 'Visualizacoes', icon: 'bi-display' },
                { key: 'unique_users', label: 'Usuarios unicos', icon: 'bi-people' },
                { key: 'days_consulted', label: 'Dias com consultas', icon: 'bi-calendar3' },
                { key: 'capital_social', label: 'Capital social', icon: 'bi-currency-dollar', isCurrency: true },
                { key: 'credit_score', label: 'Score de credito', icon: 'bi-shield-check' },
            ];

            metrics.forEach(m => {
                const diff = diffs[m.key];
                if (!diff) return;

                const row = document.createElement('tr');
                const winnerClass = diff.winner === '1' ? 'winner-1' : (diff.winner === '2' ? 'winner-2' : 'tie');
                
                let value1 = m.isCurrency ? formatCurrency(diff.value1) : formatNumber(diff.value1);
                let value2 = m.isCurrency ? formatCurrency(diff.value2) : formatNumber(diff.value2);
                
                const value1Class = diff.winner === '1' ? 'winner' : '';
                const value2Class = diff.winner === '2' ? 'winner' : '';

                let diffText = '';
                if (diff.winner === 'tie') {
                    diffText = '<i class="bi bi-dash-lg"></i> Igual';
                } else {
                    const arrow = diff.winner === '1' ? 'left' : 'right';
                    diffText = '<i class="bi bi-arrow-' + arrow + '"></i> ' + diff.difference_formatted + ' (' + diff.percent_formatted + ')';
                }

                row.innerHTML = `
                    <td><i class="${m.icon} me-2 text-muted"></i>${m.label}</td>
                    <td class="text-center"><span class="metric-value ${value1Class}">${value1}</span></td>
                    <td class="text-center"><span class="metric-value ${value2Class}">${value2}</span></td>
                    <td class="text-center"><span class="diff-indicator ${winnerClass}">${diffText}</span></td>
                `;
                tbody.appendChild(row);
            });
        }

        function updateChart(companies) {
            if (chart) chart.destroy();
            
            const ctx = document.getElementById('compareChart').getContext('2d');
            chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Consultas', 'Visualizacoes', 'Usuarios unicos', 'Dias c/ consultas'],
                    datasets: [{
                        label: companies['1'].name.length > 20 ? companies['1'].name.substring(0, 20) + '...' : companies['1'].name,
                        data: [
                            companies['1'].total_consults,
                            companies['1'].total_views,
                            companies['1'].unique_users,
                            companies['1'].days_consulted
                        ],
                        backgroundColor: 'rgba(15, 118, 110, 0.85)',
                        borderColor: '#0f766e',
                        borderWidth: 2,
                        borderRadius: 8
                    }, {
                        label: companies['2'].name.length > 20 ? companies['2'].name.substring(0, 20) + '...' : companies['2'].name,
                        data: [
                            companies['2'].total_consults,
                            companies['2'].total_views,
                            companies['2'].unique_users,
                            companies['2'].days_consulted
                        ],
                        backgroundColor: 'rgba(245, 158, 11, 0.85)',
                        borderColor: '#d97706',
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { 
                            position: 'top',
                            labels: { color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e6edf7' : '#155e63' }
                        },
                        title: { 
                            display: true, 
                            text: 'Comparativo de metricas', 
                            font: { size: 14, weight: 'bold' },
                            color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e6edf7' : '#155e63'
                        }
                    },
                    scales: { 
                        y: { 
                            beginAtZero: true,
                            ticks: { color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#b8c6d9' : '#475569' },
                            grid: { color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#2d3c56' : '#e2e8f0' }
                        },
                        x: {
                            ticks: { color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#b8c6d9' : '#475569' },
                            grid: { color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#2d3c56' : '#e2e8f0' }
                        }
                    }
                }
            });
        }
    </script>
