<?php declare(strict_types=1);
use App\Core\Database;
use App\Repositories\CompanyRepository;

$initialState = $initialState ?? null;
$initialCity = $initialCity ?? null;

$states = [];
try {
    $db = Database::connection();
    $stmt = $db->query("SELECT DISTINCT state FROM companies WHERE state IS NOT NULL AND state != '' ORDER BY state LIMIT 30");
    $states = $stmt->fetchAll(\PDO::FETCH_COLUMN);
} catch (\Exception $e) {
    $states = ['SP', 'RJ', 'MG', 'RS', 'PR', 'BA', 'PE', 'CE'];
}
?>

<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item active" aria-current="page">Mapa de Empresas</li>
    </ol>
</nav>

<style>
    #map { height: 600px; width: 100%; border-radius: 12px; }
    .map-controls { background: #fff; padding: 15px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
    .marker-info { padding: 8px; }
    .marker-info h6 { margin: 0 0 5px 0; color: #0d9488; }
    .marker-info small { color: #64748b; }
    .leaflet-popup-content-wrapper { border-radius: 8px; }
    .stats-bar { display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap; }
    .stat-item { text-align: center; }
    .stat-value { font-size: 1.5rem; font-weight: bold; color: #0d9488; }
    .stat-label { font-size: 0.8rem; color: #64748b; }
    [data-theme="dark"] .map-controls { background: var(--surface); }
    [data-theme="dark"] .stat-value { color: var(--brand); }
</style>

<div class="fade-in">
    <h1 class="h3 mb-4">
        <i class="bi bi-geo-alt me-2 text-muted"></i>Mapa de Empresas
    </h1>

    <div class="map-controls">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select class="form-select" id="stateFilter" onchange="loadCities()">
                    <option value="">Todos</option>
                    <?php foreach ($states as $state): ?>
                        <option value="<?= e($state) ?>" <?= $initialState === $state ? 'selected' : '' ?>><?= e($state) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Cidade</label>
                <select class="form-select" id="cityFilter" disabled>
                    <option value="">Selecione um estado</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <button class="btn btn-primary w-100" onclick="loadMarkers()">
                    <i class="bi bi-search me-1"></i>Atualizar Mapa
                </button>
            </div>
            <div class="col-md-2">
                <label class="form-label">Resultados</label>
                <div class="stat-value" id="resultCount">0</div>
            </div>
        </div>
    </div>

    <div id="map"></div>
    
    <div class="mt-3 text-muted small">
        <i class="bi bi-info-circle me-1"></i>
        Mostrando até 1000 empresas com coordenadas. Clique nos marcadores para detalhes.
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let map;
    let markers = [];
    const defaultCenter = [-14.235, -51.925];
    const defaultZoom = 4;

    document.addEventListener('DOMContentLoaded', function() {
        initMap();
        loadCities();
        
        const state = '<?= e($initialState ?? '') ?>';
        if (state) {
            setTimeout(() => {
                loadMarkers();
                if ('<?= e($initialCity ?? '') ?>') {
                    document.getElementById('cityFilter').value = '<?= e($initialCity ?? '') ?>';
                }
            }, 500);
        }
    });

    function initMap() {
        map = L.map('map').setView(defaultCenter, defaultZoom);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
    }

    async function loadCities() {
        const state = document.getElementById('stateFilter').value;
        const citySelect = document.getElementById('cityFilter');
        
        if (!state) {
            citySelect.innerHTML = '<option value="">Selecione um estado</option>';
            citySelect.disabled = true;
            return;
        }
        
        citySelect.innerHTML = '<option value="">Carregando...</option>';
        
        try {
            const response = await fetch('/empresas?state=' + state + '&format=json');
            const data = await response.json();
            
            if (data.cities) {
                citySelect.innerHTML = '<option value="">Todas</option>';
                data.cities.forEach(city => {
                    citySelect.innerHTML += '<option value="' + city + '">' + city + '</option>';
                });
                citySelect.disabled = false;
            } else {
                citySelect.innerHTML = '<option value="">Sem cidades</option>';
            }
        } catch (e) {
            citySelect.innerHTML = '<option value="">Erro ao carregar</option>';
        }
    }

    async function loadMarkers() {
        const state = document.getElementById('stateFilter').value;
        const city = document.getElementById('cityFilter').value;
        
        const params = new URLSearchParams();
        if (state) params.append('state', state);
        if (city) params.append('city', city);
        params.append('limit', '1000');
        
        document.getElementById('resultCount').textContent = '...';
        
        try {
            const response = await fetch('/empresas/api/mapa?' + params.toString());
            const data = await response.json();
            
            if (!data.success) throw new Error(data.error);
            
            clearMarkers();
            
            data.markers.forEach(m => {
                const marker = L.marker([m.lat, m.lng], {
                    icon: L.divIcon({
                        className: 'custom-marker',
                        html: '<div style="background:#0d9488;width:12px;height:12px;border-radius:50%;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,0.3)"></div>',
                        iconSize: [16, 16],
                        iconAnchor: [8, 8]
                    })
                });
                
                const popupContent = `
                    <div class="marker-info">
                        <h6><a href="/empresas/${m.id}">${m.name}</a></h6>
                        <small>${m.city}/${m.state}</small><br>
                        <span class="badge bg-${m.status === 'ativa' ? 'success' : 'secondary'}">${m.status}</span>
                        ${m.size ? '<span class="badge bg-info ms-1">' + m.size + '</span>' : ''}
                        ${m.cnae ? '<div class="text-muted mt-1">' + m.cnae.substring(0, 40) + '...</div>' : ''}
                    </div>
                `;
                
                marker.bindPopup(popupContent);
                marker.addTo(map);
                markers.push(marker);
            });
            
            document.getElementById('resultCount').textContent = data.count;
            
            if (data.markers.length > 0) {
                const bounds = L.latLngBounds(data.markers.map(m => [m.lat, m.lng]));
                map.fitBounds(bounds, { padding: [50, 50], maxZoom: 12 });
            }
            
        } catch (e) {
            console.error('Erro:', e);
            document.getElementById('resultCount').textContent = 'Erro';
        }
    }

    function clearMarkers() {
        markers.forEach(m => map.removeLayer(m));
        markers = [];
    }
</script>