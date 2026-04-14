<?php

declare(strict_types=1);

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use App\Controllers\GoogleAuthController;

final class GoogleDriveServiceOAuth
{
    private ?Client $client = null;
    private ?Drive $driveService = null;
    private string $folderId;

    public function __construct()
    {
        $this->folderId = env('GOOGLE_DRIVE_FOLDER_ID', '');
    }

    private function initialize(): bool
    {
        $authController = new GoogleAuthController();
        $this->client = $authController->getAuthenticatedClient();
        
        if ($this->client === null) {
            return false;
        }

        $this->driveService = new Drive($this->client);
        return true;
    }

    public function isAuthenticated(): bool
    {
        $authController = new GoogleAuthController();
        return $authController->isAuthenticated();
    }

    public function getUser(): ?array
    {
        $authController = new GoogleAuthController();
        return $authController->getUser();
    }

    public function isEnabled(): bool
    {
        $authController = new GoogleAuthController();
        return $authController->isConfigured();
    }

    public function testConnection(): array
    {
        if (!$this->isAuthenticated()) {
            return [
                'status' => 'disconnected',
                'message' => 'Clique em "Conectar ao Google Drive" para autorizar o acesso.',
            ];
        }

        if (!$this->initialize()) {
            return [
                'status' => 'error',
                'message' => 'Falha ao inicializar cliente Google.',
            ];
        }

        try {
            $about = $this->driveService->about->get([
                'fields' => 'user,storageQuota',
            ]);
            
            return [
                'status' => 'connected',
                'user' => [
                    'email' => $about->getUser()?->getEmailAddress(),
                    'displayName' => $about->getUser()?->getDisplayName(),
                ],
                'storageQuota' => [
                    'limit' => $about->getStorageQuota()?->getLimit(),
                    'usage' => $about->getStorageQuota()?->getUsage(),
                    'usageInDrive' => $about->getStorageQuota()?->getUsageInDrive(),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function uploadFile(string $filePath, string $fileName, ?string $folderId = null): array
    {
        if (!$this->initialize()) {
            throw new \Exception('Usuário não autenticado no Google Drive.');
        }

        if (!file_exists($filePath)) {
            throw new \Exception("Arquivo local não encontrado: {$filePath}");
        }

        $fileMetadata = new DriveFile([
            'name' => $fileName,
        ]);

        $targetFolderId = $folderId ?? $this->folderId;
        if ($targetFolderId !== '') {
            $fileMetadata->setParents([$targetFolderId]);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \Exception("Não foi possível ler o arquivo: {$filePath}");
        }

        try {
            $file = $this->driveService->files->create(
                $fileMetadata,
                [
                    'data' => $content,
                    'mimeType' => $this->getMimeType($filePath),
                    'uploadType' => 'multipart',
                    'fields' => 'id,name,webViewLink,webContentLink'
                ]
            );

            return [
                'id' => $file->id,
                'name' => $file->name,
                'webViewLink' => $file->webViewLink,
                'webContentLink' => $file->webContentLink,
                'success' => true
            ];
        } catch (\Exception $e) {
            throw new \Exception("Erro ao fazer upload para Google Drive: " . $e->getMessage());
        }
    }

    public function downloadFile(string $fileId): string
    {
        if (!$this->initialize()) {
            throw new \Exception('Usuário não autenticado no Google Drive.');
        }

        try {
            $response = $this->driveService->files->get($fileId, [
                'alt' => 'media'
            ]);
            
            return (string) $response;
        } catch (\Exception $e) {
            throw new \Exception("Erro ao baixar arquivo do Google Drive: " . $e->getMessage());
        }
    }

    public function getFileMetadata(string $fileId): array
    {
        if (!$this->initialize()) {
            throw new \Exception('Usuário não autenticado no Google Drive.');
        }

        try {
            $file = $this->driveService->files->get($fileId, [
                'fields' => 'id,name,webViewLink,webContentLink,createdTime,size,mimeType'
            ]);

            return [
                'id' => $file->id,
                'name' => $file->name,
                'webViewLink' => $file->webViewLink,
                'webContentLink' => $file->webContentLink,
                'createdTime' => $file->createdTime,
                'size' => $file->size,
                'mimeType' => $file->mimeType
            ];
        } catch (\Exception $e) {
            throw new \Exception("Erro ao obter informações do arquivo: " . $e->getMessage());
        }
    }

    public function deleteFile(string $fileId): bool
    {
        if (!$this->initialize()) {
            throw new \Exception('Usuário não autenticado no Google Drive.');
        }

        try {
            $this->driveService->files->delete($fileId);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Erro ao excluir arquivo do Google Drive: " . $e->getMessage());
        }
    }

    public function listFiles(?string $folderId = null, int $limit = 50): array
    {
        if (!$this->initialize()) {
            throw new \Exception('Usuário não autenticado no Google Drive.');
        }

        $query = [];
        $query[] = "trashed = false";

        $targetFolderId = $folderId ?? $this->folderId;
        if ($targetFolderId !== '' && preg_match('/^[a-zA-Z0-9_-]+$/', $targetFolderId)) {
            $query[] = "'{$targetFolderId}' in parents";
        }

        $optParams = [
            'q' => implode(' and ', $query),
            'pageSize' => $limit,
            'fields' => 'files(id,name,webViewLink,webContentLink,createdTime,size,mimeType)',
            'orderBy' => 'createdTime desc'
        ];

        try {
            $results = $this->driveService->files->listFiles($optParams);
            $files = $results->getFiles();

            $fileList = [];
            foreach ($files as $file) {
                $fileList[] = [
                    'id' => $file->id,
                    'name' => $file->name,
                    'webViewLink' => $file->webViewLink,
                    'webContentLink' => $file->webContentLink,
                    'createdTime' => $file->createdTime,
                    'size' => $file->size,
                    'mimeType' => $file->mimeType
                ];
            }

            return $fileList;
        } catch (\Exception $e) {
            throw new \Exception("Erro ao listar arquivos do Google Drive: " . $e->getMessage());
        }
    }

    public function createFolder(string $folderName, string $parentId = null): string
    {
        if (!$this->initialize()) {
            throw new \Exception('Usuário não autenticado no Google Drive.');
        }

        $fileMetadata = new DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        if ($parentId !== null && $parentId !== '') {
            $fileMetadata->setParents([$parentId]);
        }

        try {
            $folder = $this->driveService->files->create($fileMetadata, [
                'fields' => 'id'
            ]);

            return $folder->id;
        } catch (\Exception $e) {
            throw new \Exception("Erro ao criar pasta no Google Drive: " . $e->getMessage());
        }
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
