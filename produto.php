<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require RAIZ . '/src/layout.php';

$slug = (string) ($_GET['p'] ?? '');
$produto = db_um(
    'SELECT p.*, c.nome categoria_nome, c.margem margem_categoria FROM produtos p
     LEFT JOIN categorias c ON c.slug = p.categoria_slug
     WHERE p.slug = ? AND ' . SQL_PRODUTO_PUBLICO,
    [$slug]
);
$combinacoes = $produto ? combinacoes_do_produto($produto) : [];

if (!$produto || !$combinacoes) {
    http_response_code(404);
    topo('Produto não encontrado');
    echo '<p class="vazio">Produto não encontrado. <a href="' . url_base() . '">Voltar ao catálogo</a></p>';
    rodape();
    exit;
}

// Ordem dos atributos: a de aparição nas especificações
$atributos = [];
foreach ($combinacoes as $c) {
    foreach ($c['specs'] as $k => $v) {
        $atributos[$k][$v] = true;
    }
}
$atributos = array_map(function ($vals) {
    $lista = array_keys($vals);
    natcasesort($lista);
    return array_values($lista);
}, $atributos);

$dadosJs = [
    'produto'     => $produto['nome'],
    'combinacoes' => $combinacoes,
    'atributos'   => $atributos,
    'whatsapp'    => preg_replace('/\D/', '', cfg('whatsapp')),
    'unitario'    => cfg('mostrar_unitario') === '1',
    'retirada'    => cfg('retirada_ativa') === '1',
    'apiFrete'    => url_base() . 'api/frete.php',
];

topo($produto['nome']);
$base = url_base();
?>
<nav class="trilha"><a href="<?= $base ?>">Catálogo</a> ›
  <?php if ($produto['categoria_slug']): ?><a href="<?= $base ?>?categoria=<?= e(urlencode($produto['categoria_slug'])) ?>"><?= e($produto['categoria_nome']) ?></a> ›<?php endif; ?>
  <span><?= e($produto['nome']) ?></span></nav>

<article class="produto" id="produto">
  <div class="produto-img"><img id="img-produto" src="<?= e($combinacoes[0]['imagem'] ?: $produto['imagem']) ?>" alt="<?= e($produto['nome']) ?>"></div>

  <div class="produto-info">
    <h1><?= e($produto['nome']) ?></h1>
    <?php if ($produto['descricao']): ?><p class="descricao"><?= nl2br(e(texto_publico($produto['descricao']))) ?></p><?php endif; ?>

    <div class="opcoes" id="opcoes">
      <?php foreach ($atributos as $nome => $valores): ?>
        <label class="opcao"><span><?= e($nome) ?></span>
          <select data-atributo="<?= e($nome) ?>" <?= count($valores) === 1 ? 'disabled' : '' ?>>
            <?php foreach ($valores as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?>
          </select>
        </label>
      <?php endforeach; ?>
    </div>

    <h2 class="sub">Quantidade</h2>
    <table class="tabela-precos" id="tabela-precos">
      <thead><tr><th>Quantidade</th><?php if ($dadosJs['unitario']): ?><th>Por unidade</th><?php endif; ?><th>Valor</th></tr></thead>
      <tbody></tbody>
    </table>

    <p class="prazo">Prazo de produção: <strong id="prazo"></strong> <span class="mudo">+ prazo de entrega</span></p>

    <div class="frete">
      <label for="cep">Calcular frete</label>
      <div class="frete-linha">
        <input id="cep" inputmode="numeric" maxlength="9" placeholder="00000-000">
        <button type="button" class="btn btn-claro" id="btn-frete">Calcular</button>
      </div>
      <p id="frete-resultado" class="mudo"><?= e(cfg('frete_texto')) ?></p>
      <?php if ($dadosJs['retirada']): ?><p class="mudo"><?= e(cfg('retirada_texto')) ?></p><?php endif; ?>
    </div>

    <div class="extras" id="extras" hidden>
      <h2 class="sub">Acabamentos extras <small>(sob consulta)</small></h2>
      <ul></ul>
    </div>

    <div class="resumo">
      <div><span>Total</span><strong id="total">—</strong><small id="total-detalhe"></small></div>
      <?php if (cfg('whatsapp')): ?>
        <a class="btn btn-whats btn-grande" id="btn-pedido" href="#" target="_blank" rel="noopener">Pedir pelo WhatsApp</a>
      <?php endif; ?>
    </div>
  </div>
</article>

<script>window.CATALOGO = <?= json_encode($dadosJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= $base ?>assets/produto.js?v=1"></script>
<?php rodape();
