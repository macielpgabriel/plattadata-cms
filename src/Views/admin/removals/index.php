<?php declare(strict_types=1);
use App\Core\Csrf;
$flash = \App\Core\Session::flash('success');
$error = \App\Core\Session::flash('error');

$stats = [
    'total' => count($requests),
    'pending' => count(array_filter($requests, fn($r) => $r['status'] === 'pending')),
    'verified' => count(array_filter($requests, fn($r) => $r['status'] === 'verified')),
    'approved' => count(array_filter($requests, fn($r) => $r['status'] === 'approved')),
    'cancelled' => count(array_filter($requests, fn($r) => $r['status'] === 'cancelled')),
];
?>

<div class="section-header fade-in mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1"><i class="bi bi-shield-x me-2"></i>Solicitações de Remoção (LGPD)</h4>
            <p class="mb-0 opacity-75 small">Gerencie pedidos de exclusão de dados e conformidade com a LGPD</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-light shadow-sm" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
            </button>
        </div>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= e($flash) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4 fade-in">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="text-brand mb-2"><i class="bi bi-list-ul fs-2"></i></div>
                <div class="mb-0 fw-bold fs-3"><?= $stats['total'] ?></div>
                <small class="text-muted">Total</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="text-warning mb-2"><i class="bi bi-clock fs-2"></i></div>
                <div class="mb-0 fw-bold fs-3"><?= $stats['pending'] ?></div>
                <small class="text-muted">Pendentes</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="text-info mb-2"><i class="bi bi-check-circle fs-2"></i></div>
                <div class="mb-0 fw-bold fs-3"><?= $stats['verified'] ?></div>
                <small class="text-muted">Verificados</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="text-success mb-2"><i class="bi bi-check-all fs-2"></i></div>
                <div class="mb-0 fw-bold fs-3"><?= $stats['approved'] ?></div>
                <small class="text-muted">Aprovados</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="text-secondary mb-2"><i class="bi bi-arrow-counterclockwise fs-2"></i></div>
                <div class="mb-0 fw-bold fs-3"><?= $stats['cancelled'] ?></div>
                <small class="text-muted">Cancelados</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm fade-in stagger-1">
    <div class="card-header bg-white border-0 py-3">
        <div class="row align-items-center g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" id="searchInput" placeholder="Buscar empresa ou solicitante...">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="statusFilter">
                    <option value="">Todos status</option>
                    <option value="pending">Pendente</option>
                    <option value="verified">Verificado</option>
                    <option value="approved">Aprovado</option>
                    <option value="rejected">Rejeitado</option>
                    <option value="cancelled">Cancelado</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="typeFilter">
                    <option value="">Todos tipos</option>
                    <option value="email">E-mail</option>
                    <option value="document">Documento</option>
                </select>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($requests)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox text-muted display-4 mb-3 d-block"></i>
                <h5 class="text-muted">Nenhuma solicitacao encontrada</h5>
                <p class="text-muted small">Quando houver pedidos de remocao, aparecerao aqui</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="border-0 py-3 ps-4">Empresa</th>
                            <th class="border-0 py-3">Solicitante</th>
                            <th class="border-0 py-3">Verificacao</th>
                            <th class="border-0 py-3">Data</th>
                            <th class="border-0 py-3">Status</th>
                            <th class="border-0 py-3 text-end pe-4">Acoes</th>
                        </tr>
                    </thead>
                    <tbody id="requestsTableBody">
                        <?php foreach ($requests as $request): ?>
                            <tr class="request-row" 
                                data-company="<?= e(strtolower($request['legal_name'] . ' ' . $request['cnpj'])) ?>" 
                                data-requester="<?= e(strtolower($request['requester_name'] . ' ' . $request['requester_email'])) ?>"
                                data-status="<?= e($request['status']) ?>"
                                data-type="<?= e($request['verification_type']) ?>">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="bi bi-building"></i>
                                        </div>
                                        <div>
                                            <a href="/empresas/<?= e($request['cnpj']) ?>" target="_blank" class="text-decoration-none fw-medium hover-primary">
                                                <?= e($request['legal_name']) ?>
                                                <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.7rem;"></i>
                                            </a>
                                            <div class="small text-muted"><?= e($request['cnpj']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-medium"><?= e($request['requester_name']) ?></div>
                                    <small class="text-muted"><?= e($request['requester_email']) ?></small>
                                </td>
                                <td>
                                    <?php if ($request['verification_type'] === 'email'): ?>
                                        <span class="badge bg-info-subtle text-info">
                                            <i class="bi bi-envelope me-1"></i>E-mail
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning">
                                            <i class="bi bi-file-earmark-text me-1"></i>Documento
                                        </span>
                                        <?php if (!empty($request['document_path'])): ?>
                                            <a href="/admin/remocoes/documento/<?= e($request['document_path']) ?>" target="_blank" class="btn btn-xs btn-outline-primary py-0 px-1 ms-1" style="font-size: 0.7rem;">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger ms-1" style="font-size: 0.65rem;">Sem anexo</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?= date('d/m/Y H:i', strtotime($request['created_at'])) ?>
                                </td>
                                <td>
                                    <?php
                                    $statusConfig = match($request['status']) {
                                        'approved' => ['class' => 'bg-success-subtle text-success', 'icon' => 'check-circle', 'label' => 'Aprovado'],
                                        'rejected' => ['class' => 'bg-danger-subtle text-danger', 'icon' => 'x-circle', 'label' => 'Rejeitado'],
                                        'cancelled' => ['class' => 'bg-secondary-subtle text-secondary', 'icon' => 'arrow-counterclockwise', 'label' => 'Cancelado'],
                                        'verified' => ['class' => 'bg-info-subtle text-info', 'icon' => 'check-all', 'label' => 'Verificado'],
                                        default => ['class' => 'bg-warning-subtle text-warning', 'icon' => 'clock', 'label' => 'Pendente'],
                                    };
                                    ?>
                                    <span class="badge <?= $statusConfig['class'] ?>">
                                        <i class="bi bi-<?= $statusConfig['icon'] ?> me-1"></i><?= $statusConfig['label'] ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if (in_array($request['status'], ['pending', 'verified'])): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <li>
                                                    <button class="dropdown-item text-success" type="button" data-bs-toggle="modal" data-bs-target="#approveModal" data-id="<?= $request['id'] ?>" data-company="<?= e($request['legal_name']) ?>">
                                                        <i class="bi bi-check-circle me-2"></i>Aprovar Remocao
                                                    </button>
                                                </li>
                                                <li>
                                                    <button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#rejectModal" data-id="<?= $request['id'] ?>" data-company="<?= e($request['legal_name']) ?>">
                                                        <i class="bi bi-x-circle me-2"></i>Rejeitar
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    <?php elseif ($request['status'] === 'approved' || $request['status'] === 'cancelled'): ?>
                                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="<?= $request['id'] ?>" data-company="<?= e($request['legal_name']) ?>">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-success">
                    <i class="bi bi-check-circle me-2"></i>Aprovar Remocao
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="approveForm">
                <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="id" id="approveId" value="">
                <div class="modal-body pt-2">
                    <p>A empresa sera removida do site:</p>
                    <p class="fw-bold mb-0" id="approveCompany"></p>
                    <p class="text-muted small mt-3 mb-0">Esta acao e irreversivel. A empresa podera ser restaurada posteriormente.</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i>Aprovar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-x-circle me-2"></i>Rejeitar Solicitacao
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="rejectForm">
                <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="id" id="rejectId" value="">
                <div class="modal-body pt-2">
                    <p>Rejeitar solicitacao de:</p>
                    <p class="fw-bold mb-0" id="rejectCompany"></p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-lg me-1"></i>Rejeitar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-warning">
                    <i class="bi bi-arrow-counterclockwise me-2"></i>Restaurar Empresa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="restoreForm">
                <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="id" id="restoreId" value="">
                <div class="modal-body pt-2">
                    <p>A empresa sera reexibida no site:</p>
                    <p class="fw-bold mb-0" id="restoreCompany"></p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const approveModal = document.getElementById('approveModal');
    const rejectModal = document.getElementById('rejectModal');
    const restoreModal = document.getElementById('restoreModal');

    approveModal.addEventListener('show.bs.modal', function(e) {
        const button = e.relatedTarget;
        document.getElementById('approveId').value = button.dataset.id;
        document.getElementById('approveCompany').textContent = button.dataset.company;
        document.getElementById('approveForm').action = '/admin/remocoes/' + button.dataset.id + '/aprovar';
    });

    rejectModal.addEventListener('show.bs.modal', function(e) {
        const button = e.relatedTarget;
        document.getElementById('rejectId').value = button.dataset.id;
        document.getElementById('rejectCompany').textContent = button.dataset.company;
        document.getElementById('rejectForm').action = '/admin/remocoes/' + button.dataset.id + '/recusar';
    });

    restoreModal.addEventListener('show.bs.modal', function(e) {
        const button = e.relatedTarget;
        document.getElementById('restoreId').value = button.dataset.id;
        document.getElementById('restoreCompany').textContent = button.dataset.company;
        document.getElementById('restoreForm').action = '/admin/remocoes/' + button.dataset.id + '/restaurar';
    });

    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const typeFilter = document.getElementById('typeFilter');

    function filterRequests() {
        const search = searchInput.value.toLowerCase();
        const status = statusFilter.value;
        const type = typeFilter.value;

        document.querySelectorAll('.request-row').forEach(row => {
            const company = row.dataset.company;
            const requester = row.dataset.requester;
            const rowStatus = row.dataset.status;
            const rowType = row.dataset.type;

            const matchesSearch = company.includes(search) || requester.includes(search);
            const matchesStatus = !status || rowStatus === status;
            const matchesType = !type || rowType === type;

            row.style.display = matchesSearch && matchesStatus && matchesType ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterRequests);
    statusFilter.addEventListener('change', filterRequests);
    typeFilter.addEventListener('change', filterRequests);
});
</script>
