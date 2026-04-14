<?php declare(strict_types=1); use App\Core\Csrf; ?>
<?php 
$metaTitle = $title;
$metaDescription = $metaDescription ?? ($meta_description ?? null);
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Atividades</li>
    </ol>
</nav>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="display-5 fw-bold">Atividades Econômicas (CNAE)</h1>
        <p class="lead">Explore as principais atividades econômicas do Brasil e o número de empresas em cada segmento.</p>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-hover">
        <thead class="bg-secondary-subtle">
            <tr>
                <th>Código CNAE</th>
                <th>Descrição</th>
                <th>Seção</th>
                <th class="text-end">Empresas</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($activities as $act): ?>
            <tr>
                <td><code><?= htmlspecialchars($act['code']) ?></code></td>
                <td><?= htmlspecialchars($act['description']) ?></td>
                <td><span class="badge bg-secondary-subtle"><?= htmlspecialchars($act['section'] ?? 'Outros') ?></span></td>
                <td class="text-end fw-bold text-brand"><?= number_format($act['total_empresas'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (empty($activities)): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    Nenhuma atividade encontrada. É necessário ter empresas cadastradas no banco de dados para exibir os CNAEs.
</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Erro:</strong> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>