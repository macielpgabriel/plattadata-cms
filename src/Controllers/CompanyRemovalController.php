<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Repositories\CompanyRepository;
use App\Services\GoogleDriveService;
use App\Services\GoogleDriveServiceOAuth;
use App\Services\MailService;
use App\Services\ValidationService;
use App\Services\AuditLogService;
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

        $db = Database::connection();

        $driveServiceOAuth = new GoogleDriveServiceOAuth();
        $driveService = new GoogleDriveService();

        if ($driveServiceOAuth->isAuthenticated()) {
            $fileName = bin2hex(random_bytes(16)) . '.' . $fileExtension;
            $result = $driveServiceOAuth->uploadFile($file['tmp_name'], $fileName);

            if ($result && isset($result['id'])) {
                $stmt = $db->prepare('UPDATE company_removal_requests SET document_path = :path, status = "pending" WHERE id = :id');
                $stmt->execute([
                    'path' => 'gdrive:' . $result['id'],
                    'id' => $id
                ]);

                Session::flash('success', 'Documento enviado com sucesso. Nossa equipe analisará sua solicitação.');
                redirect('/');
            } else {
                Session::flash('error', 'Erro ao enviar o documento para o Google Drive. Tente novamente.');
                redirect("/empresas/remover/documento?id=$id");
            }
        } elseif ($driveService->isEnabled()) {
            $fileName = bin2hex(random_bytes(16)) . '.' . $fileExtension;
            $uploaded = $driveService->uploadFile($file['tmp_name'], $fileName);
            $fileId = (string) ($uploaded['id'] ?? '');

            if ($fileId) {
                $stmt = $db->prepare('UPDATE company_removal_requests SET document_path = :path, status = "pending" WHERE id = :id');
                $stmt->execute([
                    'path' => 'gdrive:' . $fileId,
                    'id' => $id
                ]);

                Session::flash('success', 'Documento enviado com sucesso. Nossa equipe analisará sua solicitação.');
                redirect('/');
            } else {
                Session::flash('error', 'Erro ao enviar o documento para o Google Drive. Tente novamente.');
                redirect("/empresas/remover/documento?id=$id");
            }
        } else {
            $uploadDir = base_path('storage/removals/');

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0750, true);
            }

            $fileName = bin2hex(random_bytes(16)) . '.' . $fileExtension;
            $destPath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $stmt = $db->prepare('UPDATE company_removal_requests SET document_path = :path, status = "pending" WHERE id = :id');
                $stmt->execute([
                    'path' => $fileName,
                    'id' => $id
                ]);

                Session::flash('success', 'Documento enviado com sucesso. Nossa equipe analisará sua solicitação.');
                redirect('/');
            } else {
                Session::flash('error', 'Erro ao salvar o arquivo. Verifique as permissões da pasta.');
                redirect("/empresas/remover/documento?id=$id");
            }
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

        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM company_removal_requests WHERE id = :id AND verification_code = :code AND status = "pending"');
        $stmt->execute(['id' => $id, 'code' => $code]);
        $request = $stmt->fetch();

        if ($request) {
            $update = $db->prepare('UPDATE company_removal_requests SET status = "verified", verified_at = NOW() WHERE id = :id');
            $update->execute(['id' => $id]);
            Session::flash('success', 'E-mail verificado com sucesso. Sua solicitação será analisada por um administrador.');
            redirect('/');
        } else {
            Session::flash('error', 'Código inválido ou solicitação não encontrada.');
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

        if (str_starts_with($fileIdentifier, 'gdrive:')) {
            $driveServiceOAuth = new GoogleDriveServiceOAuth();
            $fileId = substr($fileIdentifier, 7);

            if (!$driveServiceOAuth->isAuthenticated()) {
                http_response_code(401);
                echo 'Google Drive não conectado. Faça login na área admin primeiro.';
                return;
            }

            $fileData = $driveServiceOAuth->downloadFile($fileId);
            $meta = $driveServiceOAuth->getFileMetadata($fileId);
            $fileName = $meta['name'] ?? 'documento';

            header('Content-Type: ' . ($meta['mimeType'] ?? 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . strlen($fileData));
            header('Cache-Control: no-store, private');
            echo $fileData;
            exit;
        }

        $filePath = base_path('storage/removals/' . $fileIdentifier);

        if (!is_file($filePath)) {
            http_response_code(404);
            echo 'Documento nao encontrado.';
            return;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($filePath);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $fileIdentifier . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-store, private');
        readfile($filePath);
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
