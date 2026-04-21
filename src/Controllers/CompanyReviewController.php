<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Repositories\CompanyRepository;
use App\Services\RateLimiterService;
use App\Services\ValidationService;
use PDO;

final class CompanyReviewController
{
    private CompanyRepository $repository;
    private ValidationService $validator;

    public function __construct()
    {
        $this->repository = new CompanyRepository();
        $this->validator = new ValidationService();
    }

    public function list(array $params): void
    {
        $cnpj = (string) ($params['cnpj'] ?? '');
        $cnpj = preg_replace('/[^0-9A-Z]/', '', $cnpj);

        $company = $this->repository->findByCnpj($cnpj);
        if (!$company) {
            http_response_code(404);
            echo 'Empresa não encontrada.';
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT r.*, u.name as user_name 
            FROM company_reviews r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.company_id = :company_id AND r.status = "approved"
            ORDER BY r.created_at DESC LIMIT 50');
        $stmt->execute(['company_id' => $company['id']]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $avgStmt = $db->prepare('SELECT AVG(rating) as avg, COUNT(*) as total 
            FROM company_reviews WHERE company_id = :company_id AND status = "approved"');
        $avgStmt->execute(['company_id' => $company['id']]);
        $stats = $avgStmt->fetch(PDO::FETCH_ASSOC);

        view('reviews/index', [
            'company' => $company,
            'reviews' => $reviews,
            'avg_rating' => round($stats['avg'] ?? 0, 1),
            'total_reviews' => (int) ($stats['total'] ?? 0),
            'title' => 'Avaliações - ' . $company['legal_name'],
        ]);
    }

    public function showForm(array $params): void
    {
        $cnpj = (string) ($params['cnpj'] ?? '');
        $cnpj = preg_replace('/[^0-9A-Z]/', '', $cnpj);

        $company = $this->repository->findByCnpj($cnpj);
        if (!$company) {
            http_response_code(404);
            echo 'Empresa não encontrada.';
            return;
        }

        view('reviews/form', [
            'company' => $company,
            'title' => 'Avaliar - ' . $company['legal_name'],
        ]);
    }

    public function submit(array $params): void
    {
        $cnpj = (string) ($params['cnpj'] ?? '');
        $cnpj = preg_replace('/[^0-9A-Z]/', '', $cnpj);

        $company = $this->repository->findByCnpj($cnpj);
        if (!$company) {
            http_response_code(404);
            return;
        }

        $user = Auth::user();
        if (!$user) {
            Session::flash('error', 'Faça login para avaliar.');
            redirect('/login?redirect=/empresa/' . $cnpj . '/avaliar');
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Token expirado. Recarregue a página.');
            redirect("/empresa/$cnpj/avaliar");
        }

        $rating = (int) ($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            Session::flash('error', 'Selecione uma nota de 1 a 5.');
            redirect("/empresa/$cnpj/avaliar");
        }

        if (strlen($comment) > 1000) {
            Session::flash('error', 'Comentário muito longo (máx 1000 caracteres).');
            redirect("/empresa/$cnpj/avaliar");
        }

        $db = Database::connection();

        $checkStmt = $db->prepare('SELECT id FROM company_reviews 
            WHERE company_id = :company_id AND user_id = :user_id 
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $checkStmt->execute([
            'company_id' => $company['id'],
            'user_id' => $user['id'],
        ]);

        if ($checkStmt->fetch()) {
            Session::flash('error', 'Você já avaliou esta empresa recentemente.');
            redirect("/empresa/$cnpj/avaliar");
        }

        $stmt = $db->prepare('INSERT INTO company_reviews 
            (company_id, user_id, rating, comment, status) 
            VALUES (:company_id, :user_id, :rating, :comment, :status)');

        $stmt->execute([
            'company_id' => $company['id'],
            'user_id' => $user['id'],
            'rating' => $rating,
            'comment' => $comment ?: null,
            'status' => 'approved',
        ]);

        Session::flash('success', 'Obrigado! Sua avaliação foi registrada.');
        redirect("/empresa/$cnpj/avaliacoes");
    }

    public function reply(array $params): void
    {
        $cnpj = (string) ($params['cnpj'] ?? '');
        $cnpj = preg_replace('/[^0-9A-Z]/', '', $cnpj);

        $company = $this->repository->findByCnpj($cnpj);
        if (!$company) {
            http_response_code(404);
            return;
        }

        $user = Auth::user();
        if (!$user) {
            Session::flash('error', 'Faça login para responder.');
            redirect('/login');
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT id FROM company_edit_requests 
            WHERE company_id = :company_id AND status = "approved"
            ORDER BY created_at DESC LIMIT 1');
        $stmt->execute(['company_id' => $company['id']]);
        $ownership = $stmt->fetch();

        if (!$ownership) {
            Session::flash('error', 'Você precisa validar a empresa primeiro.');
            redirect("/empresa/$cnpj/dashboard");
        }

        $reviewId = (int) ($_POST['review_id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');

        if (!$reviewId || !$reply) {
            redirect("/empresa/$cnpj/dashboard");
        }

        if (strlen($reply) > 500) {
            Session::flash('error', 'Resposta muito longa.');
            redirect("/empresa/$cnpj/dashboard");
        }

        $update = $db->prepare('UPDATE company_reviews SET reply = :reply, reply_at = NOW() 
            WHERE id = :id AND company_id = :company_id');
        $update->execute([
            'reply' => $reply,
            'id' => $reviewId,
            'company_id' => $company['id'],
        ]);

        Session::flash('success', 'Resposta publicada!');
        redirect("/empresa/$cnpj/dashboard");
    }

    public function report(array $params): void
    {
        $cnpj = (string) ($params['cnpj'] ?? '');
        $cnpj = preg_replace('/[^0-9A-Z]/', '', $cnpj);

        $company = $this->repository->findByCnpj($cnpj);
        if (!$company) {
            http_response_code(404);
            return;
        }

        $user = Auth::user();
        if (!$user) {
            Session::flash('error', 'Faça login para reportar.');
            redirect('/login');
        }

        $reviewId = (int) ($_POST['review_id'] ?? 0);
        if (!$reviewId) {
            redirect("/empresa/$cnpj/avaliacoes");
        }

        $db = Database::connection();
        $update = $db->prepare('UPDATE company_reviews SET reports_count = reports_count + 1 
            WHERE id = :id');
        $update->execute(['id' => $reviewId]);

        Session::flash('success', 'Obrigado! Revisão reportada para moderação.');
        redirect("/empresa/$cnpj/avaliacoes");
    }
}