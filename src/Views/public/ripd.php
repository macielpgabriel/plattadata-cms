<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <span class="badge bg-brand bg-opacity-10 text-brand px-3 py-2 rounded-pill mb-3 fw-bold">LGPD Art. 5-XVII</span>
                    <h1 class="display-6 fw-bold mb-3">Relatório de Impacto à Proteção de Dados Pessoais</h1>
                    <p class="text-muted lead mb-4">
                        Documentação dos processos de tratamento de dados pessoais e medidas de mitigação de riscos.
                    </p>
                    <hr class="my-4">
                    <div class="d-flex align-items-center text-muted small mb-4">
                        <i class="bi bi-clock-history me-2"></i> Última atualização: <?= e(date('d/m/Y')) ?>
                    </div>

                    <section class="mb-5">
                        <h2 class="h4 fw-bold mb-3">1. Base Legal</h2>
                        <p class="text-secondary">
                            O tratamento de dados pessoais realizado pela Plattadata tem como base legal:
                        </p>
                        <ul class="text-secondary">
                            <li><strong>Consentimento (Art. 7-I):</strong> obtido de forma livre, informada e inequívoca no momento do cadastro</li>
                            <li><strong>Execução de contrato (Art. 7-V):</strong> para prestação de serviços aos usuários autenticados</li>
                            <li><strong>Legítimo interesse (Art. 7-IX):</strong> para melhoria contínua dos serviços</li>
                        </ul>
                    </section>

                    <section class="mb-5">
                        <h2 class="h4 fw-bold mb-3">2. Dados Pessoais Tratados</h2>
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Categoria</th>
                                    <th>Dados</th>
                                    <th>Finalidade</th>
                                    <th>Base Legal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Cadastro</td>
                                    <td>Nome, e-mail, senha</td>
                                    <td>Autenticação e acesso aos serviços</td>
                                    <td>Consentimento</td>
                                </tr>
                                <tr>
                                    <td>Empresas</td>
                                    <td>CNPJ, razão social, endereço, CNAE</td>
                                    <td>Consulta pública de dados empresariais</td>
                                    <td>Dado público</td>
                                </tr>
                                <tr>
                                    <td>QSA</td>
                                    <td>Nome de sócios, CPF parcial</td>
                                    <td>Informação societária pública</td>
                                    <td>Dado público</td>
                                </tr>
                                <tr>
                                    <td>Favoritos</td>
                                    <td>Lista de empresas favoritadas</td>
                                    <td>Personalização do serviço</td>
                                    <td>Execução de contrato</td>
                                </tr>
                                <tr>
                                    <td>Avaliações</td>
                                    <td>Nota, comentário, resposta</td>
                                    <td>Feedback público sobre empresas</td>
                                    <td>Consentimento</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section class="mb-5">
                        <h2 class="h4 fw-bold mb-3">3. Medidas de Segurança</h2>
                        <ul class="text-secondary">
                            <li>Criptografia de senhas com bcrypt/Argon2</li>
                            <li> HTTPS com HSTS enabled</li>
                            <li>Headers de segurança (CSP, X-Frame-Options, X-Content-Type-Options)</li>
                            <li>Rate limiting para prevenir ataques</li>
                            <li>2FA obrigatório para administradores</li>
                            <li>Logs de auditoria de acesso</li>
                            <li>Backup regular do banco de dados</li>
                        </ul>
                    </section>

                    <section class="mb-5">
                        <h2 class="h4 fw-bold mb-3">4. Análise de Riscos</h2>
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Risco</th>
                                    <th>Probabilidade</th>
                                    <th>Impacto</th>
                                    <th>Mitigação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Acesso não autorizado</td>
                                    <td>Baixa</td>
                                    <td>Alto</td>
                                    <td>Hash forte, 2FA admin, logs</td>
                                </tr>
                                <tr>
                                    <td>Vazamento de dados</td>
                                    <td>Baixa</td>
                                    <td>Alto</td>
                                    <td>Encryption at rest, backups seguros</td>
                                </tr>
                                <tr>
                                    <td>Ataque DDoS</td>
                                    <td>Média</td>
                                    <td>Médio</td>
                                    <td>Rate limiting, CDN</td>
                                </tr>
                                <tr>
                                    <td>Phishing</td>
                                    <td>Média</td>
                                    <td>Médio</td>
                                    <td>Educação, e-mails autenticados</td>
                                </tr>
                                <tr>
                                    <td>Dados incorretos</td>
                                    <td>Baixa</td>
                                    <td>Baixo</td>
                                    <td>Correção via /meus-dados</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section class="mb-5">
                        <h2 class="h4 fw-bold mb-3">5. Direitos dos Titulares</h2>
                        <p class="text-secondary">
                            Os titulares podem exercer seus direitos conforme Art. 18 da LGPD:
                        </p>
                        <ul class="text-secondary">
                            <li><strong>Confirmação:</strong> Solicitação de confirmação sobre tratamento</li>
                            <li><strong>Acesso:</strong> <a href="/meus-dados" class="text-brand">Download dos dados</a></li>
                            <li><strong>Correção:</strong> Via dashboard do usuário</li>
                            <li><strong>Anonimização/Eliminação:</strong> Solicitar via e-mail</li>
                            <li><strong>Portabilidade:</strong> <a href="/meus-dados" class="text-brand">Download em JSON</a></li>
                            <li><strong>Revogação:</strong> Solicitar via e-mail</li>
                        </ul>
                    </section>

                    <section class="mb-5">
                        <h2 class="h4 fw-bold mb-3">6. Encarregado de Proteção de Dados</h2>
                        <p class="text-secondary">
                            Para exercer seus direitos ou esclarecer dúvidas sobre este relatório:
                        </p>
                        <p>
                            <strong>E-mail:</strong> <a href="mailto:contato@plattadata.com" class="text-brand">contato@plattadata.com</a>
                        </p>
                    </section>

                    <section>
                        <h2 class="h4 fw-bold mb-3">7. Histórico de Revisões</h2>
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Versão</th>
                                    <th>Alterações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?= e(date('d/m/Y')) ?></td>
                                    <td>1.0</td>
                                    <td>Versão inicial do RIPD</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                </div>
            </div>
        </div>
    </div>
</div>