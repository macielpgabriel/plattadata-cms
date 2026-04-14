<?php

declare(strict_types=1);

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

/**
 * Serviço para operações com Google Drive usando a biblioteca oficial
 */
final class GoogleDriveService
{
    private Client $client;
    private Drive $driveService;
    private string $folderId;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setApplicationName('Plattadata CMS');
        $this->client->setScopes([Drive::DRIVE]);
        
        // Carrega credenciais do arquivo JSON
        $credentialsPath = base_path('config/google_credentials.json');
        if (file_exists($credentialsPath)) {
            $this->client->setAuthConfig($credentialsPath);
        } else {
            // Fallback para credenciais via variáveis de ambiente (para desenvolvimento)
            $clientId = env('GOOGLE_CLIENT_ID');
            $clientSecret = env('GOOGLE_CLIENT_SECRET');
            $redirectUri = env('GOOGLE_REDIRECT_URI');
            
            if ($clientId && $clientSecret && $redirectUri) {
                $this->client->setClientId($clientId);
                $this->client->setClientSecret($clientSecret);
                $this->client->setRedirectUri($redirectUri);
            }
        }
        
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');

        // Carrega o serviço do Drive
        $this->driveService = new Drive($this->client);
        
        // ID da pasta onde os arquivos serão salvos (opcional)
        $this->folderId = env('GOOGLE_DRIVE_FOLDER_ID', '');
    }

    /**
     * Verifica se o serviço está configurado corretamente
     */
    public function isEnabled(): bool
    {
        $credentialsPath = base_path('config/google_credentials.json');
        return file_exists($credentialsPath) || 
               (!empty(env('GOOGLE_CLIENT_ID')) && !empty(env('GOOGLE_CLIENT_SECRET')));
    }

    /**
     * Testa a conexão com o Google Drive
     * 
     * @return array Status da conexão
     */
    public function testConnection(): array
    {
        $credentialsPath = base_path('config/google_credentials.json');
        
        if (!file_exists($credentialsPath)) {
            $hasClientId = !empty(env('GOOGLE_CLIENT_ID'));
            $hasClientSecret = !empty(env('GOOGLE_CLIENT_SECRET'));
            
            if (!$hasClientId || !$hasClientSecret) {
                return [
                    'status' => 'disabled',
                    'message' => 'Google Drive não configurado. Credenciais não encontradas.',
                ];
            }
            
            return [
                'status' => 'misconfigured',
                'message' => 'Credenciais via OAuth2 requerem fluxo de autenticação interativo.',
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

    /**
     * Faz upload de um arquivo para o Google Drive
     * 
     * @param string $filePath Caminho local do arquivo
     * @param string $fileName Nome do arquivo no Drive
     * @param string $folderId ID da pasta onde o arquivo será salvo (opcional)
     * @return array Resultado da operação com id, name e webViewLink
     * @throws \Exception
     */
    public function uploadFile(string $filePath, string $fileName, ?string $folderId = null): array
    {
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

    /**
     * Lista arquivos em uma pasta do Google Drive
     * 
     * @param string $folderId ID da pasta (opcional, lista arquivos da raiz se null)
     * @param int $limit Número máximo de arquivos para retornar
     * @return array Lista de arquivos
     */
    public function listFiles(?string $folderId = null, int $limit = 50): array
    {
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

    /**
     * Exclui um arquivo do Google Drive
     * 
     * @param string $fileId ID do arquivo no Drive
     * @return bool True se excluído com sucesso
     */
    public function deleteFile(string $fileId): bool
    {
        try {
            $this->driveService->files->delete($fileId);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Erro ao excluir arquivo do Google Drive: " . $e->getMessage());
        }
    }

    /**
     * Obtém informações sobre um arquivo
     * 
     * @param string $fileId ID do arquivo no Drive
     * @return array Informações do arquivo
     */
    public function getFileInfo(string $fileId): array
    {
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

    /**
     * Cria uma pasta no Google Drive
     * 
     * @param string $folderName Nome da pasta
     * @param string $parentId ID da pasta pai (opcional)
     * @return string ID da pasta criada
     */
    public function createFolder(string $folderName, string $parentId = null): string
    {
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

    /**
     * Obtém o tipo MIME baseado na extensão do arquivo
     * 
     * @param string $filePath Caminho do arquivo
     * @return string Tipo MIME
     */
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
