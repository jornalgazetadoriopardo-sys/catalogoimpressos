<?php
/**
 * Imagens dos produtos: baixa do servidor da gráfica uma única vez, reduz para
 * a largura pedida e guarda em data/img. Servir pelo próprio domínio permite
 * que o PDF do orçamento inclua as fotos.
 */
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require RAIZ . '/src/leitor.php';

$url = (string) ($_GET['u'] ?? '');
$largura = in_array((int) ($_GET['w'] ?? 400), [120, 400, 800], true) ? (int) $_GET['w'] : 400;

$host = strtolower((string) parse_url($url, PHP_URL_HOST));
$permitidos = ['wbl.blob.core.windows.net', strtolower((string) parse_url((string) config('origem'), PHP_URL_HOST))];
if (!preg_match('#^https://#', $url) || !in_array($host, $permitidos, true)) {
    http_response_code(400);
    exit('Imagem não permitida.');
}

$pasta = RAIZ . '/data/img';
if (!is_dir($pasta)) {
    @mkdir($pasta, 0755, true);
}
$gd = function_exists('imagecreatefromstring');
$arquivo = $pasta . '/' . md5($url) . '-' . $largura . ($gd ? '.jpg' : '.orig');

if (!is_file($arquivo)) {
    try {
        $dados = baixar($url);
    } catch (Throwable $ex) {
        http_response_code(404);
        exit;
    }
    if (strlen($dados) > 8 * 1024 * 1024) {
        http_response_code(413);
        exit;
    }
    if ($gd && ($img = @imagecreatefromstring($dados))) {
        $w = imagesx($img);
        $h = imagesy($img);
        $nw = min($largura, $w);
        $nh = (int) round($h * $nw / $w);
        $saida = imagecreatetruecolor($nw, $nh);
        imagefill($saida, 0, 0, imagecolorallocate($saida, 255, 255, 255)); // fundo branco no lugar da transparência
        imagecopyresampled($saida, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagejpeg($saida, $arquivo, 85);
    } else {
        file_put_contents($arquivo, $dados);
    }
}

$tipo = $gd ? 'image/jpeg' : ((new finfo(FILEINFO_MIME_TYPE))->file($arquivo) ?: 'application/octet-stream');
header('Content-Type: ' . $tipo);
header('Cache-Control: public, max-age=2592000');
header('Content-Length: ' . filesize($arquivo));
readfile($arquivo);
