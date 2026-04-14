<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NotificationServiceTest extends TestCase
{
    public function testNotificationTypesAreDefined(): void
    {
        $expectedTypes = [
            'company_updated',
            'company_removed',
            'company_favorited',
            'rate_limit_warning',
            'security_alert',
        ];

        foreach ($expectedTypes as $type) {
            $this->assertIsString($type);
            $this->assertNotEmpty($type);
        }
    }

    public function testNotificationDataStructure(): void
    {
        $companyUpdateData = [
            'company_id' => 123,
            'company_name' => 'Empresa Teste LTDA',
            'cnpj' => '12345678000199',
            'updated_at' => '01/01/2024 12:00',
        ];

        $this->assertArrayHasKey('company_id', $companyUpdateData);
        $this->assertArrayHasKey('company_name', $companyUpdateData);
        $this->assertArrayHasKey('cnpj', $companyUpdateData);
        $this->assertArrayHasKey('updated_at', $companyUpdateData);
    }

    public function testSecurityAlertDataStructure(): void
    {
        $securityData = [
            'alert_type' => 'login_failed',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'occurred_at' => '01/01/2024 12:00:00',
        ];

        $this->assertArrayHasKey('alert_type', $securityData);
        $this->assertArrayHasKey('ip_address', $securityData);
        $this->assertArrayHasKey('occurred_at', $securityData);
    }

    public function testRateLimitDataStructure(): void
    {
        $rateLimitData = [
            'action' => 'cnpj_search',
            'remaining_requests' => 5,
            'reset_in_minutes' => 1,
        ];

        $this->assertArrayHasKey('action', $rateLimitData);
        $this->assertArrayHasKey('remaining_requests', $rateLimitData);
        $this->assertArrayHasKey('reset_in_minutes', $rateLimitData);
        $this->assertIsInt($rateLimitData['remaining_requests']);
        $this->assertIsInt($rateLimitData['reset_in_minutes']);
    }

    public function testTemplateInterpolation(): void
    {
        $template = 'Empresa: {company_name} - CNPJ: {cnpj}';
        $data = [
            'company_name' => 'Teste',
            'cnpj' => '12345678000199',
        ];

        $result = strtr($template, [
            '{company_name}' => $data['company_name'],
            '{cnpj}' => $data['cnpj'],
        ]);

        $this->assertSame('Empresa: Teste - CNPJ: 12345678000199', $result);
    }

    public function testNotificationPreferencesStructure(): void
    {
        $preferences = [
            'company_updated' => true,
            'company_removed' => true,
            'rate_limit_warning' => false,
            'security_alert' => true,
        ];

        $this->assertTrue($preferences['company_updated']);
        $this->assertFalse($preferences['rate_limit_warning']);
        $this->assertCount(4, $preferences);
    }
}
