/**
 * CMS Plattadata - JavaScript Customizado
 * Melhorias de UX - Refatorado
 */

(function() {
    'use strict';

    const Utils = {
        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        },

        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        },

        formatCNPJ(cnpj) {
            return cnpj.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
        },

        formatPhone(phone) {
            return phone.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
        },

        setLoadingState(button, loading = true, originalHtml = null) {
            if (!button) return originalHtml;
            
            if (loading) {
                originalHtml = button.innerHTML;
                button.disabled = true;
                return originalHtml;
            } else {
                button.disabled = false;
                if (originalHtml) button.innerHTML = originalHtml;
                return null;
            }
        },

        validateField(input) {
            if (!input.required && !input.value) {
                input.classList.remove('is-valid', 'is-invalid');
                return;
            }
            input.classList.toggle('is-invalid', !input.checkValidity());
            input.classList.toggle('is-valid', input.checkValidity());
        },

        showSkeleton(containerSelector, count = 3) {
            const container = document.querySelector(containerSelector);
            if (!container) return;
            container.innerHTML = Array(count).fill(`
                <tr class="skeleton-row">
                    <td><div class="skeleton skeleton-text"></div></td>
                    <td><div class="skeleton skeleton-text"></div></td>
                    <td><div class="skeleton skeleton-text short"></div></td>
                    <td><div class="skeleton skeleton-text"></div></td>
                </tr>
            `).join('');
        },

        hideSkeleton(containerSelector) {
            const container = document.querySelector(containerSelector);
            if (!container) return;
            container.querySelectorAll('.skeleton-row').forEach(row => row.remove());
        },

        formatServerDate(dateString) {
            if (!dateString) {
                const now = new Date();
                return `${now.getDate().toString().padStart(2, '0')}/${(now.getMonth() + 1).toString().padStart(2, '0')} ${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
            }
            const date = new Date(dateString);
            return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
        }
    };

    const Masks = {
        cnpj(input) {
            input.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '').slice(0, 14);
                const patterns = [
                    { len: 14, regex: /^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, format: '$1.$2.$3/$4-$5' },
                    { len: 13, regex: /^(\d{2})(\d{3})(\d{3})(\d{4})(\d{1})$/, format: '$1.$2.$3/$4-$5' },
                    { len: 9, regex: /^(\d{2})(\d{3})(\d{3})(\d{1})$/, format: '$1.$2.$3/$4' },
                    { len: 6, regex: /^(\d{2})(\d{3})(\d{3})$/, format: '$1.$2.$3' },
                    { len: 3, regex: /^(\d{2})(\d{3})$/, format: '$1.$2' }
                ];
                for (const p of patterns) {
                    if (value.length >= p.len) {
                        value = value.replace(p.regex, p.format);
                        break;
                    }
                }
                this.value = value;
            });

            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                const tempInput = document.createElement('input');
                tempInput.value = pastedText;
                tempInput.dispatchEvent(new Event('input'));
                this.value = tempInput.value;
            });
        },

        phone(input) {
            input.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '').slice(0, 11);
                if (value.length > 6) {
                    value = value.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
                } else if (value.length > 2) {
                    value = value.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
                }
                this.value = value;
            });
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        initPageLoader();
        initBackToTop();
        initFormEnhancements();
        initToastNotifications();
        initSmoothScroll();
        initAutoHideAlerts();
        initConfirmActions();
        initMaskInputs();
        initCopyToClipboard();
        initPasswordToggle();
        initFadeInAnimations();
        initLazyMapEmbeds();
        initDarkMode();
        initTooltips();
        initMunicipalityActions();
        initSyncLoader();
        initCookieConsent();
        initLazyImages();
    });

    function initSyncLoader() {
        const syncLoader = document.getElementById('sync-loader');
        if (!syncLoader) return;

        syncLoader.classList.remove('active');
        syncLoader.setAttribute('aria-hidden', 'true');

        document.addEventListener('submit', function(e) {
            const form = e.target;
            const action = form.getAttribute('action') || '';
            const btn = form.querySelector('button[type="submit"]');

            if (action.includes('/atualizar') || action.includes('/sync') || (btn && btn.classList.contains('sync-trigger'))) {
                if (btn && btn.classList.contains('no-loading')) return;

                syncLoader.classList.add('active');
                syncLoader.setAttribute('aria-hidden', 'false');

                if (btn) {
                    const originalHtml = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sincronizando...';
                    
                    setTimeout(() => {
                        if (btn.disabled) {
                            btn.disabled = false;
                            btn.innerHTML = originalHtml;
                        }
                    }, 15000);
                }
            }
        });
    }

    function initCookieConsent() {
        if (localStorage.getItem('cookie_accepted')) return;

        const banner = document.createElement('div');
        banner.className = 'cookie-banner shadow-lg border-0';
        banner.innerHTML = `
            <div class="d-flex align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-brand bg-opacity-10 p-2 rounded-circle d-none d-sm-block">
                        <i class="bi bi-shield-check text-brand"></i>
                    </div>
                    <div>
                        <p class="small mb-0 text-dark fw-medium">Este site utiliza cookies para sua melhor experiência.</p>
                        <a href="/politica-de-privacidade" class="text-brand x-small text-decoration-none hover-underline">Ver política de privacidade</a>
                    </div>
                </div>
                <div class="d-flex gap-2 shrink-0">
                    <button class="btn btn-brand btn-sm px-3 fw-bold" id="accept-cookies" style="font-size: 0.75rem; border-radius: 20px;">Aceitar</button>
                    <button class="btn btn-link btn-sm text-muted p-0 ms-1" id="close-cookies" aria-label="Fechar"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
        `;

        document.body.appendChild(banner);
        setTimeout(() => banner.classList.add('show'), 1000);

        const closeBanner = () => {
            banner.classList.remove('show');
            setTimeout(() => banner.remove(), 500);
        };

        document.getElementById('accept-cookies').addEventListener('click', function() {
            localStorage.setItem('cookie_accepted', 'true');
            closeBanner();
        });

        document.getElementById('close-cookies').addEventListener('click', closeBanner);
    }

    window.confirmAction = function(options) {
        return new Promise(function(resolve) {
            const modal = document.getElementById('confirmModal');
            if (!modal) {
                resolve(confirm(options.message || 'Tem certeza?'));
                return;
            }

            const titleEl = document.getElementById('confirmModalTitle');
            const bodyEl = document.getElementById('confirmModalBody');
            const btnEl = document.getElementById('confirmModalBtn');

            titleEl.innerHTML = '<i class="bi ' + Utils.escapeHtml(options.icon || 'bi-question-circle') + ' me-2"></i>' + Utils.escapeHtml(options.title || 'Confirmar');
            bodyEl.textContent = options.message || 'Tem certeza que deseja continuar?';
            btnEl.className = 'btn ' + (options.btnClass || 'btn-danger');
            btnEl.textContent = '\u2713 ' + (options.btnText || 'Confirmar');

            const bsModal = new bootstrap.Modal(modal);
            const cleanup = function() {
                modal.removeEventListener('hidden.bs.modal', cleanup);
                resolve(false);
            };
            modal.addEventListener('hidden.bs.modal', cleanup);
            btnEl.onclick = function() {
                modal.removeEventListener('hidden.bs.modal', cleanup);
                bsModal.hide();
                resolve(true);
            };

            bsModal.show();
        });
    };

    function initPageLoader() {
        const loader = document.querySelector('.page-loader');
        if (!loader) return;

        const hideLoader = function() {
            if (loader.dataset.hidden) return;
            loader.dataset.hidden = 'true';
            
            setTimeout(function() {
                loader.style.opacity = '0';
                setTimeout(function() {
                    loader.remove();
                }, 300);
            }, 500);
        };

        window.addEventListener('load', hideLoader);
        setTimeout(hideLoader, 10000);
    }

    function initBackToTop() {
        const btn = document.createElement('button');
        btn.className = 'back-to-top';
        btn.innerHTML = '<i class="bi bi-chevron-up"></i>';
        btn.setAttribute('aria-label', 'Voltar ao topo');
        document.body.appendChild(btn);

        const toggleVisibility = () => btn.classList.toggle('visible', window.scrollY > 300);
        window.addEventListener('scroll', toggleVisibility, { passive: true });
        btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }

    function initFormEnhancements() {
        const inputs = document.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            const validateOnBlur = Utils.debounce(() => Utils.validateField(input), 300);
            input.addEventListener('blur', validateOnBlur);
            input.addEventListener('input', () => {
                if (input.classList.contains('is-invalid')) Utils.validateField(input);
            });
        });

        document.addEventListener('submit', function(e) {
            const form = e.target;
            const submitBtn = form.querySelector('[type="submit"]');
            const isCnpjSearch = form.id === 'cnpj-search-form';

            if (submitBtn && !submitBtn.classList.contains('no-loading')) {
                const originalHtml = submitBtn.innerHTML;
                
                if (isCnpjSearch) {
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Consultando...';
                    const input = form.querySelector('#cnpj-input');
                    if (input) input.readOnly = true;
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;

                    setTimeout(function() {
                        if (!form.checkValidity()) {
                            submitBtn.classList.remove('loading');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalHtml;
                            const input = form.querySelector('#cnpj-input');
                            if (input) input.readOnly = false;
                        }
                    }, 4000);
                }
            }
        });
    }

    function initToastNotifications() {
        window.showToast = function(type, title, message, duration = 5000) {
            let container = document.querySelector('.toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'toast-container';
                document.body.appendChild(container);
            }

            const icons = {
                success: 'bi-check-circle-fill text-success',
                error: 'bi-x-circle-fill text-danger',
                warning: 'bi-exclamation-triangle-fill text-warning',
                info: 'bi-info-circle-fill text-info'
            };

            const toast = document.createElement('div');
            toast.className = 'toast toast-' + type;
            toast.innerHTML = `
                <div class="toast-icon"><i class="bi ${icons[type] || icons.info}"></i></div>
                <div class="toast-content">
                    ${title ? '<div class="toast-title">' + Utils.escapeHtml(title) + '</div>' : ''}
                    ${message ? '<div class="toast-message">' + Utils.escapeHtml(message) + '</div>' : ''}
                </div>
                <button type="button" class="toast-close" aria-label="Fechar">&times;</button>
            `;

            container.appendChild(toast);

            const dismissToast = () => {
                toast.style.animation = 'toast-out 0.3s ease forwards';
                setTimeout(() => toast.remove(), 300);
            };

            toast.querySelector('.toast-close').addEventListener('click', dismissToast);

            if (duration > 0) setTimeout(dismissToast, duration);

            return toast;
        };
    }

    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;

                const target = document.querySelector(targetId);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    target.focus();
                }
            });
        });
    }

    function initAutoHideAlerts() {
        document.querySelectorAll('.alert:not(.alert-permanent)').forEach(function(alert) {
            setTimeout(function() {
                if (alert.parentNode) {
                    alert.style.animation = 'toast-out 0.3s ease forwards';
                    setTimeout(() => alert.remove(), 300);
                }
            }, 5000);
        });
    }

    function initConfirmActions() {
        const handleConfirm = (el, e) => {
            e.preventDefault();
            const message = el.getAttribute('data-confirm') || 'Tem certeza que deseja continuar?';
            const title = el.getAttribute('data-confirm-title') || 'Confirmar';
            const btnClass = el.getAttribute('data-confirm-btn') || 'btn-danger';
            
            confirmAction({
                message,
                title,
                btnClass,
                icon: el.getAttribute('data-confirm-icon') || 'bi-question-circle'
            }).then(confirmed => {
                if (confirmed) {
                    const form = el.closest('form');
                    if (form) form.submit();
                    else if (el.tagName === 'A') window.location.href = el.href;
                    else el.click();
                }
            });
        };

        document.querySelectorAll('[data-confirm]').forEach(el => {
            el.addEventListener('click', e => handleConfirm(el, e));
        });

        document.querySelectorAll('form[data-confirm]').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const message = form.getAttribute('data-confirm') || 'Tem certeza que deseja continuar?';
                const title = form.getAttribute('data-confirm-title') || 'Confirmar';
                const btnClass = form.getAttribute('data-confirm-btn') || 'btn-danger';
                const icon = form.getAttribute('data-confirm-icon') || 'bi-question-circle';
                
                confirmAction({ message, title, btnClass, icon }).then(confirmed => {
                    if (confirmed) form.submit();
                });
            });
        });
    }

    function initMaskInputs() {
        document.querySelectorAll('input[name="cnpj"]').forEach(input => Masks.cnpj(input));
        document.querySelectorAll('input[name="phone"], input[name="contact_phone"], input[name="contact_whatsapp"], input[name="phone_number"]').forEach(input => Masks.phone(input));
    }

    function initCopyToClipboard() {
        document.querySelectorAll('[data-copy]').forEach(function(el) {
            el.addEventListener('click', function() {
                const textToCopy = this.getAttribute('data-copy');
                if (!textToCopy) return;

                const showFeedback = (text) => {
                    const originalText = el.textContent;
                    el.textContent = text;
                    setTimeout(() => el.textContent = originalText, 1500);
                };

                if (navigator.clipboard) {
                    navigator.clipboard.writeText(textToCopy)
                        .then(() => window.showToast ? showToast('success', 'Copiado!', 'Texto copiado para a área de transferência') : showFeedback('Copiado!'))
                        .catch(() => fallbackCopy(textToCopy, showFeedback));
                } else {
                    fallbackCopy(textToCopy, showFeedback);
                }
            });
        });

        function fallbackCopy(text, callback) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            callback('Copiado!');
        }
    }

    function initPasswordToggle() {
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.toggle-password, [data-toggle-password]');
            if (!btn) return;

            const targetId = btn.getAttribute('data-target') || btn.getAttribute('href')?.replace('#', '');
            const input = document.getElementById(targetId) || document.querySelector(`[name="${targetId}"]`);
            if (!input) return;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.innerHTML = `<i class="bi bi-eye-${isPassword ? 'slash' : ''}"></i>`;
        });
    }

    function initFadeInAnimations() {
        const elements = document.querySelectorAll('.fade-in-on-scroll');
        if (!elements.length) return;

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fade-in');
                        observer.unobserve(entry.target);
                    }
                });
            });
            elements.forEach(el => observer.observe(el));
        } else {
            elements.forEach(el => el.classList.add('fade-in'));
        }
    }

    function initLazyMapEmbeds() {
        const mapFrames = document.querySelectorAll('iframe[data-lazy-map][data-src]');
        if (!mapFrames.length) return;

        const loadFrame = (frame) => {
            if (!frame || frame.dataset.loaded === '1') return;
            const src = frame.getAttribute('data-src');
            if (src) {
                frame.src = src;
                frame.dataset.loaded = '1';
            }
        };

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        loadFrame(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '150px 0px' });

            mapFrames.forEach(frame => observer.observe(frame));

            setTimeout(() => mapFrames.forEach(frame => {
                if (frame.dataset.loaded !== '1') loadFrame(frame);
            }), 4000);
        } else {
            mapFrames.forEach(loadFrame);
        }
    }

    function initLazyImages() {
        if (!('IntersectionObserver' in window)) return;

        const lazyImages = document.querySelectorAll('img[data-src]');
        if (!lazyImages.length) return;

        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    img.classList.add('fade-in');
                    imageObserver.unobserve(img);
                }
            });
        });

        lazyImages.forEach(img => imageObserver.observe(img));
    }

    function initDarkMode() {
        const toggles = document.querySelectorAll('.theme-toggle-btn');
        if (toggles.length === 0) return;
        
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const savedTheme = localStorage.getItem('theme');
        const isDark = savedTheme === 'dark' || (!savedTheme && prefersDark);

        const applyTheme = (dark) => {
            document.documentElement.toggleAttribute('data-theme', dark);
            const icon = dark ? 'bi-sun-fill' : 'bi-moon-fill';
            const label = dark ? 'Ativar modo claro' : 'Ativar modo escuro';
            toggles.forEach(btn => {
                btn.innerHTML = btn.id === 'theme-toggle-menu' 
                    ? `<i class="bi ${icon} me-1"></i>Tema` 
                    : `<i class="bi ${icon}"></i>`;
                btn.setAttribute('aria-label', label);
            });
        };

        applyTheme(isDark);

        const toggleTheme = () => {
            const currentlyDark = document.documentElement.hasAttribute('data-theme');
            applyTheme(!currentlyDark);
            localStorage.setItem('theme', currentlyDark ? 'light' : 'dark');
        };

        document.addEventListener('click', (e) => {
            if (e.target.closest('.theme-toggle-btn')) toggleTheme();
        });
    }

    function initTooltips() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }

    function initMunicipalityActions() {
        document.addEventListener('click', function(e) {
            const weatherBtn = e.target.closest('.refresh-weather-btn');
            if (weatherBtn) {
                e.preventDefault();
                const ibgeCode = weatherBtn.getAttribute('data-ibge');
                if (ibgeCode) window.refreshWeather(ibgeCode, weatherBtn);
                return;
            }

            const ratesBtn = e.target.closest('.refresh-rates-btn');
            if (ratesBtn) {
                e.preventDefault();
                window.refreshExchangeRates(ratesBtn);
                return;
            }

            if (e.target.closest('.print-btn')) {
                e.preventDefault();
                window.print();
            }
        });
    }

    window.refreshWeather = async function(ibgeCode, button) {
        if (!button) return;

        const ibgeNumeric = parseInt(String(ibgeCode || ''), 10);
        if (!Number.isInteger(ibgeNumeric) || ibgeNumeric <= 0) {
            alert('Codigo IBGE invalido para atualizar o clima.');
            return;
        }
        
        const originalContent = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Atualizando...';
        
        try {
            const response = await fetch('/api/v1/weather/refresh', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ibge_code: ibgeNumeric })
            });

            const contentType = response.headers.get('content-type') || '';
            let data = null;
            if (contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                throw new Error(text ? 'Resposta invalida do servidor' : 'Sem resposta do servidor');
            }
            
            if (response.status === 429) {
                alert('Muitas solicitações. Aguarde um momento antes de tentar novamente.');
                button.innerHTML = originalContent;
                button.disabled = false;
                return;
            }
            
            if (data.success && data.data?.weather) {
                const weather = data.data.weather;
                const fetchedAt = weather.fetched_at || weather.updated_at;
                const formattedDate = Utils.formatServerDate(fetchedAt);
                
                if (document.getElementById('weather-content')) {
                    updateWeatherCompanyPage(weather, formattedDate, button);
                } else if (document.getElementById('weather-temp')) {
                    updateWeatherMunicipalityPage(weather, formattedDate);
                }
                
                button.innerHTML = originalContent;
                button.disabled = false;
            } else {
                alert(data?.error?.message || data?.message || 'Erro ao atualizar clima');
                button.innerHTML = originalContent;
                button.disabled = false;
            }
        } catch (e) {
            alert('Erro ao atualizar clima. Tente novamente em instantes.');
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    };

    function updateWeatherCompanyPage(weather, formattedDate, button) {
        const content = document.getElementById('weather-content');
        const wrapper = document.getElementById('weather-card-wrapper');
        if (!content || !wrapper) return;
        
        const ibgeCode = button.getAttribute('data-ibge');
        const temp = weather.current?.temp ?? weather.forecast?.[0]?.max_temp ?? null;
        const condition = weather.current?.condition ?? weather.forecast?.[0]?.condition ?? null;
        const source = weather.source || '';
        
        let html = '';
        if (temp !== null && temp !== undefined && temp !== '') {
            html += `<div class="display-4 fw-bold">${temp}°C</div>`;
        }
        if (condition) {
            html += `<div class="text-muted">${condition}</div>`;
        }
        if (source) {
            html += `<small class="text-muted">${source}</small>`;
        }
        
        html += `<small class="text-muted d-block mt-1" id="weather-updated">Atualizado em ${formattedDate}</small>`;
        html += `<button class="btn btn-sm btn-outline-secondary mt-2 refresh-weather-btn" data-ibge="${ibgeCode}"><i class="bi bi-arrow-clockwise"></i> Atualizar Clima</button>`;
        
        content.innerHTML = html;
        wrapper.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function updateWeatherMunicipalityPage(weather, formattedDate) {
        const temp = weather.current?.temp ?? weather.forecast?.[0]?.max_temp ?? null;
        const condition = weather.current?.condition ?? weather.forecast?.[0]?.condition ?? null;
        const minTemp = weather.forecast?.[0]?.min_temp ?? '--';
        const maxTemp = weather.forecast?.[0]?.max_temp ?? '--';
        const feelsLike = weather.current?.feels_like ?? '--';
        const source = weather.source || 'Open-Meteo';
        
        const elements = {
            temp: document.getElementById('weather-temp'),
            condition: document.getElementById('weather-condition'),
            min: document.getElementById('weather-min'),
            max: document.getElementById('weather-max'),
            feels: document.getElementById('weather-feels'),
            source: document.getElementById('weather-source'),
            updated: document.getElementById('weather-updated')
        };
        
        if (elements.temp && temp) elements.temp.textContent = temp + '°';
        if (elements.condition && condition) elements.condition.textContent = condition;
        if (elements.min) elements.min.textContent = minTemp + '°';
        if (elements.max) elements.max.textContent = maxTemp + '°';
        if (elements.feels) elements.feels.textContent = feelsLike + '°';
        if (elements.source) elements.source.textContent = 'Fonte: ' + source;
        if (elements.updated) elements.updated.textContent = 'Atualizado em ' + formattedDate;
    }

    window.refreshExchangeRates = async function(button) {
        if (!button) return;
        
        const originalContent = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Atualizando...';
        
        try {
            const response = await fetch('/api/v1/exchange-rates/refresh', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            
            const data = await response.json();
            
            if (response.status === 429) {
                alert('Muitas solicitações. Aguarde um momento antes de tentar novamente.');
                button.innerHTML = originalContent;
                button.disabled = false;
                return;
            }
            
            if (data.success) {
                const url = new URL(window.location.href);
                url.searchParams.set('rates_sync', Date.now());
                window.location.href = url.toString();
            } else {
                alert(data.error?.message || 'Erro ao atualizar câmbio');
                button.innerHTML = originalContent;
                button.disabled = false;
            }
        } catch (e) {
            alert('Erro ao atualizar câmbio');
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    };

    window.debounce = Utils.debounce;
    window.formatCNPJ = Utils.formatCNPJ;
    window.formatPhone = Utils.formatPhone;
    window.showSkeletons = Utils.showSkeleton;
    window.hideSkeletons = Utils.hideSkeleton;

})();