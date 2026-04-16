<?php declare(strict_types=1);
use App\Core\Auth;
$canSearch = Auth::can(['admin', 'editor']);
?>
<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item active" aria-current="page">Empresas</li>
    </ol>
</nav>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-3 gap-2 fade-in">
    <h1 class="h3 mb-0">
        <i class="bi bi-building me-2 text-muted"></i>
        <?= !empty($location) ? 'Empresas em ' . e($location['name']) . ' - ' . e($location['state_uf']) : 'Empresas' ?>
    </h1>
    <?php if ($canSearch): ?>
        <a href="/empresas/busca" class="btn btn-brand">
            <i class="bi bi-search me-1"></i>Consultar novo CNPJ
        </a>
    <?php endif; ?>
</div>

<div class="card mb-3 mb-md-4 fade-in stagger-1">
    <div class="card-body p-2 p-md-3">
        <form method="get" action="/empresas" class="row g-2 g-md-3">
            <div class="col-12 col-md-8">
                <label class="form-label small fw-bold" for="search-q">Busca</label>
                <input class="form-control" type="text" id="search-q" name="q" value="<?= e($term) ?>" placeholder="CNPJ, razao social, nome fantasia ou cidade">
            </div>
            <div class="col-5 col-md-2">
                <label class="form-label small fw-bold" for="search-state">UF</label>
                <input class="form-control" type="text" id="search-state" name="state" value="<?= e($state) ?>" maxlength="2" placeholder="SP">
            </div>
            <div class="col-7 col-md-2 d-grid align-items-end">
                <button class="btn btn-outline-primary" type="submit">
                    <i class="bi bi-filter me-1"></i>Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card fade-in stagger-2">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h2 class="h5 mb-0">
                <i class="bi bi-list me-1 text-muted"></i>Resultados
            </h2>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary"><?= e((string) $total) ?> registro(s)</span>
                <?php if ($total > 0): ?>
                    <a href="/empresas/exportar<?= '?q=' . urlencode($term) . '&state=' . urlencode($state) ?>" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-download me-1"></i>Exportar CSV
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead class="text-nowrap">
                <tr>
                    <th class="d-none d-md-table-cell">CNPJ</th>
                    <th class="d-table-cell d-md-none">Dados</th>
                    <th>Razao social</th>
                    <th class="d-none d-lg-table-cell">Nome fantasia</th>
                    <th class="d-none d-md-table-cell">CNAE</th>
                    <th class="d-none d-md-table-cell">Porte</th>
                    <th class="d-none d-md-table-cell">Receita</th>
                    <th class="text-nowrap d-none d-sm-table-cell">Cidade/UF</th>
                    <th class="d-none d-md-table-cell">Situacao</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="10" class="text-muted text-center py-4">
                            <i class="bi bi-search fs-3 d-block mb-2"></i>
                            Nenhum resultado encontrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="d-none d-md-table-cell text-nowrap"><?= cnpj_with_copy($item['cnpj']) ?></td>
                            <td class="d-table-cell d-md-none py-3">
                                    <div class="fw-bold text-body" style="font-size: 1rem; line-height: 1.2;"><?= e($item['legal_name']) ?></div>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <small class="text-muted"><i class="bi bi-hash me-1"></i><?= cnpj_with_copy($item['cnpj']) ?></small>
                                    <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e(($item['city'] ?: '-') . '/' . ($item['state'] ?: '-')) ?></small>
                                </div>
                                <?php if (!empty($item['status'])): ?>
                                    <span class="badge bg-<?= $item['status'] === 'ativa' ? 'success' : 'secondary' ?> mt-2" style="font-size: 0.7rem;">
                                        <?= e($item['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-md-table-cell"><?= e($item['legal_name']) ?></td>
                            <td class="d-none d-lg-table-cell"><?= e($item['trade_name'] ?: '-') ?></td>
                            <td class="d-none d-md-table-cell"><?= !empty($item['cnae_main_code']) ? e($item['cnae_main_code']) : '-' ?></td>
                            <td class="d-none d-md-table-cell"><?= !empty($item['company_size']) ? e($item['company_size']) : '-' ?></td>
                            <td class="d-none d-md-table-cell"><?= !empty($item['revenue_estimate']) ? 'R$ ' . number_format((float) $item['revenue_estimate'], 0, ',', '.') : '-' ?></td>
                            <td class="text-nowrap d-none d-sm-table-cell"><?= e(($item['city'] ?: '-') . '/' . ($item['state'] ?: '-')) ?></td>
                            <td class="d-none d-md-table-cell">
                                <?php if (!empty($item['status'])): ?>
                                    <span class="badge bg-<?= $item['status'] === 'ativa' ? 'success' : 'secondary' ?>">
                                        <?= e($item['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <a class="btn btn-brand btn-sm d-flex align-items-center" href="/empresas/<?= e($item['cnpj']) ?>">
                                        <i class="bi bi-eye"></i><span class="d-none d-sm-inline ms-1">Abrir</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($lastPage > 1): ?>
            <nav class="mt-3" aria-label="Navegacao de paginas">
                <ul class="pagination mb-0 justify-content-center">
                    <?php
                    $prevPage = max(1, $page - 1);
                    $nextPage = min($lastPage, $page + 1);
                    
                    if (!empty($location)) {
                        $baseUrl = "/empresas/em/" . strtolower($location['state_uf']) . "/" . $location['slug'];
                        $queryPrefix = "?";
                    } else {
                        $baseUrl = "/empresas";
                        $queryPrefix = "?" . http_build_query(['q' => $term, 'state' => $state]) . "&";
                    }
                    ?>
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e($baseUrl) . $queryPrefix ?>page=<?= e((string) $prevPage) ?>" aria-label="Pagina anterior">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <li class="page-item disabled">
                        <span class="page-link">Pagina <?= e((string) $page) ?> de <?= e((string) $lastPage) ?></span>
                    </li>
                    <li class="page-item <?= $page >= $lastPage ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e($baseUrl) . $queryPrefix ?>page=<?= e((string) $nextPage) ?>" aria-label="Proxima pagina">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>
