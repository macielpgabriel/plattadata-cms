<?php
/**
 * Script para minificar arquivos CSS
 * Uso: php scripts/minify_css.php
 */

$cssDir = __DIR__ . '/../public/css';

function minifyCss(string $content): string {
    // Remove comentários
    $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content);
    // Remove espaços extras
    $content = preg_replace('/\s+/', ' ', $content);
    // Remove espaços antes de { e }
    $content = preg_replace('/\s*\{\s*/', '{', $content);
    $content = preg_replace('/\s*\}\s*/', '}', $content);
    // Remove espaços depois de :
    $content = preg_replace('/:\s+/', ':', $content);
    // Remove espaços depois de ;
    $content = preg_replace('/;\s+/', ';', $content);
    // Remove ponto e vírgula antes de }
    $content = preg_replace('/;}/', '}', $content);
    // Remove espaços em branco ao redor de vírgula
    $content = preg_replace('/,\s+/', ',', $content);
    
    return trim($content);
}

echo "Minificando arquivos CSS...\n";

$files = glob($cssDir . '/*.css');
foreach ($files as $file) {
    $filename = basename($file);
    
    // Skip arquivos já minificados
    if (strpos($filename, '.min.') !== false) {
        echo "  Skip: $filename (já minificado)\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $minified = minifyCss($content);
    
    $minFile = str_replace('.css', '.min.css', $file);
    file_put_contents($minFile, $minified);
    
    $originalSize = strlen($content);
    $minSize = strlen($minified);
    $savings = round((1 - $minSize / $originalSize) * 100, 1);
    
    echo "  OK: $filename -> " . basename($minFile) . " (-$savings%)\n";
}

echo "\nFeito! Arquivos minificados salvos com sufixo .min.css\n";