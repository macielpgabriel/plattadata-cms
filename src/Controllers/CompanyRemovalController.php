<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Repositories\CompanyRepository;
use App\Services\LocalStorageService;
use App\Services\MailService;
use App\Services\ValidationService;
use App\Services\AuditLogService;
use App\Services\RateLimiterService;
use Exception;

final class CompanyRemovalController
{
    private CompanyRepository $repository;
    private ValidationService $validator;

    public function __construct()
    {
        $this->repository = new CompanyRepository();
        $this->validator = new ValidationService();
    }

    public function showRequestForm(array $params): void
    {
        $cnpj = (string) ($params['cnpj'] ?? '');
        $company = $this->repository->findByCnpj($cnpj);
        if (!$company) {
            http_response_code(404);
            echo 'Empresa não encontrada.';
            return;
        }

        view('removal/request', [
            'company' => $company,
            'title' => 'Solicitar Remoção de Empresa',
        ]);
    }

    public function submitRequest(array $params): void
    {

        $cnpj = (string) ($params['cnpj'] ?? '');
        $company = $this->repository->findByCnpj($cnpj);
        if (!$company) {
            http_response_code(404);
            return;
        }

        $requesterName = $this->validator->sanitizeName($_POST['name'] ?? '');
        $requesterEmail = $this->validator->sanitizeEmail($_POST['email'] ?? '');

        if (!$this->validator->name($requesterName)) {
            Session::flash('error', 'Nome invalido. Use apenas letras e espaços.');
            redirect("/empresas/$cnpj/remover");
        }

        if (!$this->validator->email($requesterEmail)) {
            Session::flash('error', 'E-mail invalido.');
            redirect("/empresas/$cnpj/remover");
        }

        $verificationType = 'document';
        $verificationCode = null;

        // Se a empresa tem e-mail no registro, usamos verificação por e-mail
        if (!empty($company['email'])) {
            $verificationType = 'email';
            $verificationCode = (string) random_int(100000, 999999);
        }

        $db = Database::connection();
        $stmt = $db->prepare('INSERT INTO company_removal_requests 
            (company_id, cnpj, requester_name, requester_email, verification_type, verification_code, status) 
            VALUES (:company_id, :cnpj, :name, :email, :type, :code, :status)');

        $stmt->execute([
            'company_id' => $company['id'],
            'cnpj' => $cnpj,
            'name' => $requesterName,
            'email' => $requesterEmail,
            'type' => $verificationType,
            'code' => $verificationCode,
            'status' => 'pending',
        ]);

        $requestId = $db->lastInsertId();

if ($verificationType === 'email') {
            $mailService = new MailService();
            $subject = "Codigo de verificacao para remocao de empresa - " . $company['legal_name'];
            $template = file_get_contents(base_path('templates/emails/removal_verify.html'));
            $template = str_replace('DATA', date('d/m/Y H:i'), $template);
            $template = str_replace('EMPRESA', $company['legal_name'], $template);
            $body = str_replace('CODIGO', $verificationCode, $template);
            
            $targetEmail = $company['email'] ?? $requesterEmail;
            error_log("Sending removal verification to: $targetEmail for CNPJ: $cnpj");
            $mailResult = $mailService->send($targetEmail, $subject, $body);
            error_log("Mail result: " . ($mailResult ? 'success' : 'failed'));
            
            redirect("/empresas/remover/verificar?id=$requestId");
        } else {
            // Caso não tenha e-mail, redireciona para upload de documento
            redirect("/empresas/remover/documento?id=$requestId");
        }
    }

    public function showDocumentForm(): void
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            redirect('/');
        }

        view('removal/document', [
            'request_id' => $id,
            'title' => 'Anexar Documentação',
        ]);
    }

    public function uploadDocument(): void
    {

        $id = $_POST['request_id'] ?? null;
        $file = $_FILES['document'] ?? null;

        if (!$id || !$file || $file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Por favor, selecione um arquivo válido.');
            redirect("/empresas/remover/documento?id=$id");
        }

        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Verificar path traversal
        $originalName = $file['name'];
        if (strpos($originalName, '..') !== false || strpos($originalName, '/') !== false || strpos($originalName, '\\') !== false) {
            Session::flash('error', 'Nome de arquivo inválido.');
            redirect("/empresas/remover/documento?id=$id");
        }

        if (!in_array($fileExtension, $allowedExtensions)) {
            Session::flash('error', 'Formato de arquivo não permitido. Use PDF, JPG ou PNG.');
            redirect("/empresas/remover/documento?id=$id");
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowedMimes, true)) {
            Session::flash('error', 'Tipo de arquivo não reconhecido. Use PDF, JPG ou PNG.');
            redirect("/empresas/remover/documento?id=$id");
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            Session::flash('error', 'O arquivo é muito grande. O limite é 5MB.');
            redirect("/empresas/remover/documento?id=$id");
        }
        
        // Verificar bytes mágicos (arquivo real)
        $handle = fopen($file['tmp_name'], 'rb');
        if ($handle) {
            $header = fread($handle, 8);
            fclose($handle);
            
            $pdfMagic = "%PDF";
            $jpgMagic1 = "\xFF\xD8\xFF";
            $jpgMagic2 = "\x89PNG";
            
            $isValidMagic = (
                str_starts_with($header, $pdfMagic) ||
                str_starts_with($header, $jpgMagic1) ||
                str_starts_with($header, $jpgMagic2)
            );
            
            if (!$isValidMagic) {
                Session::flash('error', 'Arquivo corrupto ou inválido.');
                redirect("/empresas/remover/documento?id=$id");
            }
        }

        $db = Database::connection();

        $storage = new LocalStorageService();
        $result = $storage->uploadFile($file['tmp_name'], 'removals');

        if ($result && isset($result['id'])) {
            $stmt = $db->prepare('UPDATE company_removal_requests SET document_path = :path, status = "pending" WHERE id = :id');
            $stmt->execute([
                'path' => $result['id'],
                'id' => $id
            ]);

            Session::flash('success', 'Documento enviado com sucesso. Nossa equipe analisará sua solicitação.');
            redirect('/');
        } else {
            Session::flash('error', 'Erro ao salvar o documento. Tente novamente.');
            redirect("/empresas/remover/documento?id=$id");
        }
    }

    public function showVerifyForm(): void
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            redirect('/');
        }

        view('removal/verify', [
            'request_id' => $id,
            'title' => 'Verificar Solicitação',
        ]);
    }

    public function verifyCode(): void
    {

        $id = $_POST['request_id'] ?? null;
        $code = $_POST['code'] ?? null;

        if (!$id || !$code) {
            redirect('/');
        }

        // Rate limit: 10 tentativas por minuto
        if (!RateLimiterService::check('verification_code', $id, 10, 60)) {
            Session::flash('error', 'Muitas tentativas. Tente novamente em 1 minuto.');
            redirect("/empresas/remover/verificar?id=$id");
            return;
        }

        // Bloqueio temporario apos 5 erros (15 minutos)
        $lockoutKey = "removal_lockout_$id";
        $lockout = Cache::get($lockoutKey);
        if ($lockout && $lockout > time()) {
            $remaining = ceil(($lockout - time()) / 60);
            Session::flash('error', "Conta temporariamente bloqueada. Tente novamente em $remaining minutos.");
            redirect("/empresas/remover/verificar?id=$id");
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM company_removal_requests WHERE id = :id AND verification_code = :code AND status = "pending"');
        $stmt->execute(['id' => $id, 'code' => $code]);
        $request = $stmt->fetch();

        if ($request) {
            // Limpa contagem de erros
            Cache::forget("removal_errors_$id");
            
            $update = $db->prepare('UPDATE company_removal_requests SET status = "verified", verified_at = NOW() WHERE id = :id');
            $update->execute(['id' => $id]);
            Session::flash('success', 'E-mail verificado com sucesso. Sua solicitação será analisada por um administrador.');
            redirect('/');
        } else {
            // Incrementa contador de erros
            $errors = (int) (Cache::get("removal_errors_$id") ?? 0) + 1;
            Cache::set("removal_errors_$id", $errors, 300); // 5 minutos
            
            // Bloqueio temporario apos 5 erros
            if ($errors >= 5) {
                Cache::set($lockoutKey, time() + 900, 900); // 15 minutos
                Cache::forget("removal_errors_$id");
                Session::flash('error', 'Muitas tentativas incorretas. Bloqueado por 15 minutos.');
            } else {
                Session::flash('error', "Código inválido. Você tem " . (5 - $errors) . " tentativas restantes.");
            }
            redirect("/empresas/remover/verificar?id=$id");
        }
    }

    public function adminList(): void
    {
        $db = Database::connection();
        $stmt = $db->query('SELECT r.id, r.company_id, r.cnpj, r.requester_name, r.requester_email, r.verification_type, r.document_path, r.status, r.created_at, c.legal_name FROM company_removal_requests r 
            JOIN companies c ON r.company_id = c.id 
            ORDER BY r.created_at DESC');
        $requests = $stmt->fetchAll();

        view('admin/removals/index', [
            'requests' => $requests,
            'title' => 'Gerenciar Remoções',
        ]);
    }

public function downloadDocument(array $params): void
    {
        if (!Auth::can(['admin', 'moderator'])) {
            http_response_code(403);
            echo 'Acesso negado.';
            return;
        }

        $fileIdentifier = basename($params['file'] ?? '');

        $storage = new LocalStorageService();
        $fileData = $storage->getFileContents($fileIdentifier, 'removals');
        $meta = $storage->getFileInfo($fileIdentifier, 'removals');

        header('Content-Type: ' . ($meta['mimeType'] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . ($meta['name'] ?? $fileIdentifier) . '"');
        header('Content-Length: ' . strlen($fileData));
        header('Cache-Control: no-store, private');
        echo $fileData;
        exit;
    }

    public function approve(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $user = Auth::user();
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM company_removal_requests WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $request = $stmt->fetch();

        if (!$request) {
            redirect('/admin/remocoes');
        }

        $db->beginTransaction();
        try {
            // Marca empresa como oculta
            $hide = $db->prepare('UPDATE companies SET is_hidden = 1 WHERE id = :id');
            $hide->execute(['id' => $request['company_id']]);

            // Atualiza solicitação
            $update = $db->prepare('UPDATE company_removal_requests SET status = "approved", resolved_at = NOW(), resolved_by = :user WHERE id = :id');
            $update->execute(['id' => $id, 'user' => $user['id']]);

            $db->commit();

            AuditLogService::logUpdate((int) $user['id'], 'company', (int) $request['company_id'], ['is_hidden' => 0], ['is_hidden' => 1]);

            Session::flash('success', 'Empresa removida com sucesso.');
        } catch (Exception $e) {
            $db->rollBack();
            Session::flash('error', 'Erro ao processar aprovação.');
        }

        redirect('/admin/remocoes');
    }

    public function restore(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $user = Auth::user();
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM company_removal_requests WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $request = $stmt->fetch();

        if (!$request) {
            redirect('/admin/remocoes');
        }

        $db->beginTransaction();
        try {
            $show = $db->prepare('UPDATE companies SET is_hidden = 0 WHERE id = :id');
            $show->execute(['id' => $request['company_id']]);

            $update = $db->prepare('UPDATE company_removal_requests SET status = "cancelled", admin_notes = "Remocao cancelada pelo administrador", resolved_at = NOW(), resolved_by = :user WHERE id = :id');
            $update->execute(['id' => $id, 'user' => $user['id']]);

            $db->commit();

            AuditLogService::logUpdate((int) $user['id'], 'company', (int) $request['company_id'], ['is_hidden' => 1], ['is_hidden' => 0]);

            Session::flash('success', 'Empresa restaurada e solicitacao cancelada com sucesso.');
        } catch (Exception $e) {
            $db->rollBack();
            Session::flash('error', 'Erro ao processar restauracao.');
        }

        redirect('/admin/remocoes');
    }

    public function reject(array $params): void
    {
        if (!\App\Core\Csrf::validate($_POST['_token'] ?? '')) {
            Session::flash('error', 'Token CSRF invalido.');
            redirect('/admin/remocoes');
        }
        $id = (int) ($params['id'] ?? 0);
        $user = Auth::user();
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM company_removal_requests WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $request = $stmt->fetch();

        if (!$request) {
            redirect('/admin/remocoes');
        }

        try {
            // Apenas atualiza o status para rejeitado
            $update = $db->prepare('UPDATE company_removal_requests SET status = "rejected", resolved_at = NOW(), resolved_by = :user WHERE id = :id');
            $update->execute(['id' => $id, 'user' => $user['id']]);

            Session::flash('success', 'Solicitação recusada com sucesso.');
        } catch (Exception $e) {
            Session::flash('error', 'Erro ao processar recusa.');
        }

        redirect('/admin/remocoes');
    }
}
