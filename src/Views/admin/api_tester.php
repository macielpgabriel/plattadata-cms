<?php declare(strict_types=1); use App\Core\Csrf; ?>

<nav aria-label="breadcrumb" class="breadcrumb-container">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">Inicio</a></li>
        <li class="breadcrumb-item"><a href="/admin">Admin</a></li>
        <li class="breadcrumb-item"><a href="/admin/observabilidade">Observabilidade</a></li>
        <li class="breadcrumb-item active" aria-current="page">Testar APIs</li>
    </ol>
</nav>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 fade-in">
    <div>
        <h1 class="h3 mb-1">
            <i class="bi bi-patch-check me-2 text-muted"></i>Testar APIs Externas
        </h1>
        <p class="text-muted small mb-0">Valide conectividade e tokens das APIs</p>
    </div>
</div>

<div class="alert alert-info alert-permanent shadow-sm mb-4">
    <i class="bi bi-info-circle-fill me-2"></i>
    Estes testes realizam requisições reais às APIs externas configuradas no seu <code>.env</code>, mas <strong>não salvam nenhuma informação no banco de dados</strong>. Use para validar conectividade e tokens.
</div>

<div class="row g-4" x-data="apiTester()">
    <!-- Card BrasilAPI (CNPJ) -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title d-flex align-items-center mb-3">
                    <span class="p-2 bg-primary-subtle rounded me-2"><i class="bi bi-building text-primary"></i></span>
                    BrasilAPI (CNPJ)
                </h5>
                <p class="small text-muted mb-3">Consulta dados cadastrais de empresas via Receita Federal.</p>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" x-model="params.cnpj" class="form-control" placeholder="CNPJ (apenas numeros)">
                    <button class="btn btn-primary" @click="run('cnpj', params.cnpj)" :disabled="loading.cnpj">
                        <span x-show="!loading.cnpj">Testar</span>
                        <span x-show="loading.cnpj" class="spinner-border spinner-border-sm"></span>
                    </button>
                </div>
                <template x-if="results.cnpj">
                    <div :class="results.cnpj.ok ? 'text-success' : 'text-danger'" class="small fw-bold">
                        <i :class="results.cnpj.ok ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-triangle-fill'" class="me-1"></i>
                        <span x-text="results.cnpj.ok ? 'Sucesso (' + results.cnpj.duration_ms + 'ms)' : 'Erro: ' + results.cnpj.error"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Card IBGE -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title d-flex align-items-center mb-3">
                    <span class="p-2 bg-success-subtle rounded me-2"><i class="bi bi-geo-alt text-success"></i></span>
                    IBGE (Municipios)
                </h5>
                <p class="small text-muted mb-3">Busca populacao, PIB, frota e empresas.</p>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" x-model="params.ibge" class="form-control" placeholder="UF (ex: SP) ou IBGE (ex: 3550308)">
                    <button class="btn btn-success" @click="run('ibge', params.ibge)" :disabled="loading.ibge">
                        <span x-show="!loading.ibge">Testar</span>
                        <span x-show="loading.ibge" class="spinner-border spinner-border-sm"></span>
                    </button>
                </div>
                <template x-if="results.ibge">
                    <div :class="results.ibge.ok ? 'text-success' : 'text-danger'" class="small fw-bold">
                        <i :class="results.ibge.ok ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-triangle-fill'" class="me-1"></i>
                        <span x-text="results.ibge.ok ? 'Sucesso (' + results.ibge.duration_ms + 'ms)' : 'Erro: ' + results.ibge.error"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Card Banco Central -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title d-flex align-items-center mb-3">
                    <span class="p-2 bg-warning-subtle rounded me-2"><i class="bi bi-currency-dollar text-warning"></i></span>
                    BCB (Cambio PTAX)
                </h5>
                <p class="small text-muted mb-3">Cotacoes oficiais do Banco Central.</p>
                <div class="input-group input-group-sm mb-3">
                    <select x-model="params.bcb" class="form-select">
                        <option value="USD">Dolar (USD)</option>
                        <option value="EUR">Euro (EUR)</option>
                        <option value="GBP">Libra (GBP)</option>
                        <option value="ARS">Peso Argen. (ARS)</option>
                    </select>
                    <button class="btn btn-warning text-dark" @click="run('bcb', params.bcb)" :disabled="loading.bcb">
                        <span x-show="!loading.bcb">Testar</span>
                        <span x-show="loading.bcb" class="spinner-border spinner-border-sm"></span>
                    </button>
                </div>
                <template x-if="results.bcb">
                    <div :class="results.bcb.ok ? 'text-success' : 'text-danger'" class="small fw-bold">
                        <i :class="results.bcb.ok ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-triangle-fill'" class="me-1"></i>
                        <span x-text="results.bcb.ok ? 'Sucesso (' + results.bcb.duration_ms + 'ms)' : 'Erro: ' + results.bcb.error"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Card CPTEC/Clima -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title d-flex align-items-center mb-3">
                    <span class="p-2 bg-info-subtle rounded me-2"><i class="bi bi-cloud-sun text-info"></i></span>
                    CPTEC/INPE (Clima)
                </h5>
                <p class="small text-muted mb-3">Previsao do tempo por codigo IBGE.</p>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" x-model="params.cptec" class="form-control" placeholder="Codigo IBGE (ex: 3550308)">
                    <button class="btn btn-info text-white" @click="run('cptec', params.cptec)" :disabled="loading.cptec">
                        <span x-show="!loading.cptec">Testar</span>
                        <span x-show="loading.cptec" class="spinner-border spinner-border-sm"></span>
                    </button>
                </div>
                <template x-if="results.cptec">
                    <div :class="results.cptec.ok ? 'text-success' : 'text-danger'" class="small fw-bold">
                        <i :class="results.cptec.ok ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-triangle-fill'" class="me-1"></i>
                        <span x-text="results.cptec.ok ? 'Sucesso (' + results.cptec.duration_ms + 'ms)' : 'Erro: ' + results.cptec.error"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Card DDD -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title d-flex align-items-center mb-3">
                    <span class="p-2 bg-secondary-subtle rounded me-2"><i class="bi bi-telephone text-secondary"></i></span>
                    BrasilAPI (DDD)
                </h5>
                <p class="small text-muted mb-3">Cidades abrangidas por codigo DDD.</p>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" x-model="params.ddd" class="form-control" placeholder="DDD (ex: 11)">
                    <button class="btn btn-secondary" @click="run('ddd', params.ddd)" :disabled="loading.ddd">
                        <span x-show="!loading.ddd">Testar</span>
                        <span x-show="loading.ddd" class="spinner-border spinner-border-sm"></span>
                    </button>
                </div>
                <template x-if="results.ddd">
                    <div :class="results.ddd.ok ? 'text-success' : 'text-danger'" class="small fw-bold">
                        <i :class="results.ddd.ok ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-triangle-fill'" class="me-1"></i>
                        <span x-text="results.ddd.ok ? 'Sucesso (' + results.ddd.duration_ms + 'ms)' : 'Erro: ' + results.ddd.error"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Card Nominatim -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title d-flex align-items-center mb-3">
                    <span class="p-2 bg-danger-subtle rounded me-2"><i class="bi bi-map text-danger"></i></span>
                    Nominatim (Maps)
                </h5>
                <p class="small text-muted mb-3">Geocodificacao via OpenStreetMap.</p>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" x-model="params.nominatim" class="form-control" placeholder="Endereco ou cidade">
                    <button class="btn btn-danger" @click="run('nominatim', params.nominatim)" :disabled="loading.nominatim">
                        <span x-show="!loading.nominatim">Testar</span>
                        <span x-show="loading.nominatim" class="spinner-border spinner-border-sm"></span>
                    </button>
                </div>
                <template x-if="results.nominatim">
                    <div :class="results.nominatim.ok ? 'text-success' : 'text-danger'" class="small fw-bold">
                        <i :class="results.nominatim.ok ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-triangle-fill'" class="me-1"></i>
                        <span x-text="results.nominatim.ok ? 'Sucesso (' + results.nominatim.duration_ms + 'ms)' : 'Erro: ' + results.nominatim.error"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Card Google News -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title d-flex align-items-center mb-3">
                    <span class="p-2 bg-primary-subtle rounded me-2"><i class="bi bi-newspaper text-primary"></i></span>
                    Google News (RSS)
                </h5>
                <p class="small text-muted mb-3">Busca noticias por termo ou setor.</p>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" x-model="params.news" class="form-control" placeholder="Termo de busca">
                    <button class="btn btn-primary" @click="run('news', params.news)" :disabled="loading.news">
                        <span x-show="!loading.news">Testar</span>
                        <span x-show="loading.news" class="spinner-border spinner-border-sm"></span>
                    </button>
                </div>
                <template x-if="results.news">
                    <div :class="results.news.ok ? 'text-success' : 'text-danger'" class="small fw-bold">
                        <i :class="results.news.ok ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-triangle-fill'" class="me-1"></i>
                        <span x-text="results.news.ok ? 'Sucesso (' + results.news.duration_ms + 'ms)' : 'Erro: ' + results.news.error"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Card Portal da Transparencia -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title d-flex align-items-center mb-3">
                    <span class="p-2 bg-warning-subtle rounded me-2"><i class="bi bi-shield-check text-warning"></i></span>
                    Portal Transparencia
                </h5>
                <p class="small text-muted mb-3">Sancoes (CEIS/CNEP/CEPIM).</p>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" x-model="params.compliance" class="form-control" placeholder="CNPJ para sancao">
                    <button class="btn btn-warning text-dark" @click="run('compliance', params.compliance)" :disabled="loading.compliance">
                        <span x-show="!loading.compliance">Testar</span>
                        <span x-show="loading.compliance" class="spinner-border spinner-border-sm"></span>
                    </button>
                </div>
                <template x-if="results.compliance">
                    <div :class="results.compliance.ok ? 'text-success' : 'text-danger'" class="small fw-bold">
                        <i :class="results.compliance.ok ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-triangle-fill'" class="me-1"></i>
                        <span x-text="results.compliance.ok ? 'Sucesso (' + results.compliance.duration_ms + 'ms)' : 'Erro: ' + results.compliance.error"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Card ReceitaWS -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title d-flex align-items-center mb-3">
                    <span class="p-2 bg-success-subtle rounded me-2"><i class="bi bi-file-earmark-text text-success"></i></span>
                    ReceitaWS (Fallback)
                </h5>
                <p class="small text-muted mb-3">API alternativa para consulta de CNPJ.</p>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" x-model="params.receitaws" class="form-control" placeholder="CNPJ (apenas numeros)">
                    <button class="btn btn-success" @click="run('receitaws', params.receitaws)" :disabled="loading.receitaws">
                        <span x-show="!loading.receitaws">Testar</span>
                        <span x-show="loading.receitaws" class="spinner-border spinner-border-sm"></span>
                    </button>
                </div>
                <template x-if="results.receitaws">
                    <div :class="results.receitaws.ok ? 'text-success' : 'text-danger'" class="small fw-bold">
                        <i :class="results.receitaws.ok ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-triangle-fill'" class="me-1"></i>
                        <span x-text="results.receitaws.ok ? 'Sucesso (' + results.receitaws.duration_ms + 'ms)' : 'Erro: ' + results.receitaws.error"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Card Impostometro -->
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title d-flex align-items-center mb-3">
                    <span class="p-2 bg-primary-subtle rounded me-2"><i class="bi bi-cash-stack text-primary"></i></span>
                    Impostometro
                </h5>
                <p class="small text-muted mb-3">Dados de arrecadacao de impostos brasileiros.</p>
                <div class="input-group input-group-sm mb-3">
                    <button class="btn btn-primary w-100" @click="testImpostometro()" :disabled="loading.impostometro">
                        <span x-show="!loading.impostometro"><i class="bi bi-play me-1"></i>Testar API</span>
                        <span x-show="loading.impostometro" class="spinner-border spinner-border-sm"></span>
                    </button>
                </div>
                <template x-if="results.impostometro">
                    <div :class="results.impostometro.ok ? 'text-success' : 'text-danger'" class="small fw-bold">
                        <i :class="results.impostometro.ok ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-triangle-fill'" class="me-1"></i>
                        <span x-text="results.impostometro.ok ? 'Sucesso (' + results.impostometro.duration_ms + 'ms)' : 'Erro: ' + results.impostometro.error"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Resultado Detalhado (JSON) -->
    <div class="col-12 mt-4" x-show="lastResponse">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2">
                <h6 class="mb-0 small"><i class="bi bi-code-slash me-2"></i>Resposta JSON (Ultima Consulta)</h6>
                <button class="btn btn-link btn-sm text-white p-0" @click="lastResponse = null">Limpar</button>
            </div>
            <div class="card-body p-0" style="background: var(--surface);">
                <pre class="mb-0 p-3" style="max-height: 400px; overflow-y: auto; font-size: 0.75rem; white-space: pre-wrap; word-break: break-all;" x-text="JSON.stringify(lastResponse, null, 2)"></pre>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= (string) ($_SERVER['CSP_NONCE'] ?? '') ?>">
function apiTester() {
    return {
        params: {
            cnpj: '00000000000191',
            ibge: '3550308',
            bcb: 'USD',
            cptec: '3550308',
            ddd: '11',
            nominatim: 'Sao Paulo, SP',
            news: 'tecnologia',
            compliance: '00000000000191',
            receitaws: '00000000000191',
            bcbIndicator: 'all'
        },
        loading: {
            cnpj: false, ibge: false, bcb: false, cptec: false, ddd: false, nominatim: false, news: false, compliance: false, receitaws: false, 'bcb-indicators': false, impostometro: false
        },
        results: {
            cnpj: null, ibge: null, bcb: null, cptec: null, ddd: null, nominatim: null, news: null, compliance: null, receitaws: null, 'bcb-indicators': null, impostometro: null
        },
        lastResponse: null,
        testImpostometro() {
            this.loading.impostometro = true;
            this.results.impostometro = null;
            const start = Date.now();
            fetch('/api/impostometro')
                .then(res => res.json())
                .then(data => {
                    const duration = Date.now() - start;
                    this.results.impostometro = { ok: true, duration_ms: duration };
                    this.lastResponse = data;
                })
                .catch(err => {
                    this.results.impostometro = { ok: false, error: 'Erro na conexao.' };
                })
                .finally(() => {
                    this.loading.impostometro = false;
                });
        },
        run(api, param = '') {
            this.loading[api] = true;
            this.results[api] = null;
            
            const formData = new FormData();
            formData.append('api', api);
            formData.append('param', param);
            formData.append('_token', '<?= Csrf::token() ?>');

            fetch('/admin/api-tester/test', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                this.results[api] = data;
                if (data.ok) {
                    this.lastResponse = data.data;
                } else {
                    this.lastResponse = { error: data.error };
                }
            })
            .catch(err => {
                this.results[api] = { ok: false, error: 'Erro na conexao com o servidor.' };
                this.lastResponse = { error: 'Erro na conexao com o servidor.' };
            })
            .finally(() => {
                this.loading[api] = false;
            });
        }
    }
}
</script>
