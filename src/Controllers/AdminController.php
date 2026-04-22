public function clearCache(): void
    {
        if (!\App\Core\Auth::can(['admin'])) {
            http_response_code(403);
            echo 'Acesso negado.';
            return;
        }

        if (!\App\Core\Csrf::validate($_POST['_token'] ?? null)) {
            Session::flash('error', 'Sessão expirada.');
            redirect('/admin');
        }

        try {
            $results = \App\Core\Logger::autoCleanup();
            
            $totalCleared = $results['cache_files_removed'] ?? 0;
            $logCleared = $results['log_cleared'] ? 'Sim' : 'Não';
            
            Session::flash('success', "Cache limpo! Arquivos de cache removidos: {$totalCleared}. Logs cleared: {$logCleared}.");
        } catch (\Throwable $e) {
            Session::flash('error', 'Erro ao limpar cache: ' . $e->getMessage());
        }

        redirect('/admin');
    }