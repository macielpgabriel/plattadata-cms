<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Cache;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Repositories\CompanyRepository;
use App\Services\MailService;
use App\Services\ValidationService;
use PDO;
use Exception;

final class CompanyDashboardController
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
        $cnpj = preg_replace('/[^0-9A-Z]/', '', $cnpj);

        if (strlen($cnpj) !== 14) {
            Session::flash('error', 'CNPJ inválido.');
            redirect('/empresas/validar');
        }

        $company = $this->repository->findByCnpj($cnpj);
        if (!$company) {
            http_response_code(404);
            echo 'Empresa não encontrada no sistema.';
            return;
        }

        $db = Database::connection();
        $existing = $db->prepare('SELECT id, status, created_at FROM company_edit_requests WHERE cnpj = :cnpj AND status IN ("pending", "verified", "approved") ORDER BY created_at DESC LIMIT 1');
        $existing->execute(['cnpj' => $cnpj]);
        $pending = $existing->fetch(PDO::FETCH_ASSOC);

        view('dashboard_company/request', [
            'company' => $company,
            'pending' => $pending,
            'title' => 'Validar Empresa',
        ]);
    }

    public function submitRequest(array $params): void
    {
        $cnpj = (string) ($params['cnpj'] ?? '');
        $cnpj = preg_replace('/[^0-9A-Z]/', '', $cnpj);

        $company = $this->repository->findByCnpj($cnpj);
        if (!$company) {
            http_response_code(404);
            return;
        }

        $requesterName = $this->validator->sanitizeName($_POST['name'] ?? '');
        $requesterEmail = $this->validator->sanitizeEmail($_POST['email'] ?? '');

        if (!$this->validator->name($requesterName)) {
            Session::flash('error', 'Nome inválido. Use apenas letras e espaços.');
            redirect("/empresas/validar/$cnpj");
        }

        if (!$this->validator->email($requesterEmail)) {
            Session::flash('error', 'E-mail inválido.');
            redirect("/empresas/validar/$cnpj");
        }

        $verificationType = 'document';
        $verificationCode = null;

        if (!empty($company['email'])) {
            $verificationType = 'email';
            $verificationCode = (string) random_int(100000, 999999);
        }

        $db = Database::connection();
        $stmt = $db->prepare('INSERT INTO company_edit_requests 
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
            $subject = "Código de verificação - " . $company['legal_name'];
            $template = file_get_contents(base_path('templates/emails/edit_verify.html'));
            $template = str_replace('DATA', date('d/m/Y H:i'), $template);
            $template = str_replace('EMPRESA', $company['legal_name'], $template);
            $body = str_replace('CODIGO', $verificationCode, $template);
            
            $targetEmail = $company['email'] ?? $requesterEmail;
            $mailService->send($targetEmail, $subject, $body);
            
            redirect("/empresas/validar/verificar?id=$requestId");
        } else {
            redirect("/empresas/validar/documento?id=$requestId");
        }
    }

    public function showVerifyForm(): void
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            redirect('/empresas/validar');
        }

        view('dashboard_company/verify', [
            'request_id' => $id,
            'title' => 'Verificar Empresa',
        ]);
    }

    public function verifyCode(): void
    {
        $id = $_POST['id'] ?? null;
        $code = $_POST['code'] ?? '';

        if (!$id || !$code) {
            Session::flash('error', 'Código inválido.');
            redirect("/empresas/validar/verificar?id=$id");
        }

        $lockoutKey = "edit_verify_$id";
        if (Cache::get($lockoutKey)) {
            Session::flash('error', 'Muitas tentativas. Aguarde 5 minutos.');
            redirect("/empresas/validar/verificar?id=$id");
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM company_edit_requests WHERE id = :id AND verification_code = :code AND status = "pending"');
        $stmt->execute(['id' => $id, 'code' => $code]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            $attempts = (int) (Cache::get($lockoutKey) ?? 0) + 1;
            Cache::set($lockoutKey, $attempts, 300);
            Session::flash('error', 'Código inválido.');
            redirect("/empresas/validar/verificar?id=$id");
        }

        $update = $db->prepare('UPDATE company_edit_requests SET status = "verified", verified_at = NOW() WHERE id = :id');
        $update->execute(['id' => $id]);

        $cnpj = $request['cnpj'];
        $token = bin2hex(random_bytes(32));
        Cache::set("edit_token_$id", $token, 3600);
        
        redirect("/empresas/validar/sucesso?id=$id&token=$token");
    }

    public function showDocumentForm(): void
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            redirect('/empresas/validar');
        }

        view('dashboard_company/document', [
            'request_id' => $id,
            'title' => 'Anexar Documento',
        ]);
    }

    public function submitDocument(): void
    {
        $id = $_POST['id'] ?? null;
        if (!$id) {
            redirect('/empresas/validar');
        }

        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Erro ao enviar documento.');
            redirect("/empresas/validar/documento?id=$id");
        }

        $file = $_FILES['document'];
        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        
        if (!in_array($file['type'], $allowed)) {
            Session::flash('error', 'Apenas PDF, JPG ou PNG.');
            redirect("/empresas/validar/documento?id=$id");
        }

        $cnpj = $_POST['cnpj'] ?? '';
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = "edit_{$cnpj}_{$id}.{$extension}";
        
        $uploadDir = base_path('storage/edit_documents/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $targetPath = $uploadDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            Session::flash('error', 'Erro ao salvar arquivo.');
            redirect("/empresas/validar/documento?id=$id");
        }

        $db = Database::connection();
        $update = $db->prepare('UPDATE company_edit_requests SET document_path = :path, status = "pending" WHERE id = :id');
        $update->execute(['path' => "storage/edit_documents/$filename", 'id' => $id]);

        Session::flash('success', 'Documento enviado! Aguarde aprovação.');
        redirect("/empresas/validar/documento?id=$id");
    }

    public function showSuccess(array $params): void
    {
        $id = $_GET['id'] ?? null;
        $token = $_GET['token'] ?? null;

        if (!$id || !$token) {
            redirect('/empresas/validar');
        }

        $storedToken = Cache::get("edit_token_$id");
        if ($storedToken !== $token) {
            Session::flash('error', 'Token expirado.');
            redirect('/empresas/validar');
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT cnpj, company_id FROM company_edit_requests WHERE id = :id AND status = "verified"');
        $stmt->execute(['id' => $id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            redirect('/empresas/validar');
        }

        $update = $db->prepare('UPDATE company_edit_requests SET status = "approved" WHERE id = :id');
        $update->execute(['id' => $id]);

        $expires = time() + 86400 * 30;
        Cache::set("company_owner_{$request['company_id']}", $id, 86400 * 30);

        view('dashboard_company/success', [
            'cnpj' => $request['cnpj'],
            'company_id' => $request['company_id'],
            'title' => 'Empresa Validada',
        ]);
    }

    public function showDashboard(array $params): void
    {
        $cnpj = (string) ($params['cnpj'] ?? '');
        $cnpjDigits = preg_replace('/[^0-9A-Z]/', '', $cnpj);

        if (strlen($cnpjDigits) !== 14) {
            redirect('/empresas/validar');
        }

        $company = $this->repository->findByCnpj($cnpjDigits);
        if (!$company) {
            http_response_code(404);
            echo 'Empresa não encontrada.';
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT id, status FROM company_edit_requests WHERE cnpj = :cnpj AND status = "approved" ORDER BY created_at DESC LIMIT 1');
        $stmt->execute(['cnpj' => $cnpjDigits]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            redirect("/empresas/validar/$cnpjDigits");
        }

        view('dashboard_company/index', [
            'company' => $company,
            'title' => 'Dashboard Empresarial',
        ]);
    }

    public function updateProfile(array $params): void
    {
        $cnpj = (string) ($params['cnpj'] ?? '');
        $cnpjDigits = preg_replace('/[^0-9A-Z]/', '', $cnpj);

        $company = $this->repository->findByCnpj($cnpjDigits);
        if (!$company) {
            http_response_code(404);
            return;
        }

        if (!Csrf::validate($_POST['_token'] ?? '')) {
            Session::flash('error', 'Token expirado. Recarregue a página.');
            redirect("/empresas/$cnpj/dashboard");
        }

        $db = Database::connection();
        
        $description = strip_tags($_POST['description'] ?? '', '<p><br><b><strong><em><i><u><a>');
        $facebook = $this->validator->sanitizeUrl($_POST['facebook'] ?? '');
        $instagram = preg_replace('/[^a-zA-Z0-9_.]/', '', $_POST['instagram'] ?? '');
        $linkedin = $this->validator->sanitizeUrl($_POST['linkedin'] ?? '');
        $whatsapp = preg_replace('/[^0-9]/', '', $_POST['whatsapp'] ?? '');

        $stmt = $db->prepare('UPDATE companies SET 
            description = :desc,
            facebook = :fb,
            instagram = :ig,
            linkedin = :li,
            whatsapp = :wa,
            updated_at = NOW()
            WHERE id = :id');

        $stmt->execute([
            'desc' => $description ?: null,
            'fb' => $facebook ?: null,
            'ig' => $instagram ?: null,
            'li' => $linkedin ?: null,
            'wa' => $whatsapp ?: null,
            'id' => $company['id'],
        ]);

        Session::flash('success', 'Dados atualizados!');
        redirect("/empresas/$cnpj/dashboard");
    }
}