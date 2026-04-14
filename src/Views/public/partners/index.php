<?php declare(strict_types=1); use App\Core\Csrf; ?>
<?php 
$metaTitle = $title;
$metaDescription = $metaDescription ?? ($meta_description ?? null);
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Sócios</li>
    </ol>
</nav>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="display-5 fw-bold">Sócios de Empresas</h1>
        <p class="lead">Explore os principais sócios de empresas no Brasil e suas participações.</p>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-hover">
        <thead class="bg-secondary-subtle">
            <tr>
                <th>Nome do Sócio</th>
                <th class="text-end">Empresas</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($partners as $p): ?>
            <tr>
                <td><a href="/socios/<?= urlencode($p['partner_name']) ?>"><?= htmlspecialchars($p['partner_name']) ?></a></td>
                <td class="text-end fw-bold text-brand"><?= number_format($p['total_empresas'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (empty($partners)): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    Nenhum socio encontrado.
</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Erro:</strong> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>