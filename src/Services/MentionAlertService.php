<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;

final class MentionAlertService
{
    public function checkMentions(string $companyName, string $cnpj): array
    {
        $results = [
            'google_alerts' => $this->checkGoogleAlerts($companyName),
            'social_mentions' => $this->checkSocialMentions($companyName),
            'news_mentions' => $this->checkNewsMentions($companyName),
            'checked_at' => date('Y-m-d H:i:s'),
        ];

        $this->saveMentionCheck($cnpj, $companyName, $results);

        return $results;
    }

    private function checkGoogleAlerts(string $companyName): array
    {
        return [
            'found' => true,
            'url' => 'https://news.google.com/rss/search?q=' . urlencode($companyName . ' brasil'),
        ];
    }

    private function checkSocialMentions(string $companyName): array
    {
        return [
            'twitter' => [
                'available' => true,
                'url' => 'https://twitter.com/search?q=' . urlencode($companyName),
            ],
            'linkedin' => [
                'available' => true,
                'url' => 'https://www.linkedin.com/search/results/all/?keywords=' . urlencode($companyName),
            ],
            'instagram' => [
                'available' => true,
                'url' => 'https://www.instagram.com/explore/searches/?q=' . urlencode($companyName),
            ],
            'facebook' => [
                'available' => true,
                'url' => 'https://www.facebook.com/search/pages/?q=' . urlencode($companyName),
            ],
        ];
    }

    private function checkNewsMentions(string $companyName): array
    {
        return [
            'google_news' => [
                'available' => true,
                'url' => 'https://news.google.com/search?q=' . urlencode($companyName),
            ],
        ];
    }

    private function saveMentionCheck(string $cnpj, string $companyName, array $results): void
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("
                INSERT INTO company_mentions (cnpj, company_name, mention_data, checked_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE mention_data = VALUES(mention_data), checked_at = NOW()
            ");
            $stmt->execute([$cnpj, $companyName, json_encode($results)]);
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    public function getMentionHistory(string $cnpj, int $limit = 10): array
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("
                SELECT * FROM company_mentions 
                WHERE cnpj = ?
                ORDER BY checked_at DESC
                LIMIT ?
            ");
            $stmt->execute([$cnpj, $limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function subscribeAlert(string $cnpj, string $email): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("
                INSERT INTO mention_alert_subscriptions (cnpj, email, created_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE email = VALUES(email), active = 1
            ");
            return $stmt->execute([$cnpj, $email]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function unsubscribeAlert(string $cnpj, string $email): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("
                UPDATE mention_alert_subscriptions 
                SET active = 0 
                WHERE cnpj = ? AND email = ?
            ");
            return $stmt->execute([$cnpj, $email]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function sendMentionNotification(string $cnpj, string $companyName, array $mentions): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("
                SELECT email FROM mention_alert_subscriptions 
                WHERE cnpj = ? AND active = 1
            ");
            $stmt->execute([$cnpj]);
            $subscribers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($subscribers)) {
                return false;
            }

            $mailService = new MailService();
            $subject = "Alerta: Nova menção detectada - {$companyName}";
            $body = $this->buildNotificationEmail($companyName, $mentions);

            foreach ($subscribers as $subscriber) {
                $mailService->send($subscriber['email'], $subject, $body);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function buildNotificationEmail(string $companyName, array $mentions): string
    {
        ob_start();
        ?>
        <h2>Alerta de Menção - <?= e($companyName) ?></h2>
        <p>Detectamos novas menções da empresa <?= e($companyName) ?>.</p>
        
        <h3>Redes Sociais</h3>
        <?php if (!empty($mentions['social_mentions'])): ?>
            <ul>
                <?php foreach ($mentions['social_mentions'] as $network => $data): ?>
                    <?php if (!empty($data['available'])): ?>
                        <li>
                            <a href="<?= e($data['url']) ?>">Ver menções no <?= ucfirst($network) ?></a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <h3>Notícias</h3>
        <?php if (!empty($mentions['news_mentions']['google_news']['available'])): ?>
            <p>
                <a href="<?= e($mentions['news_mentions']['google_news']['url']) ?>">
                    Ver notícias no Google News
                </a>
            </p>
        <?php endif; ?>
        
        <hr>
        <p class="small text-muted">
            Você está recebendo este e-mail porque se inscreveu nos alertas do PlattaData.
            <a href="#">Cancelar inscrição</a>
        </p>
        <?php
        return ob_get_clean();
    }
}
