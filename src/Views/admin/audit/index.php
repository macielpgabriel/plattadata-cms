<?php declare(strict_types=1);
use App\Core\Auth;

$user = Auth::user();
$canExport = in_array($user['role'] ?? '', ['admin', 'moderator'], true);

$actionStats = $stats['by_action'] ?? [];
$creates = $actionStats['create'] ?? 0;
$updates = $actionStats['update'] ?? 0;
$deletes = $actionStats['delete'] ?? 0;
$accesses = $actionStats['access'] ?? 0;
?>

<div class="section-header fade-in mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1"><i class="bi bi-shield-check me-2"></i>Auditoria</h4>
            <p class="mb-0 opacity-75 small">Log de operações e alterações no sistema</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($canExport): ?>
                <a href="/admin/auditoria/exportar<?= http_build_query(array_filter(['action' => $action, 'entity' => $entityType, 'user' => $userId, 'start' => $startDate, 'end' => $endDate, 'quick' => $quickFilter, 'q' => $search])) ?>" class="btn btn-sm btn-outline-primary shadow-sm">
                    <i class="bi bi-download me-1"></i> Exportar CSV
                </a>
            <?php endif; ?>
            <button class="btn btn-sm btn-light shadow-sm" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
            </button>
        </div>
    </div>
</div>

<div class="row g-3 mb-4 fade-in">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="text-brand mb-2"><i class="bi bi-list-task fs-2"></i></div>
                <div class="mb-0 fw-bold fs-3"><?= number_format($total, 0, ',', '.') ?></div>
                <small class="text-muted">Total de Registros</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="text-success mb-2"><i class="bi bi-plus-circle fs-2"></i></div>
                <div class="mb-0 fw-bold fs-3"><?= number_format($creates, 0, ',', '.') ?></div>
                <small class="text-muted">Criações</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="text-primary mb-2"><i class="bi bi-pencil fs-2"></i></div>
                <div class="mb-0 fw-bold fs-3"><?= number_format($updates, 0, ',', '.') ?></div>
                <small class="text-muted">Atualizações</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="text-warning mb-2"><i class="bi bi-eye fs-2"></i></div>
                <div class="mb-0 fw-bold fs-3"><?= number_format($accesses, 0, ',', '.') ?></div>
                <small class="text-muted">Acessos</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm fade-in">
    <div class="card-header bg-transparent py-3">
        <form method="get" action="/admin/auditoria" class="row g-2 g-md-3 align-items-end">
            <div class="col-12 col-md-auto">
                <label class="form-label small text-muted mb-1">Buscar</label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?= e($search) ?>" placeholder="Pesquisar em valores...">
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small text-muted mb-1">Filtro rápido</label>
                <select name="quick" class="form-select form-select-sm">
                    <option value="">Todos os períodos</option>
                    <?php foreach ($quickFilters as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $quickFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small text-muted mb-1">Ação</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">Todas as ações</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small text-muted mb-1">Entidade</label>
                <select name="entity" class="form-select form-select-sm">
                    <option value="">Todas as entidades</option>
                    <?php foreach ($entityTypes as $e): ?>
                        <option value="<?= e($e) ?>" <?= $entityType === $e ? 'selected' : '' ?>><?= e($e) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small text-muted mb-1">Usuário</label>
                <select name="user" class="form-select form-select-sm">
                    <option value="">Todos os usuários</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $userId == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small text-muted mb-1">De</label>
                <input type="date" name="start" class="form-control form-control-sm" value="<?= e($startDate) ?>">
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small text-muted mb-1">Até</label>
                <input type="date" name="end" class="form-control form-control-sm" value="<?= e($endDate) ?>">
            </div>
            <div class="col-12 col-md-auto">
                <label class="d-none d-md-block">&nbsp;</label>
                <div class="d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel me-1"></i> Filtrar
                    </button>
                    <a href="/admin/auditoria" class="btn btn-sm btn-outline-secondary">
                        Limpar
                    </a>
                </div>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-3">ID</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Entidade</th>
                        <th>Entidade ID</th>
                        <th>Data</th>
                        <th>IP</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                Nenhum registro encontrado
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="px-3"><small class="text-muted"><?= $log['id'] ?></small></td>
                                <td>
                                    <?php if (!empty($log['user_name'])): ?>
                                        <span class="fw-medium"><?= e($log['user_name']) ?></span>
                                        <small class="text-muted d-block" style="font-size: 0.7rem;"><?= e($log['user_email'] ?? '') ?></small>
                                    <?php else: ?>
                                        <span class="text-muted"><?= $log['user_id'] ?: '-' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $actionColors = [
                                        'create' => 'success',
                                        'update' => 'primary',
                                        'delete' => 'danger',
                                        'access' => 'info',
                                        'login_success' => 'success',
                                        'login_failed' => 'danger',
                                    ];
                                    $color = $actionColors[$log['action']] ?? 'secondary';
                                    ?>
                                    <span class="badge text-bg-<?= $color ?>"><?= e($log['action']) ?></span>
                                </td>
                                <td><?= e($log['entity_type']) ?></td>
                                <td><?= $log['entity_id'] ?: '<span class="text-muted">-</span>' ?></td>
                                <td><small><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></small></td>
                                <td><small class="text-muted"><?= e($log['ip_address'] ?? '-') ?></small></td>
                                <td>
                                    <button class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size: 0.7rem;" data-bs-toggle="modal" data-bs-target="#modal-<?= $log['id'] ?>">
                                        Detalhes
                                    </button>
                                </td>
                            </tr>
                            <div class="modal fade" id="modal-<?= $log['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Detalhes do Log #<?= $log['id'] ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <h6>Usuário</h6>
                                                    <p class="mb-0"><?= e($log['user_name'] ?? ($log['user_id'] ?: 'Sistema')) ?></p>
                                                    <?php if (!empty($log['user_email'])): ?>
                                                        <small class="text-muted"><?= e($log['user_email']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>IP</h6>
                                                    <p class="mb-0"><?= e($log['ip_address'] ?? '-') ?></p>
                                                    <small class="text-muted"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></small>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6>Valores Anteriores</h6>
                                                    <pre class="bg-light p-2 rounded small"><?= $log['old_values'] ? e(json_encode(json_decode($log['old_values'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : '<span class="text-muted">N/A</span>' ?></pre>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>Valores Novos</h6>
                                                    <pre class="bg-light p-2 rounded small"><?= $log['new_values'] ? e(json_encode(json_decode($log['new_values'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : '<span class="text-muted">N/A</span>' ?></pre>
                                                </div>
                                            </div>
                                            <?php if ($log['changes']): ?>
                                                <div class="mt-3">
                                                    <h6>Alterações</h6>
                                                    <pre class="bg-light p-2 rounded small"><?= e(json_encode(json_decode($log['changes'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-transparent py-2">
            <nav>
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    <?php $baseUrl = '/admin/auditoria' . ($queryParams ? '?' . http_build_query($queryParams) . '&' : '?'); ?>
                    <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= $baseUrl ?>page=<?= $page - 1 ?>">Anterior</a></li>
                    <?php endif; ?>
                    <li class="page-item disabled"><span class="page-link"><?= $page ?> / <?= $totalPages ?></span></li>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item"><a class="page-link" href="<?= $baseUrl ?>page=<?= $page + 1 ?>">Próxima</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>