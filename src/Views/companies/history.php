<?php declare(strict_types=1); use App\Core\Auth; ?>
<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item"><a href="/empresas">Empresas</a></li>
        <li class="breadcrumb-item"><a href="/empresas/<?= e($cnpj) ?>"><?= e($company['legal_name'] ?? $cnpj) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">Historico</li>
    </ol>
</nav>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
    <div>
        <h1 class="h4 mb-1">
            <i class="bi bi-clock-history me-2 text-muted"></i>
            Historico da Empresa
        </h1>
        <div class="text-muted small">
            <?= e($company['legal_name'] ?? '-') ?> - CNPJ <?= e($cnpj) ?>
        </div>
    </div>
    <div>
        <a href="/empresas/<?= e($cnpj) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0">
                    <i class="bi bi-database me-2 text-muted"></i>
                    Snapshots da API
                    <span class="badge bg-secondary ms-2"><?= count($snapshots) ?></span>
                </h2>
            </div>
            <div class="card-body">
                <?php if (empty($snapshots)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                        <p class="mb-0">Nenhum snapshot registrado.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($snapshots as $index => $snapshot): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            <?= e(format_datetime($snapshot['created_at'])) ?>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            <span class="badge bg-secondary-subtle text-muted border me-1"><?= e($snapshot['source']) ?></span>
                                            <span class="small">ID: <?= e((string) $snapshot['id']) ?></span>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            type="button" 
                                            data-bs-toggle="collapse" 
                                            data-bs-target="#snapshot-<?= $index ?>"
                                            aria-expanded="false">
                                        <i class="bi bi-code-slash"></i>
                                    </button>
                                </div>
                                <div class="collapse mt-3" id="snapshot-<?= $index ?>">
                                    <div class="bg-dark text-white rounded p-3 small overflow-auto" style="max-height: 400px;">
                                        <pre class="mb-0" style="white-space: pre-wrap; word-break: break-all;"><?php
                                            $data = json_decode((string) $snapshot['raw_data'], true);
                                            echo e(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                        ?></pre>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0">
                    <i class="bi bi-search me-2 text-muted"></i>
                    Historico de Consultas
                    <span class="badge bg-secondary ms-2"><?= count($queryHistory) ?></span>
                </h2>
            </div>
            <div class="card-body">
                <?php if (empty($queryHistory)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                        <p class="mb-0">Nenhuma consulta registrada.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Usuario</th>
                                    <th>Origem</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($queryHistory as $query): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= e(format_datetime($query['created_at'])) ?></td>
                                        <td><?= e($query['user_name'] ?? '-') ?></td>
                                        <td><span class="badge bg-secondary-subtle text-muted"><?= e($query['source'] ?? '-') ?></span></td>
                                        <td class="text-muted small"><?= e($query['ip_address'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">
                <i class="bi bi-info-circle me-2 text-muted"></i>
                Informacoes da Empresa
            </h2>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-md-3">Razao Social</dt>
                <dd class="col-md-9"><?= e($company['legal_name'] ?? '-') ?></dd>
                
                <dt class="col-md-3">Nome Fantasia</dt>
                <dd class="col-md-9"><?= e($company['trade_name'] ?? '-') ?></dd>
                
                <dt class="col-md-3">CNPJ</dt>
                <dd class="col-md-9"><?= e($cnpj) ?></dd>
                
                <dt class="col-md-3">Situacao</dt>
                <dd class="col-md-9">
                    <?php if (!empty($company['status'])): ?>
                        <span class="badge bg-<?= $company['status'] === 'ativa' ? 'success' : 'secondary' ?>">
                            <?= e($company['status']) ?>
                        </span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </dd>
                
                <dt class="col-md-3">Cidade/UF</dt>
                <dd class="col-md-9"><?= e(($company['city'] ?? '-') . '/' . ($company['state'] ?? '-')) ?></dd>
                
                <dt class="col-md-3">Ultima Sincronizacao</dt>
                <dd class="col-md-9"><?= e($company['last_synced_at'] ? format_datetime($company['last_synced_at']) : '-') ?></dd>
                
                <dt class="col-md-3">Fornecedor</dt>
                <dd class="col-md-9"><?= e($company['source_provider'] ?? '-') ?></dd>
                
                <dt class="col-md-3">Total de Views</dt>
                <dd class="col-md-9"><?= number_format((int) ($company['views'] ?? 0), 0, ',', '.') ?></dd>
            </dl>
        </div>
    </div>
</div>
