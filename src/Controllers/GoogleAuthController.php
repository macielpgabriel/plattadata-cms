<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Logger;
use App\Core\Session;
use App\Core\View;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Oauth2;

/**
 * Controller para autenticação OAuth com Google Drive (acesso do usuário)
 */
final class GoogleAuthController
{
    private Client $googleClient;

    public function __construct()
    {
        $this->googleClient = new Client();
        $this->googleClient->setApplicationName('Plattadata CMS');
        $this->googleClient->setScopes([
            Drive::DRIVE,
            Drive::DRIVE_READONLY,
            Drive::DRIVE_METADATA
        ]);
        
        $clientId = env('GOOGLE_OAUTH_CLIENT_ID');
        $clientSecret = env('GOOGLE_OAUTH_CLIENT_SECRET');
        $redirectUri = env('GOOGLE_OAUTH_REDIRECT_URI');
        
        if ($clientId && $clientSecret && $redirectUri) {
            $this->googleClient->setClientId($clientId);
            $this->googleClient->setClientSecret($clientSecret);
            $this->googleClient->setRedirectUri($redirectUri);
        }
        
        $this->googleClient->setAccessType('offline');
        $this->googleClient->setPrompt('select_account consent');
    }

    /**
     * Verifica se OAuth está configurado
     */
    public function isConfigured(): bool
    {
        return !empty(env('GOOGLE_OAUTH_CLIENT_ID')) && 
               !empty(env('GOOGLE_OAUTH_CLIENT_SECRET'));
    }

    /**
     * Inicia o fluxo de autenticação OAuth com Google
     */
    public function login(): void
    {
        if (!$this->isConfigured()) {
            Session::flash('error', 'OAuth do Google não configurado. Adicione GOOGLE_OAUTH_CLIENT_ID e GOOGLE_OAUTH_CLIENT_SECRET no .env');
            redirect('/admin/drive-upload');
            return;
        }

        $authUrl = $this->googleClient->createAuthUrl();
        header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
        exit;
    }

    /**
     * Callback do Google após autenticação OAuth
     */
    public function callback(): void
    {
        $params = $_GET;

        if (isset($params['error'])) {
            Session::flash('error', 'Erro na autenticação com Google: ' . htmlspecialchars($params['error']));
            redirect('/admin/drive-upload');
            return;
        }

        if (!isset($params['code'])) {
            Session::flash('error', 'Código de autenticação não recebido.');
            redirect('/admin/drive-upload');
            return;
        }

        try {
            $token = $this->googleClient->fetchAccessTokenWithAuthCode($params['code']);
            
            if (!isset($token['access_token'])) {
                Session::flash('error', 'Token de acesso não recebido. Response: ' . json_encode($token));
                redirect('/admin/drive-upload');
                return;
            }
            
            $this->googleClient->setAccessToken($token);

            $_SESSION['google_oauth_token'] = $token;
            if (isset($token['refresh_token'])) {
                $_SESSION['google_oauth_refresh_token'] = $token['refresh_token'];
            }

            $drive = new Drive($this->googleClient);
            $about = $drive->about->get(['fields' => 'user']);
            $userInfo = $about->getUser();
            
            $userInfo = $about->getUser();
            Logger::info('Google OAuth: User info retrieved successfully');

            $_SESSION['google_oauth_user'] = [
                'id' => $userInfo->getPermissionId(),
                'email' => $userInfo->getEmailAddress(),
                'name' => $userInfo->getDisplayName(),
                'picture' => null,
            ];

            Session::flash('success', 'Conectado ao Google Drive com sucesso!');
            redirect('/admin/drive-upload');
        } catch (\Exception $e) {
            Session::flash('error', 'Erro ao autenticar com Google: ' . $e->getMessage());
            redirect('/admin/drive-upload');
        }
    }

    /**
     * Desconecta do Google Drive (revoga token)
     */
    public function logout(): void
    {
        if (isset($_SESSION['google_oauth_token'])) {
            try {
                $this->googleClient->setAccessToken($_SESSION['google_oauth_token']);
                $this->googleClient->revokeToken();
            } catch (\Exception $e) {
            }
        }
        
        unset($_SESSION['google_oauth_token']);
        unset($_SESSION['google_oauth_refresh_token']);
        unset($_SESSION['google_oauth_user']);
        
        Session::flash('success', 'Desconectado do Google Drive.');
        redirect('/admin/drive-upload');
    }

    /**
     * Obtém o cliente Google autenticado com token do usuário
     */
    public function getAuthenticatedClient(): ?Client
    {
        if (isset($_SESSION['google_oauth_token'])) {
            $this->googleClient->setAccessToken($_SESSION['google_oauth_token']);

            if ($this->googleClient->isAccessTokenExpired()) {
                if (isset($_SESSION['google_oauth_refresh_token'])) {
                    try {
                        $this->googleClient->fetchAccessTokenWithRefreshToken($_SESSION['google_oauth_refresh_token']);
                        $_SESSION['google_oauth_token'] = $this->googleClient->getAccessToken();
                    } catch (\Exception $e) {
                        $this->logout();
                        return null;
                    }
                } else {
                    $this->logout();
                    return null;
                }
            }

            return $this->googleClient;
        }

        return null;
    }

    /**
     * Verifica se o usuário está autenticado via OAuth
     */
    public function isAuthenticated(): bool
    {
        return isset($_SESSION['google_oauth_token']) && isset($_SESSION['google_oauth_user']);
    }

    /**
     * Obtém informações do usuário autenticado
     */
    public function getUser(): ?array
    {
        return $_SESSION['google_oauth_user'] ?? null;
    }
}