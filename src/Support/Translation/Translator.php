<?php

declare(strict_types=1);

namespace App\Support\Translation;

final class Translator
{
    private static ?Translator $instance = null;
    private string $locale;
    private array $translations = [];

    public const DEFAULT_LOCALE = 'pt_BR';
    public const SUPPORTED_LOCALES = ['pt_BR', 'en_US', 'es_ES'];

    private function __construct(string $locale = self::DEFAULT_LOCALE)
    {
        $this->locale = $this->validateLocale($locale);
        $this->loadTranslations();
    }

    public static function getInstance(?string $locale = null): self
    {
        if (self::$instance === null) {
            $locale = $locale ?? self::getLocaleFromSession() ?? self::getBrowserLocale() ?? self::DEFAULT_LOCALE;
            self::$instance = new self($locale);
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $this->validateLocale($locale);
        $this->loadTranslations();
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function t(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;
        $this->loadLocaleIfNeeded($locale);

        $translation = $this->getTranslation($key, $locale);

        if ($translation === null && $locale !== self::DEFAULT_LOCALE) {
            $translation = $this->getTranslation($key, self::DEFAULT_LOCALE);
        }

        if ($translation === null) {
            return $key;
        }

        return $this->replaceParameters($translation, $replace);
    }

    public function __invoke(string $key, array $replace = []): string
    {
        return $this->t($key, $replace);
    }

    public function getAvailableLocales(): array
    {
        return self::SUPPORTED_LOCALES;
    }

    public function getLocaleName(?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;
        return match ($locale) {
            'pt_BR' => 'Português (Brasil)',
            'en_US' => 'English (US)',
            'es_ES' => 'Español',
            default => $locale,
        };
    }

    private function validateLocale(string $locale): string
    {
        if (in_array($locale, self::SUPPORTED_LOCALES, true)) {
            return $locale;
        }

        $short = substr($locale, 0, 2);
        $mapping = [
            'pt' => 'pt_BR',
            'en' => 'en_US',
            'es' => 'es_ES',
        ];

        return $mapping[$short] ?? self::DEFAULT_LOCALE;
    }

    private function loadTranslations(): void
    {
        $this->loadLocaleIfNeeded($this->locale);
        $this->loadLocaleIfNeeded(self::DEFAULT_LOCALE);
    }

    private function loadLocaleIfNeeded(string $locale): void
    {
        if (isset($this->translations[$locale])) {
            return;
        }

        $file = base_path("resources/lang/{$locale}/messages.php");

        if (is_file($file)) {
            $this->translations[$locale] = require $file;
        } else {
            $this->translations[$locale] = [];
        }
    }

    private function getTranslation(string $key, string $locale): ?string
    {
        if (!isset($this->translations[$locale])) {
            return null;
        }

        $keys = explode('.', $key);
        $value = $this->translations[$locale];

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }

        return is_string($value) ? $value : null;
    }

    private function replaceParameters(string $translation, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $translation = str_replace(':' . $key, (string) $value, $translation);
        }
        return $translation;
    }

    private static function getLocaleFromSession(): ?string
    {
        return $_SESSION['locale'] ?? null;
    }

    private static function getBrowserLocale(): ?string
    {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($acceptLanguage === '') {
            return null;
        }

        preg_match_all('/([a-z]{1,8}(?:-[a-z]{1,8})?)\s*(?:;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $acceptLanguage, $matches);

        if (empty($matches[1])) {
            return null;
        }

        $languages = array_combine($matches[1], $matches[2]);
        foreach ($languages as $lang => $q) {
            $languages[$lang] = $q === '' ? 1.0 : (float) $q;
        }

        arsort($languages);
        $top = array_key_first($languages);

        $short = substr($top, 0, 2);
        $mapping = [
            'pt' => 'pt_BR',
            'en' => 'en_US',
            'es' => 'es_ES',
        ];

        return $mapping[$short] ?? null;
    }
}

function __(string $key, array $replace = []): string
{
    return Translator::getInstance()->t($key, $replace);
}

function set_locale(string $locale): void
{
    $_SESSION['locale'] = $locale;
    Translator::reset();
}

function get_locale(): string
{
    return Translator::getInstance()->getLocale();
}
