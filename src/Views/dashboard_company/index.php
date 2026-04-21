<?php startblock('title') ?>
    Dashboard Empresarial - <?= e($company['legal_name']) ?>
<?php endblock() ?>

<?php startblock('content') ?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Início</a></li>
            <li class="breadcrumb-item"><a href="/empresa/<?= e($company['cnpj']) ?>"><?= e($company['legal_name']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
        </ol>
    </nav>

    <h2 class="mb-4">
        <i class="bi bi-building"></i> 
        Dashboard Empresarial
    </h2>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Dados da Empresa</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">CNPJ</label>
                            <p class="mb-0 fw-bold"><?= format_cnpj($company['cnpj']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Razão Social</label>
                            <p class="mb-0"><?= e($company['legal_name']) ?></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="text-muted small">Nome Fantasia</label>
                            <p class="mb-0"><?= e($company['trade_name'] ?? '-') ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Situação</label>
                            <p class="mb-0">
                                <span class="badge bg-<?= ($company['status'] ?? '') === 'Ativa' ? 'success' : 'warning' ?>">
                                    <?= e($company['status'] ?? 'Desconhecida') ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Dados Complementares</h5>
                    <span class="text-muted small">Editáveis</span>
                </div>
                <div class="card-body">
                    <form method="post" action="/empresa/<?= e($company['cnpj']) ?>/dashboard">
                        <?= csrf_field() ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrição da Empresa</label>
                            <textarea name="description" class="form-control" rows="4" 
                                placeholder="Conte um pouco sobre sua empresa..."><?= e($company['description'] ?? '') ?></textarea>
                            <div class="form-text">Descrição que aparece no perfil público da empresa.</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Facebook</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-facebook"></i></span>
                                    <input type="url" name="facebook" class="form-control" 
                                        placeholder="https://facebook.com/suaempresa"
                                        value="<?= e($company['facebook'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Instagram</label>
                                <div class="input-group">
                                    <span class="input-group-text">@</span>
                                    <input type="text" name="instagram" class="form-control" 
                                        placeholder="suaempresa"
                                        value="<?= e($company['instagram'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">LinkedIn</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-linkedin"></i></span>
                                    <input type="url" name="linkedin" class="form-control" 
                                        placeholder="https://linkedin.com/company/suaempresa"
                                        value="<?= e($company['linkedin'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">WhatsApp</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-whatsapp"></i></span>
                                    <input type="tel" name="whatsapp" class="form-control" 
                                        placeholder="11999999999"
                                        value="<?= e($company['whatsapp'] ?? '') ?>">
                                </div>
                                <div class="form-text">Número com DDD, apenas números.</div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Nota:</strong> Os dados acima são complementares e não são enviados à Receita Federal. 
                            Eles aparecerão no perfil público da sua empresa no Plattadata.
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar Alterações
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up"></i> Estatísticas</h6>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <div class="display-6"><?= format_number($company['visits'] ?? 0) ?></div>
                        <p class="text-muted mb-0">Visualizações</p>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Favoritos</span>
                        <span class="badge bg-primary"><?= $company['favorites_count'] ?? 0 ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Consultas</span>
                        <span class="badge bg-secondary"><?= $company['queries_count'] ?? 0 ?></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-link"></i> Links Rápidos</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="/empresa/<?= e($company['cnpj']) ?>" class="list-group-item list-group-item-action">
                            <i class="bi bi-eye"></i> Ver Perfil Público
                        </a>
                        <a href="/empresas/compare?cnpj=<?= e($company['cnpj']) ?>" class="list-group-item list-group-item-action">
                            <i class="bi bi-bar-chart"></i> Comparar com Concorrentes
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endblock() ?>