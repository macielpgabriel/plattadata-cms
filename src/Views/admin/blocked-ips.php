<?php

$flash = Session::flash('success');
$error = Session::flash('error');

?>
<?php include __DIR__ . '/layouts/app.php'; ?>

<?php startblock('content'); ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">IPs Bloqueados</h1>
        <form method="POST" action="/admin/bloqueados/desbloquear-todos">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger" onclick="return confirm('Desbloquear todos os IPs?')">
                Desbloquear Todos
            </button>
        </form>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-success"><?= e($flash) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">IPs Bloqueados Atualmente</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($blockedIPs)): ?>
                        <p class="text-muted mb-0">Nenhum IP bloqueado.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>IP</th>
                                        <th>Motivo</th>
                                        <th>Bloqueado em</th>
                                        <th>Expira em</th>
                                        <th>Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($blockedIPs as $ip): ?>
                                        <tr>
                                            <td><code><?= e($ip['ip']) ?></code></td>
                                            <td><?= e($ip['reason']) ?></td>
                                            <td><?= format_datetime($ip['created_at'] ?? '', true) ?></td>
                                            <td><?= $ip['expires_at'] ? format_datetime($ip['expires_at'], true) : 'Permanente' ?></td>
                                            <td>
                                                <form method="POST" action="/admin/bloqueados/desbloquear" style="display:inline;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="ip" value="<?= e($ip['ip']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Desbloquear</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Tentativas de Login Falhas Recentes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($failedAttempts)): ?>
                        <p class="text-muted mb-0">Nenhuma tentativa falha registrada.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>IP</th>
                                        <th>Tentativas</th>
                                        <th>Última tentativa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($failedAttempts as $attempt): ?>
                                        <tr>
                                            <td><code><?= e($attempt['ip']) ?></code></td>
                                            <td><?= (int) $attempt['attempts'] ?></td>
                                            <td><?= format_datetime($attempt['last_attempt'] ?? '', true) ?></td>
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
</div>
<?php endblock(); ?>