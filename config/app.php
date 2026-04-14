<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Plattadata'),
    'domain' => env('APP_DOMAIN', 'plattadata.com'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'key' => env('APP_KEY', ''),
    'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo'),
    'session' => [
        'name' => env('SESSION_NAME', 'cms_session'),
        'secure' => filter_var(env('SESSION_SECURE', 'false'), FILTER_VALIDATE_BOOLEAN),
        'http_only' => filter_var(env('SESSION_HTTP_ONLY', 'true'), FILTER_VALIDATE_BOOLEAN),
        'same_site' => env('SESSION_SAME_SITE', 'Lax'),
    ],
    'security' => [
        'password_min_length' => (int) env('PASSWORD_MIN_LENGTH', '12'),
        'check_pwned_passwords' => filter_var(env('CHECK_PWNED_PASSWORDS', 'true'), FILTER_VALIDATE_BOOLEAN),
        'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', '5'),
        'login_lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', '15'),
        'admin_2fa_required' => filter_var(env('ADMIN_2FA_REQUIRED', 'true'), FILTER_VALIDATE_BOOLEAN),
        'otp_length' => (int) env('ADMIN_OTP_LENGTH', '6'),
        'otp_ttl_minutes' => (int) env('ADMIN_OTP_TTL_MINUTES', '10'),
        'headers' => [
            'hsts' => filter_var(env('SECURITY_HSTS', 'true'), FILTER_VALIDATE_BOOLEAN),
            'csp' => env('SECURITY_CSP', "default-src 'self'; script-src 'self' 'nonce-{nonce}' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; frame-src https://*.google.com; connect-src 'self' https:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'"),
        ],
    ],
    'rate_limit' => [
        'cnpj_search_public_per_minute' => (int) env('RL_CNPJ_PUBLIC_PER_MINUTE', '20'),
        'cnpj_search_auth_user_per_minute' => (int) env('RL_CNPJ_AUTH_USER_PER_MINUTE', '60'),
        'cnpj_search_auth_ip_per_minute' => (int) env('RL_CNPJ_AUTH_IP_PER_MINUTE', '120'),
    ],
    'lgpd' => [
        'audit' => [
            'enabled' => filter_var(env('LGPD_AUDIT_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
        ],
        'retention' => [
            'enabled' => filter_var(env('LGPD_RETENTION_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
            'days' => [
                'company_query_logs' => (int) env('RETENTION_COMPANY_QUERY_LOGS_DAYS', '180'),
                'company_source_payloads' => (int) env('RETENTION_COMPANY_SOURCE_PAYLOADS_DAYS', '180'),
                'company_snapshots' => (int) env('RETENTION_COMPANY_SNAPSHOTS_DAYS', '365'),
                'email_logs' => (int) env('RETENTION_EMAIL_LOGS_DAYS', '365'),
                'request_rate_limits' => (int) env('RETENTION_RATE_LIMITS_DAYS', '7'),
                'lgpd_audit_logs' => (int) env('RETENTION_LGPD_AUDIT_LOGS_DAYS', '365'),
            ],
        ],
    ],
    'cnpj' => [
        'provider' => env('CNPJ_PROVIDER', 'brasilapi'),
        'api_base' => env('CNPJ_API_BASE', 'https://brasilapi.com.br/api/cnpj/v1'),
        'fallback_chain' => env('CNPJ_FALLBACK_CHAIN', 'brasilapi,receitaws,cnpjws,opencnpj'),
        'timeout' => (int) env('CNPJ_HTTP_TIMEOUT', '10'),
        'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('CNPJ_ALLOWED_HOSTS', 'brasilapi.com.br,www.receitaws.com.br,api.cnpj.ws,opencnpj.org,servicodados.ibge.gov.br,viacep.com.br'))))),
        'refresh' => [
            'user_cooldown_days' => (int) env('CNPJ_REFRESH_USER_COOLDOWN_DAYS', '15'),
            'rate_limit_per_hour' => (int) env('CNPJ_REFRESH_RATE_LIMIT_PER_HOUR', '10'),
            'auto_refresh_min_days' => (int) env('CNPJ_AUTO_REFRESH_MIN_DAYS', '7'),
            'auto_refresh_lock_seconds' => (int) env('CNPJ_AUTO_REFRESH_LOCK_SECONDS', '21600'),
        ],
    ],
    'api' => [
        'portal_transparencia_token' => env('PORTAL_TRANSPARENCIA_TOKEN', ''),
    ],
    'ibge' => [
        'base_url' => env('IBGE_API_BASE', 'https://servicodados.ibge.gov.br/api/v1'),
        'timeout' => (int) env('IBGE_HTTP_TIMEOUT', '5'),
    ],
    'cptec' => [
        'base_url' => env('CPTEC_API_BASE', 'https://brasilapi.com.br/api/cptec/v1'),
        'timeout' => (int) env('CPTEC_HTTP_TIMEOUT', '5'),
    ],
    'ddd' => [
        'base_url' => env('DDD_API_BASE', 'https://brasilapi.com.br/api/ddd/v1'),
        'timeout' => (int) env('DDD_HTTP_TIMEOUT', '3'),
    ],
    'simples_nacional' => [
        'base_url' => env('SIMPLES_API_BASE', 'https://brasilapi.com.br/api/cnpj/v1'),
        'timeout' => (int) env('SIMPLES_HTTP_TIMEOUT', '5'),
    ],
    'bcb' => [
        'base_url' => env('BCB_API_BASE', 'https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata'),
        'timeout' => (int) env('BCB_HTTP_TIMEOUT', '5'),
    ],
    'address' => [
        'timeout' => (int) env('ADDRESS_HTTP_TIMEOUT', '5'),
    ],
    'compliance' => [
        'base_url' => env('COMPLIANCE_API_BASE', 'https://api.portaldatransparencia.gov.br/api-de-dados'),
        'timeout' => (int) env('COMPLIANCE_HTTP_TIMEOUT', '10'),
        'token' => env('PORTAL_TRANSPARENCIA_TOKEN', ''),
    ],
    'market_intelligence' => [
        'timeout' => (int) env('MARKET_INTEL_HTTP_TIMEOUT', '5'),
    ],
    'stock_market' => [
        'brapi_url' => env('BRAPI_URL', 'https://brapi.dev/api'),
        'brapi_token' => env('BRAPI_TOKEN', null),
        'timeout' => (int) env('STOCK_MARKET_HTTP_TIMEOUT', '10'),
    ],
];
