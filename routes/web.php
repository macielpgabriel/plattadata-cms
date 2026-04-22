// Teste rápido - verificar se o arquivo da Receita Federal existe
$router->get('/admin/test-munic-url', function() {
    $url = 'https://arquivos.receitafederal.gov.br/public.php/dav/files/YggdBLfdninEJX9/2026-01/Municipios.zip';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✅ Arquivo encontrado! HTTP $httpCode";
    } else {
        echo "❌ Arquivo não encontrado. HTTP $httpCode";
    }
});