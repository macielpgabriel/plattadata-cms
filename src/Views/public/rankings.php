<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            <div class="mb-5">
                <span class="badge bg-brand bg-opacity-10 text-brand px-3 py-2 rounded-pill mb-3 fw-bold"> Rankings </span>
                <h1 class="display-5 fw-bold mb-3">Rankings Brasil</h1>
                <p class="lead text-muted mb-0">
                    Compare estados, cidades e atividades econômicas por número de empresas e arrecadação.
                </p>
            </div>

            <!-- Filtros -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Buscar</label>
                            <input type="text" id="searchInput" class="form-control" placeholder="Digite para buscar..." onkeyup="applyFilters()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Ordenar por</label>
                            <select id="sortSelect" class="form-select" onchange="applyFilters()">
                                <option value="ranking">Posição</option>
                                <option value="nome">Nome</option>
                                <option value="empresas">Empresas</option>
                                <option value="participacao">Participação</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Ordem</label>
                            <select id="orderSelect" class="form-select" onchange="applyFilters()">
                                <option value="asc">Crescente</option>
                                <option value="desc">Decrescente</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Linhas por tabela</label>
                            <select id="limitSelect" class="form-select" onchange="applyFilters()">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="27">Todas</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Abas -->
            <ul class="nav nav-pills mb-3 flex-wrap gap-1" id="rankingTabs">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-arrecadacao" onclick="switchTab('arrecadacao')">
                        <i class="bi bi-cash-stack me-1"></i>Arrecadação
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-estados" onclick="switchTab('estados')">
                        <i class="bi bi-building me-1"></i>Estados
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-cidades" onclick="switchTab('cidades')">
                        <i class="bi bi-buildings me-1"></i>Cidades
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-cnae" onclick="switchTab('cnae')">
                        <i class="bi bi-briefcase me-1"></i>CNAE
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-porte" onclick="switchTab('porte')">
                        <i class="bi bi-building me-1"></i>Porte
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-status" onclick="switchTab('status')">
                        <i class="bi bi-check-circle me-1"></i>Status
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-regiao" onclick="switchTab('regiao')">
                        <i class="bi bi-globe-americas me-1"></i>Regiões
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Arrecadação -->
                <div class="tab-pane fade show active" id="tab-arrecadacao">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <h2 class="h5 fw-bold mb-0">Arrecadação por Estado</h2>
                            <span class="badge bg-secondary-subtle" id="count-arrecadacao">27</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="table-arrecadacao">
                                <thead class="bg-secondary-subtle">
                                    <tr><th>#</th><th>Estado</th><th>Região</th><th class="text-end">Arrecadação</th><th class="text-end">Participação</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estadoEmpRank['data'] ?? [] as $e): ?>
                                    <tr data-nome="<?= strtolower($e['estado']) ?>" data-empresas="<?= $e['arrecadacao'] ?>" data-participacao="<?= $e['participacao'] ?>">
                                        <td class="ps-4 fw-bold"><?= $e['ranking'] ?>º</td>
                                        <td class="fw-medium"><?= e($e['estado']) ?> (<?= e($e['uf']) ?>)</td>
                                        <td><span class="badge bg-secondary-subtle"><?= e($e['regiao']) ?></span></td>
                                        <td class="text-end"><?= e($e['arrecadacao_formatada']) ?></td>
                                        <td class="text-end"><?= number_format($e['participacao'], 1, ',', '.') ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Estados -->
                <div class="tab-pane fade" id="tab-estados">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <h2 class="h5 fw-bold mb-0">Empresas por Estado</h2>
                            <span class="badge bg-secondary-subtle" id="count-estados">10</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="table-estados">
                                <thead class="bg-secondary-subtle">
                                    <tr><th>#</th><th>Estado</th><th class="text-end">Empresas</th><th class="text-end">Cidades</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estadoEmpRank['data'] ?? [] as $e): ?>
                                    <tr data-nome="<?= strtolower($e['estado']) ?>" data-empresas="<?= $e['empresas'] ?>" data-participacao="<?= $e['participacao'] ?>">
                                        <td class="ps-4 fw-bold"><?= $e['ranking'] ?>º</td>
                                        <td class="fw-medium"><?= e($e['estado']) ?> (<?= e($e['uf']) ?>)</td>
                                        <td class="text-end"><?= number_format($e['empresas'], 0, ',', '.') ?></td>
                                        <td class="text-end"><?= number_format($e['cidades'], 0, ',', '.') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Cidades -->
                <div class="tab-pane fade" id="tab-cidades">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <h2 class="h5 fw-bold mb-0">Cidades com Mais Empresas</h2>
                            <span class="badge bg-secondary-subtle" id="count-cidades">10</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="table-cidades">
                                <thead class="bg-secondary-subtle">
                                    <tr><th>#</th><th>Cidade</th><th>UF</th><th class="text-end">Empresas</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cidadeRank['data'] ?? [] as $c): ?>
                                    <tr data-nome="<?= strtolower($c['cidade']) ?>" data-empresas="<?= $c['num_empresas'] ?>" data-participacao="0">
                                        <td class="ps-4 fw-bold"><?= $c['ranking'] ?>º</td>
                                        <td class="fw-medium"><?= e($c['cidade']) ?></td>
                                        <td><?= e($c['uf']) ?></td>
                                        <td class="text-end"><?= number_format($c['num_empresas'], 0, ',', '.') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- CNAE -->
                <div class="tab-pane fade" id="tab-cnae">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <h2 class="h5 fw-bold mb-0">Atividades CNAE</h2>
                            <span class="badge bg-secondary-subtle" id="count-cnae"><?= count($cnaeRank['data'] ?? []) ?></span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="table-cnae">
                                <thead class="bg-secondary-subtle">
                                    <tr><th>#</th><th>CNAE</th><th>Descrição</th><th>Setor</th><th class="text-end">Empresas</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cnaeRank['data'] ?? [] as $c): ?>
                                    <tr data-nome="<?= strtolower($c['descricao']) ?>" data-empresas="<?= $c['num_empresas'] ?>" data-participacao="<?= $c['participacao'] ?>">
                                        <td class="ps-4 fw-bold"><?= $c['ranking'] ?>º</td>
                                        <td class="font-monospace small"><?= e($c['cnae']) ?></td>
                                        <td><?= e($c['descricao']) ?></td>
                                        <td><span class="badge bg-secondary-subtle"><?= e($c['setor']) ?></span></td>
                                        <td class="text-end"><?= number_format($c['num_empresas'], 0, ',', '.') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Porte -->
                <div class="tab-pane fade" id="tab-porte">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <h2 class="h5 fw-bold mb-0">Empresas por Porte</h2>
                            <span class="badge bg-secondary-subtle" id="count-porte"><?= count($porteRank['data'] ?? []) ?></span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="table-porte">
                                <thead class="bg-secondary-subtle">
                                    <tr><th>#</th><th>Porte</th><th>Sigla</th><th class="text-end">Empresas</th><th class="text-end">Part.</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($porteRank['data'] ?? [] as $p): ?>
                                    <tr data-nome="<?= strtolower($p['porte']) ?>" data-empresas="<?= $p['num_empresas'] ?>" data-participacao="<?= $p['participacao'] ?>">
                                        <td class="ps-4 fw-bold"><?= $p['ranking'] ?>º</td>
                                        <td class="fw-medium"><?= e($p['porte']) ?></td>
                                        <td><span class="badge bg-primary"><?= e($p['sigla']) ?></span></td>
                                        <td class="text-end"><?= number_format($p['num_empresas'], 0, ',', '.') ?></td>
                                        <td class="text-end"><?= number_format($p['participacao'], 1, ',', '.') ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="tab-pane fade" id="tab-status">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <h2 class="h5 fw-bold mb-0">Status das Empresas</h2>
                            <span class="badge bg-secondary-subtle" id="count-status"><?= count($statusRank['data'] ?? []) ?></span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="table-status">
                                <thead class="bg-secondary-subtle">
                                    <tr><th>#</th><th>Status</th><th class="text-end">Empresas</th><th class="text-end">Part.</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($statusRank['data'] ?? [] as $s): ?>
                                    <tr data-nome="<?= strtolower($s['status']) ?>" data-empresas="<?= $s['num_empresas'] ?>" data-participacao="<?= $s['participacao'] ?>">
                                        <td class="ps-4 fw-bold"><?= $s['ranking'] ?>º</td>
                                        <td class="fw-medium"><?= e($s['status']) ?></td>
                                        <td class="text-end"><?= number_format($s['num_empresas'], 0, ',', '.') ?></td>
                                        <td class="text-end"><?= number_format($s['participacao'], 1, ',', '.') ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Regiões -->
                <div class="tab-pane fade" id="tab-regiao">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                            <h2 class="h5 fw-bold mb-0">Empresas por Região</h2>
                            <span class="badge bg-secondary-subtle" id="count-regiao"><?= count($regiaoRank['data'] ?? []) ?></span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="table-regiao">
                                <thead class="bg-secondary-subtle">
                                    <tr><th>#</th><th>Região</th><th class="text-end">Empresas</th><th class="text-end">Part.</th><th class="text-end">Estados</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($regiaoRank['data'] ?? [] as $r): ?>
                                    <tr data-nome="<?= strtolower($r['regiao']) ?>" data-empresas="<?= $r['num_empresas'] ?>" data-participacao="<?= $r['participacao'] ?>">
                                        <td class="ps-4 fw-bold"><?= $r['ranking'] ?>º</td>
                                        <td class="fw-medium"><?= e($r['regiao']) ?></td>
                                        <td class="text-end"><?= number_format($r['num_empresas'], 0, ',', '.') ?></td>
                                        <td class="text-end"><?= number_format($r['participacao'], 1, ',', '.') ?>%</td>
                                        <td class="text-end"><?= e($r['estados']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentTab = 'arrecadacao';
let originalData = {};

function switchTab(tabName) {
    currentTab = tabName;
    document.querySelectorAll('#rankingTabs .nav-link').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    applyFilters();
}

function applyFilters() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const sortBy = document.getElementById('sortSelect').value;
    const order = document.getElementById('orderSelect').value;
    const limit = parseInt(document.getElementById('limitSelect').value);
    
    const tables = ['arrecadacao', 'estados', 'cidades', 'cnae', 'porte', 'status', 'regiao'];
    
    tables.forEach(tableId => {
        const table = document.getElementById('table-' + tableId);
        if (!table) return;
        
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        // Filter
        let visibleRows = rows.filter(row => {
            if (!search) return true;
            const nome = row.dataset.nome || '';
            return nome.includes(search);
        });
        
        // Sort
        visibleRows.sort((a, b) => {
            let valA, valB;
            if (sortBy === 'ranking') {
                valA = parseInt(a.querySelector('td').textContent);
                valB = parseInt(b.querySelector('td').textContent);
            } else if (sortBy === 'nome') {
                valA = a.dataset.nome || '';
                valB = b.dataset.nome || '';
            } else if (sortBy === 'empresas') {
                valA = parseInt(a.dataset.empresas || 0);
                valB = parseInt(b.dataset.empresas || 0);
            } else if (sortBy === 'participacao') {
                valA = parseFloat(a.dataset.participacao || 0);
                valB = parseFloat(b.dataset.participacao || 0);
            }
            
            if (order === 'asc') {
                return valA > valB ? 1 : (valA < valB ? -1 : 0);
            } else {
                return valA < valB ? 1 : (valA > valB ? -1 : 0);
            }
        });
        
        // Limit
        const rowsToShow = visibleRows.slice(0, limit);
        tbody.innerHTML = '';
        
        rowsToShow.forEach((row, index) => {
            const newRow = row.cloneNode(true);
            newRow.querySelector('td').textContent = (index + 1) + 'º';
            tbody.appendChild(newRow);
        });
        
        // Update count
        const countEl = document.getElementById('count-' + tableId);
        if (countEl) countEl.textContent = rowsToShow.length;
    });
}
</script>