<?php declare(strict_types=1);
use App\Core\Auth;
use App\Core\Csrf;

$token = Csrf::token();
$currentGroupId = $currentGroupId ?? null;
$groups = $groups ?? [];
?>
<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item"><a href="/dashboard">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Favoritos</li>
    </ol>
</nav>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-3 gap-2 fade-in">
    <h1 class="h3 mb-0">
        <i class="bi bi-star-fill me-2 text-warning"></i>Minhas Empresas Favoritas
    </h1>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newGroupModal">
            <i class="bi bi-folder-plus me-1"></i>Novo Grupo
        </button>
        <?php if (!empty($items)): ?>
            <a href="/favoritos/exportar<?= $currentGroupId ? '?group=' . $currentGroupId : '' ?>" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($flash)): ?>
    <div class="alert alert-success alert-permanent fade-in"><?= e($flash) ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-permanent fade-in"><?= e($error) ?></div>
<?php endif; ?>

<div class="row g-3 mb-3 fade-in">
    <div class="col-12">
        <div class="d-flex flex-wrap gap-2 align-items-center" x-data="favoriteGroups()">
            <a href="/favoritos" class="btn btn-sm <?= !$currentGroupId ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <i class="bi bi-star me-1"></i>Todos (<?= $total ?>)
            </a>
            <?php foreach ($groups as $group): ?>
                <div class="d-flex align-items-center gap-1">
                    <a href="/favoritos?group=<?= $group['id'] ?>" 
                       class="btn btn-sm <?= $currentGroupId == $group['id'] ? 'btn-primary' : 'btn-outline-secondary' ?>"
                       style="border-color: var(--bs-<?= e($group['color']) ?>)">
                        <i class="bi bi-folder-fill me-1"></i><?= e($group['name']) ?> (<?= $group['company_count'] ?>)
                    </a>
                    <button type="button" class="btn btn-sm btn-link text-danger p-0" 
                            onclick="deleteGroup(<?= $group['id'] ?>, '<?= e($group['name']) ?>')"
                            title="Excluir grupo">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card fade-in">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">
                <i class="bi bi-list me-1 text-muted"></i>Lista de Favoritos
            </h2>
            <span class="badge bg-secondary"><?= e((string) $total) ?> registro(s)</span>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead class="text-nowrap">
                <tr>
                    <th class="d-none d-md-table-cell">CNPJ</th>
                    <th>Razao social</th>
                    <th class="d-none d-lg-table-cell">Nome fantasia</th>
                    <th class="text-nowrap d-none d-sm-table-cell">Cidade/UF</th>
                    <th class="d-none d-md-table-cell">Situacao</th>
                    <th class="text-end">Acoes</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="6" class="text-muted text-center py-5">
                            <i class="bi bi-star fs-1 d-block mb-3 text-light"></i>
                            <p class="mb-0">Voce ainda nao favoritou nenhuma empresa.</p>
                            <small>Clique na estrela ao visualizar os detalhes de uma empresa para salva-la aqui.</small>
                            <div class="mt-4">
                                <a href="/empresas" class="btn btn-brand btn-sm">Explorar empresas</a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="d-none d-md-table-cell text-nowrap"><?= cnpj_with_copy($item['cnpj']) ?></td>
                            <td>
                                <strong><?= e($item['legal_name']) ?></strong>
                                <div class="d-md-none small text-muted"><?= cnpj_with_copy($item['cnpj']) ?></div>
                            </td>
                            <td class="d-none d-lg-table-cell"><?= e($item['trade_name'] ?: '-') ?></td>
                            <td class="text-nowrap d-none d-sm-table-cell"><?= e(($item['city'] ?: '-') . '/' . ($item['state'] ?: '-')) ?></td>
                            <td class="d-none d-md-table-cell">
                                <?php if (!empty($item['status'])): ?>
                                    <span class="badge bg-<?= $item['status'] === 'ativa' ? 'success' : 'secondary' ?>">
                                        <?= e($item['status']) ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1" x-data="{ removed: false, showMove: false }">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="showMove = !showMove" title="Mover para grupo">
                                        <i class="bi bi-folder"></i>
                                    </button>
                                    <a class="btn btn-sm btn-outline-primary" href="/empresas/<?= e($item['cnpj']) ?>" x-show="!removed">
                                        <i class="bi bi-eye"></i><span class="d-none d-sm-inline ms-1">Ver</span>
                                    </a>
                                    
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            x-show="!removed"
                                            @click="
                                                confirmAction({
                                                    message: 'Remover esta empresa dos favoritos?',
                                                    title: 'Remover favorito',
                                                    icon: 'bi-star-fill',
                                                    btnClass: 'btn-outline-danger'
                                                }).then(function(confirmed) {
                                                    if (confirmed) {
                                                        removed = true;
                                                        fetch('/favoritos/<?= e($item['cnpj']) ?>/toggle', {
                                                            method: 'POST',
                                                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                                            body: '_token=<?= e($token) ?>'
                                                        });
                                                    }
                                                });
                                            ">
                                        <i class="bi bi-star-fill"></i>
                                    </button>
                                    
                                    <div x-show="showMove" class="position-relative">
                                        <div class="dropdown-menu show p-2" style="min-width: 180px;">
                                            <div class="dropdown-header small fw-bold">Mover para:</div>
                                            <?php foreach ($groups as $group): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary w-100 mb-1 text-start"
                                                        onclick="moveToGroup('<?= e($item['cnpj']) ?>', <?= $group['id'] ?>)">
                                                    <i class="bi bi-folder-fill me-1 text-<?= e($group['color']) ?>"></i><?= e($group['name']) ?>
                                                </button>
                                            <?php endforeach; ?>
                                            <hr class="my-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary w-100 text-start" onclick="moveToGroup('<?= e($item['cnpj']) ?>', null)">
                                                <i class="bi bi-star me-1"></i>Sem grupo
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($lastPage > 1): ?>
            <nav class="mt-3">
                <ul class="pagination justify-content-center mb-0">
                    <?php for ($i = 1; $i <= $lastPage; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?><?= $currentGroupId ? '&group=' . $currentGroupId : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="newGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-folder-plus me-2"></i>Novo Grupo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/favoritos/grupos/criar" x-data="{ name: '', color: 'primary' }">
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= e($token) ?>">
                    <div class="mb-3">
                        <label class="form-label">Nome do grupo</label>
                        <input type="text" name="name" class="form-control" x-model="name" required maxlength="100" placeholder="Ex: Potenciais clientes">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cor</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php foreach (['primary', 'success', 'danger', 'warning', 'info', 'secondary'] as $color): ?>
                                <label class="form-check">
                                    <input type="radio" name="color" value="<?= $color ?>" <?= $color === 'primary' ? 'checked' : '' ?> class="form-check-input">
                                    <span class="badge bg-<?= $color ?>"><?= ucfirst($color) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" :disabled="!name.trim()">
                        <i class="bi bi-check me-1"></i>Criar grupo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function moveToGroup(cnpj, groupId) {
    const formData = new FormData();
    formData.append('_token', '<?= e($token) ?>');
    formData.append('group_id', groupId || '');
    
    fetch('/favoritos/' + cnpj + '/mover', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        } else {
            alert(data.error || 'Erro ao mover');
        }
    });
}

function deleteGroup(groupId, groupName) {
    if (!confirm('Excluir o grupo "' + groupName + '"?\n\nAs empresas não serão removidas, apenas perderão a associação.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('_token', '<?= e($token) ?>');
    
    fetch('/favoritos/grupos/' + groupId + '/excluir', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        } else {
            alert(data.error || 'Erro ao excluir');
        }
    });
}
</script>
