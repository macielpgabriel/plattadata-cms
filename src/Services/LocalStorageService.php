<?php

declare(strict_types=1);

namespace App\Services;

final class LocalStorageService
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = base_path('storage/app');
    }

    public function isEnabled(): bool
    {
        return is_dir($this->basePath) || mkdir($this->basePath, 0750, true);
    }

    public function testConnection(): array
    {
        if (!$this->isEnabled()) {
            return [
                'status' => 'error',
                'message' => 'Diretório de armazenamento não disponível.',
            ];
        }

        $freeSpace = disk_free_space($this->basePath);
        $totalSpace = disk_total_space($this->basePath);

        return [
            'status' => 'connected',
            'user' => [
                'email' => 'local',
                'displayName' => 'Sistema Local',
            ],
            'storageQuota' => [
                'limit' => $totalSpace,
                'usage' => $totalSpace - $freeSpace,
                'usageInDrive' => $totalSpace - $freeSpace,
            ],
        ];
    }

    public function uploadFile(string $filePath, string $subDir = ''): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Arquivo local não encontrado: {$filePath}");
        }

        $targetDir = $this->basePath;
        if ($subDir) {
            $targetDir = $this->basePath . '/' . $subDir;
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0750, true);
            }
        }

        $fileName = bin2hex(random_bytes(16)) . '_' . basename($filePath);
        $destPath = $targetDir . '/' . $fileName;

        if (!copy($filePath, $destPath)) {
            throw new \Exception("Erro ao salvar arquivo.");
        }

        return [
            'id' => $fileName,
            'name' => basename($filePath),
            'path' => $destPath,
            'success' => true
        ];
    }

    public function listFiles(string $subDir = '', int $limit = 50): array
    {
        $targetDir = $this->basePath;
        if ($subDir) {
            $targetDir = $this->basePath . '/' . $subDir;
        }

        if (!is_dir($targetDir)) {
            return [];
        }

        $files = array_diff(scandir($targetDir), ['.', '..']);
        $files = array_values(array_filter($files, fn($f) => is_file($targetDir . '/' . $f)));
        $files = array_slice($files, 0, $limit);

        $fileList = [];
        foreach ($files as $fileName) {
            $filePath = $targetDir . '/' . $fileName;
            $fileList[] = [
                'id' => $fileName,
                'name' => $fileName,
                'path' => $filePath,
                'createdTime' => date('c', filectime($filePath)),
                'size' => filesize($filePath),
                'mimeType' => $this->getMimeType($filePath)
            ];
        }

        return $fileList;
    }

    public function deleteFile(string $fileName, string $subDir = ''): bool
    {
        $targetDir = $this->basePath;
        if ($subDir) {
            $targetDir = $this->basePath . '/' . $subDir;
        }

        $filePath = $targetDir . '/' . basename($fileName);

        if (!is_file($filePath)) {
            throw new \Exception("Arquivo não encontrado: {$fileName}");
        }

        return unlink($filePath);
    }

    public function getFileInfo(string $fileName, string $subDir = ''): array
    {
        $targetDir = $this->basePath;
        if ($subDir) {
            $targetDir = $this->basePath . '/' . $subDir;
        }

        $filePath = $targetDir . '/' . basename($fileName);

        if (!is_file($filePath)) {
            throw new \Exception("Arquivo não encontrado: {$fileName}");
        }

        return [
            'id' => basename($fileName),
            'name' => basename($fileName),
            'path' => $filePath,
            'createdTime' => date('c', filectime($filePath)),
            'size' => filesize($filePath),
            'mimeType' => $this->getMimeType($filePath)
        ];
    }

    public function getFileContents(string $fileName, string $subDir = ''): string
    {
        $targetDir = $this->basePath;
        if ($subDir) {
            $targetDir = $this->basePath . '/' . $subDir;
        }

        $filePath = $targetDir . '/' . basename($fileName);

        if (!is_file($filePath)) {
            throw new \Exception("Arquivo não encontrado: {$fileName}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \Exception("Erro ao ler arquivo.");
        }

        return $content;
    }

    private function getMimeType(string $filePath): string
    {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}