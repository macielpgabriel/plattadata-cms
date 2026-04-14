<?php declare(strict_types=1);
use App\Core\Auth;
$canSearch = Auth::can(['admin', 'editor']);
?>
<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item"><a href="/atividades">Atividades</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($activity['description'] ?? $activity['code'] ?? 'Atividade') ?></li>
    </ol>
</nav>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-3 gap-2 fade-in">
    <div>
        <h1 class="h4 mb-1">
            <i class="bi bi-briefcase me-2 text-muted"></i>
            <?= e($activity['description'] ?? "Atividade $code") ?>
        </h1>
        <div class="text-muted small">
            <span class="badge bg-secondary"><?= e($code) ?></span>
            <?php if (!empty($activity['slug'])): ?>
                <span class="ms-2"><?= count($companies) ?> empresa(s) encontrada(s)</span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($canSearch): ?>
        <a href="/empresas/busca" class="btn btn-brand">
            <i class="bi bi-search me-1"></i>Consultar CNPJ
        </a>
    <?php endif; ?>
</div>

<div class="card fade-in stagger-1">
    <div class="card-body">
        <?php if (empty($companies)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-building fs-1 d-block mb-3"></i>
                <p class="mb-0">Nenhuma empresa encontrada para esta atividade.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="text-nowrap">
                        <tr>
                            <th class="d-none d-md-table-cell">CNPJ</th>
                            <th class="d-table-cell d-md-none">Dados</th>
                            <th>Razao social</th>
                            <th class="d-none d-lg-table-cell">Nome fantasia</th>
                            <th class="text-nowrap d-none d-sm-table-cell">Cidade/UF</th>
                            <th class="d-none d-md-table-cell">Situacao</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $company): ?>
                            <tr>
                                <td class="d-none d-md-table-cell text-nowrap"><?= e($company['cnpj']) ?></td>
                                <td class="d-table-cell d-md-none py-3">
                                    <div class="fw-bold text-body" style="font-size: 1rem; line-height: 1.2;"><?= e($company['legal_name']) ?></div>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <small class="text-muted"><i class="bi bi-hash me-1"></i><?= e($company['cnpj']) ?></small>
                                        <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e(($company['city'] ?: '-') . '/' . ($company['state'] ?: '-')) ?></small>
                                    </div>
                                    <?php if (!empty($company['status'])): ?>
                                        <span class="badge bg-<?= $company['status'] === 'ativa' ? 'success' : 'secondary' ?> mt-2" style="font-size: 0.7rem;">
                                            <?= e($company['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell"><?= e($company['legal_name']) ?></td>
                                <td class="d-none d-lg-table-cell"><?= e($company['trade_name'] ?: '-') ?></td>
                                <td class="text-nowrap d-none d-sm-table-cell"><?= e(($company['city'] ?: '-') . '/' . ($company['state'] ?: '-')) ?></td>
                                <td class="d-none d-md-table-cell">
                                    <?php if (!empty($company['status'])): ?>
                                        <span class="badge bg-<?= $company['status'] === 'ativa' ? 'success' : 'secondary' ?>">
                                            <?= e($company['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="/empresas/<?= e($company['cnpj']) ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i><span class="d-none d-md-inline">Ver</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
