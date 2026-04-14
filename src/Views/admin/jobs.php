<div class="section-header fade-in mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1"><i class="bi bi-list-task me-2"></i>Fila de Processamento</h4>
            <p class="mb-0 opacity-75 small">Gerencie as tarefas agendadas e monitore a execução em segundo plano</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-light shadow-sm" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
            </button>
            <a href="/admin/observabilidade" class="btn btn-sm btn-outline-light">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </div>
</div>

<?php if (App\Core\Session::has('success')): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= e(App\Core\Session::flash('success')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (App\Core\Session::has('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e(App\Core\Session::flash('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm fade-in">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="bi bi-filter me-2 text-muted"></i>Filtrar por Status
            </h5>
            <div class="d-flex gap-2">
                <a href="/admin/jobs" class="btn btn-sm <?= $status === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">Todos</a>
                <a href="/admin/jobs?status=pending" class="btn btn-sm <?= $status === 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>">Pendentes</a>
                <a href="/admin/jobs?status=processing" class="btn btn-sm <?= $status === 'processing' ? 'btn-info' : 'btn-outline-info' ?>">Processando</a>
                <a href="/admin/jobs?status=completed" class="btn btn-sm <?= $status === 'completed' ? 'btn-success' : 'btn-outline-success' ?>">Concluídos</a>
                <a href="/admin/jobs?status=failed" class="btn btn-sm <?= $status === 'failed' ? 'btn-danger' : 'btn-outline-danger' ?>">Falhos</a>
            </div>
        </div>
        
        <?php if (empty($jobs)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3">Nenhum job encontrado com os filtros atuais.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Handler</th>
                            <th>Status</th>
                            <th>Tentativas</th>
                            <th>Criado em</th>
                            <th>Atualizado em</th>
                            <th>Erro</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td><code><?= e($job['id']) ?></code></td>
                                <td><small><?= e($job['handler']) ?></small></td>
                                <td>
                                    <?php
                                    $badgeClass = match($job['status']) {
                                        'pending' => 'bg-warning-subtle text-warning',
                                        'processing' => 'bg-info-subtle text-info',
                                        'completed' => 'bg-success-subtle text-success',
                                        'failed' => 'bg-danger-subtle text-danger',
                                        default => 'bg-secondary-subtle text-secondary',
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= e(strtoupper($job['status'])) ?></span>
                                </td>
                                <td><?= e($job['attempts']) ?></td>
                                <td><small><?= e(date('d/m/Y H:i:s', strtotime($job['created_at']))) ?></small></td>
                                <td><small><?= e($job['updated_at'] ? date('d/m/Y H:i:s', strtotime($job['updated_at'])) : '-') ?></small></td>
                                <td>
                                    <?php if (!empty($job['error_message'])): ?>
                                        <small class="text-danger" title="<?= e($job['error_message']) ?>">
                                            <?= e(mb_strimwidth($job['error_message'], 0, 50, '...')) ?>
                                        </small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <?php if ($job['status'] === 'failed'): ?>
                                            <form method="post" action="/admin/jobs/retry" class="d-inline" data-confirm="Tem certeza que deseja reenviar este job para processamento?" data-confirm-title="Reenviar Job" data-confirm-icon="bi-arrow-repeat" data-confirm-btn="btn-warning">
                                                <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                                                <input type="hidden" name="id" value="<?= e($job['id']) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Reenviar">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="/admin/jobs/delete" class="d-inline" data-confirm="Tem certeza que deseja remover este job permanentemente?" data-confirm-title="Remover Job" data-confirm-icon="bi-trash" data-confirm-btn="btn-danger">
                                            <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                                            <input type="hidden" name="id" value="<?= e($job['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Remover">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="/admin/jobs?status=<?= e($status) ?>&page=<?= $page - 1 ?>">Anterior</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="/admin/jobs?status=<?= e($status) ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="/admin/jobs?status=<?= e($status) ?>&page=<?= $page + 1 ?>">Próximo</a>
                        </li>
                    </ul>
                </nav>
                <p class="text-muted small text-center mt-2">Total: <?= e($total) ?> jobs</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4 fade-in">
    <div class="card-body">
        <h5 class="mb-3"><i class="bi bi-info-circle me-2 text-muted"></i>Sobre os Jobs</h5>
        <ul class="small text-muted mb-0">
            <li><strong>Pendentes:</strong> Aguardando processamento pelo worker</li>
            <li><strong>Processando:</strong> Sendo executado atualmente</li>
            <li><strong>Concluídos:</strong> Executados com sucesso</li>
            <li><strong>Falhos:</strong> Tentativas esgotadas ou erro durante execução</li>
        </ul>
    </div>
</div>