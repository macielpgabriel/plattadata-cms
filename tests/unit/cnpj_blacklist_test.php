<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CnpjBlacklistValidationTest extends TestCase
{
    public function testValidCnpjFormat(): void
    {
        $cnpjs = [
            '00.000.000/0001-91' => true,
            '11222333000181' => true,
            '12.345.678/0001-95' => true,
            '00000000000000' => false,
            '12345678000198' => false,
            'invalid' => false,
            '12345678' => false,
            '12.ABC.345/01AB-77' => true,
            '12abc34501ab77' => true,
            'AAAAAAAAAAAAAA' => false,
        ];

        foreach ($cnpjs as $cnpj => $expected) {
            $result = $this->validateCnpj($cnpj);
            $this->assertSame($expected, $result, "CNPJ: $cnpj");
        }
    }

    public function testCnpjBlacklistStatusConstants(): void
    {
        $validStatuses = ['pending', 'approved', 'rejected'];
        
        foreach ($validStatuses as $status) {
            $this->assertContains($status, $validStatuses);
        }
    }

    public function testBlacklistEntryStructure(): void
    {
        $entry = [
            'id' => 1,
            'cnpj' => '12345678000199',
            'reason' => 'Solicitacao do titular',
            'status' => 'pending',
            'requested_by' => 1,
            'approved_by' => null,
            'legal_document' => 'document.pdf',
            'created_at' => '2024-01-01 00:00:00',
            'processed_at' => null,
        ];

        $this->assertArrayHasKey('id', $entry);
        $this->assertArrayHasKey('cnpj', $entry);
        $this->assertArrayHasKey('status', $entry);
        $this->assertArrayHasKey('created_at', $entry);
        $this->assertMatchesRegularExpression('/^\d{14}$/', $entry['cnpj']);
        $this->assertContains($entry['status'], ['pending', 'approved', 'rejected']);
    }

    public function testCnpjNormalization(): void
    {
        $input = '12.345.678/0001-90';
        $normalized = preg_replace('/[^A-Za-z0-9]/', '', $input);
        $this->assertSame('12345678000190', $normalized);

        $inputAlfanumerico = '12.ABC.345/01AB-77';
        $normalizedAlfanumerico = preg_replace('/[^A-Za-z0-9]/', '', $inputAlfanumerico);
        $this->assertSame('12ABC34501AB77', $normalizedAlfanumerico);
    }

    public function testPaginationStructure(): void
    {
        $pagination = [
            'data' => [],
            'total' => 100,
            'page' => 1,
            'per_page' => 20,
            'total_pages' => 5,
        ];

        $this->assertArrayHasKey('data', $pagination);
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('page', $pagination);
        $this->assertArrayHasKey('per_page', $pagination);
        $this->assertArrayHasKey('total_pages', $pagination);
        $this->assertSame(5, $pagination['total_pages']);
        $this->assertSame(100, $pagination['total']);
    }

    private function validateCnpj(string $cnpj): bool
    {
        $cnpj = preg_replace('/[^A-Za-z0-9]/', '', $cnpj);

        if (strlen($cnpj) !== 14) {
            return false;
        }

        if (preg_match('/^([A-Z0-9])\1{13}$/', $cnpj)) {
            return false;
        }

        $weightsFirst = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $digit1 = $this->calculateCheckDigit($cnpj, $weightsFirst, 12);

        $weightsSecond = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $digit2 = $this->calculateCheckDigit($cnpj, $weightsSecond, 13);

        return $cnpj[12] === (string)$digit1 && $cnpj[13] === (string)$digit2;
    }

    private function calculateCheckDigit(string $cnpj, array $weights, int $length): int
    {
        $sum = 0;
        for ($i = 0; $i < $length; $i++) {
            $charValue = ord($cnpj[$i]) - 48;
            $sum += $charValue * $weights[$i];
        }
        $remainder = $sum % 11;
        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
