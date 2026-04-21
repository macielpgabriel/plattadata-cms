<?php declare(strict_types=1);

use App\Core\Session;
use App\Core\Csrf;

$flash = Session::flash('success');
$error = Session::flash('error');

$schedules = $schedules ?? [];
$status = $status ?? [];
?>

<?php if (!empty($flash)): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= e($flash) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Tarefas Agendadas (Cron)</h1>
    <form method="POST" action="/admin/cron/run">
        <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-play-fill me-1"></i> Executar Agora
        </button>
    </form>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Status das Tarefas</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Tarefa</th>
                        <th>Intervalo</th>
                        <th>Última Execução</th>
                        <th>Próxima Execução</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status as $hook => $data): ?>
                        <tr>
                            <td><code><?= e($hook) ?></code></td>
                            <td><?= e($data['interval_human']) ?></td>
                            <td><?= e($data['last_run']) ?></td>
                            <td><?= e($data['next_run']) ?></td>
                            <td>
                                <?php if ($data['due']): ?>
                                    <span class="badge bg-warning">Pendente</span>
                                <?php else: ?>
                                    <span class="badge bg-success">OK</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Configuração do Cron</h5>
    </div>
    <div class="card-body">
        <p>Para executar automaticamente, adicione ao crontab do servidor:</p>
        <div class="bg-dark text-light p-3 rounded">
            <code>*/15 * * * * curl -s <?= e(config('app.url', 'https://plattadata.com')) ?>/api/v1/cron > /dev/null 2>&1</code>
        </div>
        <p class="mt-3 text-muted small">
            Execute a cada 15 minutos para verificar tarefas pendentes.
        </p>
    </div>
</div>