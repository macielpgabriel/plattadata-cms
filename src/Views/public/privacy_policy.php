<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 20px;">
                <div class="card-body p-4 p-md-5">
                    <div class="mb-5">
                        <span class="badge bg-brand bg-opacity-10 text-brand px-3 py-2 rounded-pill mb-3 fw-bold">Segurança & Conformidade</span>
                        <h1 class="display-6 fw-bold mb-3">Política de Privacidade e Tratamento de Dados</h1>
                        <p class="text-muted lead mb-0">Entenda como tratamos seus dados com transparência, segurança e em conformidade com a LGPD (Lei nº 13.709/2018) e o Marco Civil da Internet (Lei nº 12.965/2014).</p>
                        <hr class="my-4 opacity-25">
                        <div class="d-flex align-items-center text-muted small">
                            <i class="bi bi-clock-history me-2"></i> Última atualização: <?= e(date('d/m/Y')) ?>
                        </div>
                    </div>

                    <div class="privacy-content">
                        <section class="mb-5">
                            <h2 class="h4 fw-bold mb-3 d-flex align-items-center">
                                <span class="bg-brand text-white rounded-circle d-inline-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-size: 0.9rem;">1</span>
                                Quem Somos
                            </h2>
                            <p class="text-secondary ps-5 ms-2">
                                <strong><?= e(site_setting('site_name', 'PlattaData')) ?></strong> é uma plataforma de dados para prospecção B2B que ajuda empresas a encontrarem potenciais clientes empresariais.
                            </p>
                            <p class="text-secondary ps-5 ms-2">
                                CNPJ: <?= e(site_setting('site_cnpj', 'A definir')) ?><br>
                                E-mail: <a href="mailto:<?= e(config('mail.admin_email', 'contato@plattadata.com')) ?>" class="text-brand"><?= e(config('mail.admin_email', 'contato@plattadata.com')) ?></a>
                            </p>
                        </section>

                        <section class="mb-5">
                            <h2 class="h4 fw-bold mb-3 d-flex align-items-center">
                                <span class="bg-brand text-white rounded-circle d-inline-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-size: 0.9rem;">2</span>
                                Finalidade do Tratamento
                            </h2>
                            <p class="text-secondary ps-5 ms-2">
                                Tratamos Dados Pessoais com o objetivo de identificar empresas e, por consequência, também seus sócios e administradores, localizadas em determinadas regiões geográficas ou em um segmento de mercado pré-definido pelos nossos clientes.
                            </p>
                            <p class="text-secondary ps-5 ms-2">
                                Para cumprir este objetivo, usamos tecnologia para capturar dados de diversas fontes públicas na internet e automatizar o cruzamento de informações e a análise de cenários complexos para nossos clientes.
                            </p>
                        </section>

                        <section class="mb-5">
                            <h2 class="h4 fw-bold mb-3 d-flex align-items-center">
                                <span class="bg-brand text-white rounded-circle d-inline-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-size: 0.9rem;">3</span>
                                Origem dos Dados
                            </h2>
                            <p class="text-secondary ps-5 ms-2">
                                Todos os dados tratados são originalmente dados de pessoas jurídicas, obtidos do ambiente público da internet, incluindo:
                            </p>
                            <ul class="text-secondary ps-5 ms-2">
                                <li><strong>APIs governamentais:</strong> Receita Federal (via BrasilAPI), IBGE, Banco Central do Brasil</li>
                                <li><strong>Portais de transparência:</strong> Portal da Transparência (CEIS, CNEP, CEPIM)</li>
                                <li><strong>Bases sancionatórias:</strong> OpenSanctions (listas internacionais de sanções)</li>
                                <li><strong>Websites públicos:</strong> Páginas disponíveis abertamente na internet</li>
                                <li><strong>Buscadores e redes sociais:</strong> Informações de contato disponíveis publicamente</li>
                            </ul>
                            <p class="text-secondary ps-5 ms-2">
                                Todas as informações capturadas têm a sua origem armazenada e são sempre obtidas de páginas públicas na internet, ou seja, aquelas que qualquer pessoa consegue acessar de forma irrestrita e sem necessidade de autenticação.
                            </p>
                        </section>

                        <section class="mb-5">
                            <h2 class="h4 fw-bold mb-3 d-flex align-items-center">
                                <span class="bg-brand text-white rounded-circle d-inline-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-size: 0.9rem;">4</span>
                                Dados Coletados
                            </h2>
                            <div class="ps-5 ms-2">
                                <h6 class="fw-bold text-dark">Dados de Empresas (CNPJ):</h6>
                                <ul class="text-secondary small">
                                    <li>Razão Social, Nome Fantasia, CNPJ</li>
                                    <li>Situação Cadastral (Ativa, Inapta, Baixada, etc.)</li>
                                    <li>Endereço completo (logradouro, bairro, CEP, cidade, UF)</li>
                                    <li>Website da empresa (extraído do domínio público)</li>
                                    <li>Logo da empresa (via Clearbit API, baseado em domínio público)</li>
                                    <li>CNAE Primário e CNAEs Secundários</li>
                                    <li>Data de Abertura, Capital Social</li>
                                    <li>Natureza Jurídica, Porte</li>
                                    <li>Estimativa de funcionários (baseada no porte da empresa)</li>
                                    <li>Dados do Simples Nacional e MEI</li>
                                    <li>Inscrições Estaduais por UF</li>
                                </ul>

                                <h6 class="fw-bold text-dark mt-3">Dados de Sócios e Administradores (QSA):</h6>
                                <ul class="text-secondary small">
                                    <li>Nome completo</li>
                                    <li>Qualificação (cargo/função na empresa)</li>
                                    <li>CPF (parcialmente mascarado em acessos públicos)</li>
                                    <li>Faixa etária</li>
                                    <li>Data de entrada na sociedade</li>
                                </ul>

                                <h6 class="fw-bold text-dark mt-3">Dados de Compliance:</h6>
                                <ul class="text-secondary small">
                                    <li>Inscrições em listas de sanções (CEIS, CNEP, CEPIM)</li>
                                    <li>Resultados de verificação contra bases internacionais</li>
                                </ul>

                                <h6 class="fw-bold text-dark mt-3">Dados de Navegação:</h6>
                                <ul class="text-secondary small">
                                    <li>Endereço IP</li>
                                    <li>Tipo de navegador e versão</li>
                                    <li>Sistema operacional</li>
                                    <li>Resolução da tela</li>
                                    <li>Idioma do navegador</li>
                                    <li>Páginas visitadas e tempo de navegação</li>
                                </ul>
                            </div>
                        </section>

                        <section class="mb-5">
                            <h2 class="h4 fw-bold mb-3 d-flex align-items-center">
                                <span class="bg-brand text-white rounded-circle d-inline-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-size: 0.9rem;">5</span>
                                Cookies e Tecnologias
                            </h2>
                            <p class="text-secondary ps-5 ms-2">
                                Utilizamos cookies e tecnologias similares para:
                            </p>
                            <ul class="text-secondary ps-5 ms-2">
                                <li>Funcionamento adequado do site</li>
                                <li>Proteção de segurança</li>
                                <li>Experiência personalizada</li>
                                <li>Análise anônima de tráfego (Google Analytics ou similar)</li>
                            </ul>
                            <p class="text-secondary ps-5 ms-2 small">
                                Cookies são arquivos de texto contendo pequenas quantidades de informação armazenados no seu dispositivo. Você pode configurar seu navegador para recusar cookies.
                            </p>
                        </section>

                        <section class="mb-5">
                            <h2 class="h4 fw-bold mb-3 d-flex align-items-center">
                                <span class="bg-brand text-white rounded-circle d-inline-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-size: 0.9rem;">6</span>
                                Minimização e Mascaramento
                            </h2>
                            <p class="text-secondary ps-5 ms-2">
                                Quando houver dados pessoais no retorno de APIs públicas (como CPF de sócios), aplicamos:
                            </p>
                            <ul class="text-secondary ps-5 ms-2">
                                <li><strong>Mascaramento de CPFs:</strong> Exibição parcial (ex: ***.123.456-**) em acessos públicos</li>
                                <li><strong>Classificação por perfil:</strong> Usuários autenticados e autorizados veem dados completos</li>
                                <li><strong>Minimização:</strong> Armazenamos apenas dados necessários para a finalidade declarada</li>
                            </ul>
                        </section>

                        <section class="mb-5">
                            <h2 class="h4 fw-bold mb-3 d-flex align-items-center">
                                <span class="bg-brand text-white rounded-circle d-inline-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-size: 0.9rem;">7</span>
                                Armazenamento e Segurança
                            </h2>
                            <p class="text-secondary ps-5 ms-2">
                                Seguimos as melhores práticas de Segurança da Informação para armazenar os dados, aplicando criptografia baseada em padrões de mercado desde a entrada da informação em nossos sistemas.
                            </p>
                            <p class="text-secondary ps-5 ms-2">
                                As informações são armazenadas em nuvem (cloud) em serviços de terceiros especializados em hospedagem e computação em nuvem.
                            </p>
                            <p class="text-secondary ps-5 ms-2">
                                Implementamos controles rigorosos:
                            </p>
                            <ul class="text-secondary ps-5 ms-2">
                                <li>Registro de consultas (usuário, CNPJ consultado, timestamp, IP)</li>
                                <li>Rate limit para prevenir abuso</li>
                                <li>Autenticação de dois fatores (2FA) para administradores</li>
                                <li>Criptografia de senhas com bcrypt</li>
                                <li>HTTPS com HSTS</li>
                                <li>Headers de segurança (CSP, X-Frame-Options, etc.)</li>
                            </ul>
                        </section>

                        <section class="mb-5">
                            <h2 class="h4 fw-bold mb-3 d-flex align-items-center">
                                <span class="bg-brand text-white rounded-circle d-inline-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-size: 0.9rem;">8</span>
                                Compartilhamento de Dados
                            </h2>
                            <p class="text-secondary ps-5 ms-2">
                                Comercializamos as informações capturadas do ambiente público da internet com nossos clientes para que eles consigam fazer seu planejamento de marketing e vendas e alcançar potenciais novos clientes empresariais. Esta atividade está no coração do que fazemos como empresa.
                            </p>
                            <p class="text-secondary ps-5 ms-2">
                                Os dados compartilhados são sempre dados de pessoas jurídicas (empresas) e seus representantes legais, obtidos de fontes públicas.
                            </p>
                        </section>

                        <section class="mb-5">
                            <h2 class="h4 fw-bold mb-3 d-flex align-items-center">
                                <span class="bg-brand text-white rounded-circle d-inline-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-size: 0.9rem;">9</span>
                                Retenção de Dados
                            </h2>
                            <p class="text-secondary ps-5 ms-2">
                                Dados Pessoais são armazenados somente pelo tempo necessário para cumprir com as finalidades para as quais tenham sido coletados, salvo se houver outra razão para manutenção (ex: obrigações legais).
                            </p>
                            <ul class="text-secondary ps-5 ms-2">
                                <li>Logs de consulta: <?= e((int)config('app.retention.company_query_logs_days', 180)) ?> dias</li>
                                <li>Payloads de APIs: <?= e((int)config('app.retention.company_source_payloads_days', 180)) ?> dias</li>
                                <li>Snapshots de empresas: <?= e((int)config('app.retention.company_snapshots_days', 365)) ?> dias</li>
                                <li>Logs LGPD: <?= e((int)config('app.retention.lgpd_audit_logs_days', 365)) ?> dias</li>
                            </ul>
                        </section>

                        <section class="mb-5">
                            <h2 class="h4 fw-bold mb-3 d-flex align-items-center">
                                <span class="bg-brand text-white rounded-circle d-inline-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-size: 0.9rem;">10</span>
                                Seus Direitos (LGPD Art. 18)
                            </h2>
                            <p class="text-secondary ps-5 ms-2">
                                Você pode solicitar, gratuitamente:
                            </p>
                            <ul class="text-secondary ps-5 ms-2">
                                <li>Confirmação de existência de tratamento</li>
                                <li>Acesso aos seus dados pessoais</li>
                                <li>Correção de dados incompletos ou desatualizados</li>
                                <li>Anonimização, bloqueio ou eliminação de dados desnecessários ou excessivos</li>
                                <li>Informação sobre compartilhamento com terceiros</li>
                                <li>Informação sobre a possibilidade de não fornecer consentimento e consequências da negativa</li>
                                <li>Revogação do consentimento</li>
                            </ul>
                            <p class="text-secondary ps-5 ms-2 small">
                                Podemos solicitar prova da sua identidade para assegurar que a partilha de dados seja feita apenas com o titular.
                            </p>
                        </section>

                        <section class="mb-5">
                            <h2 class="h4 fw-bold mb-3 d-flex align-items-center">
                                <span class="bg-brand text-white rounded-circle d-inline-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-size: 0.9rem;">11</span>
                                Encarregado de Dados (DPO)
                            </h2>
                            <p class="text-secondary ps-5 ms-2">
                                Para questões relacionadas à privacidade e proteção de dados pessoais, entre em contato com nosso Encarregado:
                            </p>
                            <p class="text-secondary ps-5 ms-2">
                                <strong>E-mail:</strong> <a href="mailto:<?= e(config('mail.admin_email', 'privacidade@plattadata.com')) ?>" class="text-brand"><?= e(config('mail.admin_email', 'privacidade@plattadata.com')) ?></a>
                            </p>
                        </section>

                        <section class="mb-5">
                            <h2 class="h4 fw-bold mb-3 d-flex align-items-center">
                                <span class="bg-brand text-white rounded-circle d-inline-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-size: 0.9rem;">12</span>
                                Contato
                            </h2>
                            <div class="ps-5 ms-2">
                                <p class="text-secondary">Para dúvidas sobre privacidade, tratamento de dados ou exercício de direitos de titular, utilize os canais abaixo:</p>
                                
                                <?php
                                $privacyEmail = site_setting('contact_email', (string) config('mail.admin_email', ''));
                                $privacyWhatsapp = site_setting('contact_whatsapp', '');
                                $privacyPhone = site_setting('contact_phone', '');
                                ?>

                                <div class="row g-3 mt-2">
                                    <?php if ($privacyEmail !== ''): ?>
                                        <div class="col-md-6">
                                            <div class="p-3 bg-secondary-subtle rounded-3 border">
                                                <small class="text-muted d-block fw-bold text-uppercase x-small mb-1">E-mail</small>
                                                <a href="mailto:<?= e($privacyEmail) ?>" class="text-brand text-decoration-none fw-medium"><?= e($privacyEmail) ?></a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($privacyWhatsapp !== ''): ?>
                                        <div class="col-md-6">
                                            <div class="p-3 bg-secondary-subtle rounded-3 border">
                                                <small class="text-muted d-block fw-bold text-uppercase x-small mb-1">WhatsApp</small>
                                                <span class="fw-medium"><?= e($privacyWhatsapp) ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($privacyEmail === '' && $privacyWhatsapp === '' && $privacyPhone === ''): ?>
                                    <p class="small text-muted italic">Canais de contato ainda não configurados.</p>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>
                </div>
                <div class="card-footer bg-secondary-subtle border-0 p-4 text-center">
                    <a href="/" class="btn btn-brand px-4 rounded-pill fw-bold shadow-sm">Voltar ao Início</a>
                </div>
            </div>
        </div>
    </div>
</div>
