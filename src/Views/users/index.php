<?php use App\Core\Csrf; ?>
<div class="section-header fade-in">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1"><i class="bi bi-people me-2"></i>Gestão de Usuários</h4>
            <p class="mb-0 opacity-75 small">Controle de acesso, permissões e perfis do sistema</p>
        </div>
        <button type="button" class="btn btn-light shadow-sm" data-bs-toggle="modal" data-bs-target="#userModal" data-action="create">
            <i class="bi bi-person-plus me-2"></i>Novo Usuário
        </button>
    </div>
</div>

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

<div class="row g-4 mb-4 fade-in">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="text-brand mb-2"><i class="bi bi-people fs-2"></i></div>
                <div class="mb-0 fw-bold fs-3"><?= count($users) ?></div>
                <small class="text-muted">Total</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="text-success mb-2"><i class="bi bi-person-check fs-2"></i></div>
                <div class="mb-0 fw-bold fs-3"><?= count(array_filter($users, fn($u) => (int)($u['is_active'] ?? 0) === 1)) ?></div>
                <small class="text-muted">Ativos</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="text-danger mb-2"><i class="bi bi-person-dash fs-2"></i></div>
                <div class="mb-0 fw-bold fs-3"><?= count(array_filter($users, fn($u) => (int)($u['is_active'] ?? 0) === 0)) ?></div>
                <small class="text-muted">Inativos</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="text-warning mb-2"><i class="bi bi-shield-check fs-2"></i></div>
                <div class="mb-0 fw-bold fs-3"><?= count(array_filter($users, fn($u) => ($u['role'] ?? '') === 'admin')) ?></div>
                <small class="text-muted">Admins</small>
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
                    <input type="text" class="form-control border-start-0" id="userSearch" placeholder="Buscar usuario...">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="roleFilter">
                    <option value="">Todos os perfis</option>
                    <?php foreach ($roles as $role => $meta): ?>
                        <option value="<?= e($role) ?>"><?= e($meta['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="statusFilter">
                    <option value="">Todos status</option>
                    <option value="1">Ativos</option>
                    <option value="0">Inativos</option>
                </select>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($users)): ?>
            <div class="text-center py-5">
                <i class="bi bi-people text-muted display-4 mb-3 d-block"></i>
                <h5 class="text-muted">Nenhum usuario encontrado</h5>
                <p class="text-muted small">Clique em "Novo Usuario" para adicionar</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="border-0 py-3 ps-4">Usuario</th>
                            <th class="border-0 py-3">Perfil</th>
                            <th class="border-0 py-3">Status</th>
                            <th class="border-0 py-3">2FA</th>
                            <th class="border-0 py-3">Criado em</th>
                            <th class="border-0 py-3 text-end pe-4">Acoes</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php foreach ($users as $u): ?>
                            <tr class="user-row" data-name="<?= e(strtolower($u['name'])) ?>" data-email="<?= e(strtolower($u['email'])) ?>" data-role="<?= e($u['role']) ?>" data-status="<?= (int)($u['is_active'] ?? 0) ?>">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-brand bg-opacity-10 text-brand rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <span class="fw-bold"><?= strtoupper(substr($u['name'], 0, 1)) ?></span>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?= e($u['name']) ?></div>
                                            <small class="text-muted"><?= e($u['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge text-bg-<?= $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'editor' ? 'primary' : 'secondary') ?>">
                                        <?= e($roles[$u['role']]['label'] ?? $u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ((int)($u['is_active'] ?? 0) === 1): ?>
                                        <span class="badge bg-success-subtle text-success">
                                            <i class="bi bi-check-circle me-1"></i>Ativo
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">
                                            <i class="bi bi-x-circle me-1"></i>Inativo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)($u['two_factor_enabled'] ?? 0) === 1): ?>
                                        <i class="bi bi-shield-check text-success fs-5" title="2FA Ativado"></i>
                                    <?php else: ?>
                                        <i class="bi bi-shield text-muted fs-5" title="2FA Desativado"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?= date('d/m/Y', strtotime($u['created_at'] ?? 'now')) ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#userModal" data-action="edit" data-user='<?= htmlspecialchars(json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>'>
                                                    <i class="bi bi-pencil me-2 text-muted"></i>Editar
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#passwordModal" data-user-id="<?= $u['id'] ?>" data-user-name="<?= e($u['name']) ?>" data-user='<?= htmlspecialchars(json_encode(['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role'], 'is_active' => $u['is_active']], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>'>
                                                    <i class="bi bi-key me-2 text-muted"></i>Alterar Senha
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteModal" data-user-id="<?= $u['id'] ?>" data-user-name="<?= e($u['name']) ?>">
                                                    <i class="bi bi-trash me-2"></i>Excluir
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="userModalTitle">
                    <i class="bi bi-person-plus me-2"></i>Novo Usuario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="userForm">
                <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <input type="hidden" name="id" id="userId" value="">
                <div class="modal-body pt-2">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nome Completo</label>
                        <input type="text" name="name" id="userName" class="form-control" required placeholder="Nome do usuario">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">E-mail</label>
                        <input type="email" name="email" id="userEmail" class="form-control" required placeholder="email@exemplo.com">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">Perfil</label>
                            <select name="role" id="userRole" class="form-select">
                                <?php foreach ($roles as $role => $meta): ?>
                                    <option value="<?= e($role) ?>"><?= e($meta['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">Status</label>
                            <select id="userStatusSelect" class="form-select">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3" id="passwordField">
                        <label class="form-label small fw-bold">Senha <span class="text-muted fw-normal">(opcional)</span></label>
                        <div x-data="{ show: false }">
                            <div class="input-group">
                                <input :type="show ? 'text' : 'password'" name="password" id="userPassword" class="form-control" placeholder="Minimo 8 caracteres">
                                <button type="button" class="btn btn-outline-secondary" @click="show = !show">
                                    <i :class="show ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                </button>
                            </div>
                            <div class="form-text">Deixe vazio para gerar automaticamente</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-brand" id="userFormBtn">
                        <i class="bi bi-check-lg me-1"></i>Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">
                    <i class="bi bi-key me-2"></i>Alterar Senha
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="passwordForm">
                <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="id" id="passwordUserId" value="">
                <input type="hidden" name="name" id="passwordUserName" value="">
                <input type="hidden" name="email" id="passwordUserEmail" value="">
                <input type="hidden" name="role" id="passwordUserRole" value="">
                <input type="hidden" name="is_active" id="passwordUserStatus" value="">
                <div class="modal-body pt-2">
                    <p class="small text-muted mb-3">Alterando senha para: <strong id="passwordUserDisplay"></strong></p>
                    <div class="mb-3" x-data="{ show: false }">
                        <label class="form-label small fw-bold">Nova Senha</label>
                        <div class="input-group">
                            <input :type="show ? 'text' : 'password'" name="password" class="form-control" required placeholder="Minimo 8 caracteres" minlength="8">
                            <button type="button" class="btn btn-outline-secondary" @click="show = !show">
                                <i :class="show ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-brand">
                        <i class="bi bi-check-lg me-1"></i>Alterar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>Confirmar Exclusao
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="deleteForm">
                <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="id" id="deleteUserId" value="">
                <div class="modal-body pt-2 text-center">
                    <p class="mb-2">Deseja realmente excluir o usuario:</p>
                    <p class="fw-bold mb-0" id="deleteUserName"></p>
                </div>
                <div class="modal-footer border-0 pt-0 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Excluir
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userModal = document.getElementById('userModal');
    const userForm = document.getElementById('userForm');
    const passwordModal = document.getElementById('passwordModal');
    const passwordForm = document.getElementById('passwordForm');
    const deleteModal = document.getElementById('deleteModal');
    const deleteForm = document.getElementById('deleteForm');

    userModal.addEventListener('show.bs.modal', function(e) {
        const button = e.relatedTarget;
        const action = button.dataset.action;
        
        document.getElementById('formMethod').value = action === 'edit' ? 'POST' : 'POST';
        document.getElementById('userFormBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i>' + (action === 'edit' ? 'Atualizar' : 'Criar');
        document.getElementById('userModalTitle').innerHTML = action === 'edit' ? '<i class="bi bi-pencil me-2"></i>Editar Usuario' : '<i class="bi bi-person-plus me-2"></i>Novo Usuario';
        document.getElementById('passwordField').style.display = action === 'edit' ? 'none' : 'block';
        
        if (action === 'edit') {
            const user = JSON.parse(button.dataset.user);
            document.getElementById('userId').value = user.id;
            document.getElementById('userName').value = user.name;
            document.getElementById('userEmail').value = user.email;
            document.getElementById('userRole').value = user.role;
            document.getElementById('userStatusSelect').value = user.is_active;
            userForm.action = '/usuarios/' + user.id;
        } else {
            document.getElementById('userId').value = '';
            document.getElementById('userName').value = '';
            document.getElementById('userEmail').value = '';
            document.getElementById('userRole').value = 'viewer';
            document.getElementById('userStatusSelect').value = '1';
            userForm.action = '/usuarios';
        }
    });

    passwordModal.addEventListener('show.bs.modal', function(e) {
        const button = e.relatedTarget;
        const user = JSON.parse(button.dataset.user || '{}');
        document.getElementById('passwordUserId').value = button.dataset.userId;
        document.getElementById('passwordUserName').value = user.name || button.dataset.userName;
        document.getElementById('passwordUserEmail').value = user.email || '';
        document.getElementById('passwordUserRole').value = user.role || 'viewer';
        document.getElementById('passwordUserStatus').value = user.is_active || 1;
        document.getElementById('passwordUserDisplay').textContent = button.dataset.userName;
        passwordForm.action = '/usuarios/' + button.dataset.userId;
    });

    deleteModal.addEventListener('show.bs.modal', function(e) {
        const button = e.relatedTarget;
        document.getElementById('deleteUserId').value = button.dataset.userId;
        document.getElementById('deleteUserName').textContent = button.dataset.userName;
        deleteForm.action = '/usuarios/' + button.dataset.userId + '/excluir';
    });

    const searchInput = document.getElementById('userSearch');
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    const tableBody = document.getElementById('usersTableBody');

    function filterUsers() {
        const search = searchInput.value.toLowerCase();
        const role = roleFilter.value;
        const status = statusFilter.value;

        document.querySelectorAll('.user-row').forEach(row => {
            const name = row.dataset.name;
            const email = row.dataset.email;
            const userRole = row.dataset.role;
            const userStatus = row.dataset.status;

            const matchesSearch = name.includes(search) || email.includes(search);
            const matchesRole = !role || userRole === role;
            const matchesStatus = !status || userStatus === status;

            row.style.display = matchesSearch && matchesRole && matchesStatus ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterUsers);
    roleFilter.addEventListener('change', filterUsers);
    statusFilter.addEventListener('change', filterUsers);
});
</script>
