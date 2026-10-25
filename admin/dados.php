<?php
/** Opções e preços de venda de um produto, para montar itens do orçamento. */
declare(strict_types=1);

require __DIR__ . '/_base.php';
require RAIZ . '/src/layout.php';

header('Content-Type: application/json; charset=utf-8');
if (!admin_logado()) {
    http_response_code(403);
    exit('{"erro":"Sessão expirada."}');
}
$produto = db_um(
    'SELECT p.*, c.margem margem_categoria FROM produtos p LEFT JOIN categorias c ON c.slug = p.categoria_slug WHERE p.slug = ?',
    [(string) ($_GET['p'] ?? '')]
);
if (!$produto) {
    http_response_code(404);
    exit('{"erro":"Produto não encontrado."}');
}
$combs = db_todos('SELECT id, imagem FROM combinacoes WHERE produto_slug = ?', [$produto['slug']]);
$imagens = array_column($combs, 'imagem', 'id');
$saida = [];
foreach (combinacoes_do_produto($produto) as $c) {
    $c['imagem_original'] = $imagens[$c['id']] ?? $produto['imagem'];
    $saida[] = $c;
}
echo json_encode(['nome' => $produto['nome'], 'imagem' => $produto['imagem'], 'combinacoes' => $saida], JSON_UNESCAPED_UNICODE);
