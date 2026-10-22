<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require RAIZ . '/src/layout.php';

$busca = trim((string) ($_GET['q'] ?? ''));
$catSel = (string) ($_GET['categoria'] ?? '');
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 48;

$where = [];
$params = [];
if ($catSel !== '') {
    $where[] = 'p.categoria_slug = ?';
    $params[] = $catSel;
}
if ($busca !== '') {
    foreach (preg_split('/\s+/', $busca) as $termo) {
        $where[] = '(p.nome LIKE ? OR c.nome LIKE ?)';
        $params[] = "%$termo%";
        $params[] = "%$termo%";
    }
}
$produtos = produtos_publicos(implode(' AND ', $where), $params);
$total = count($produtos);
$produtos = array_slice($produtos, ($pagina - 1) * $porPagina, $porPagina);
$categorias = categorias_publicas();
$nomeCat = '';
foreach ($categorias as $c) {
    if ($c['slug'] === $catSel) {
        $nomeCat = $c['nome'];
    }
}

topo($nomeCat ?: cfg('nome_empresa'));
$base = url_base();
?>
<nav class="categorias" aria-label="Categorias">
  <a href="<?= $base ?>" class="<?= $catSel === '' ? 'ativo' : '' ?>">Todos</a>
  <?php foreach ($categorias as $c): ?>
    <a href="?categoria=<?= e(urlencode($c['slug'])) ?>" class="<?= $catSel === $c['slug'] ? 'ativo' : '' ?>"><?= e($c['nome']) ?></a>
  <?php endforeach; ?>
</nav>

<div class="titulo-lista">
  <h1><?= e($busca !== '' ? "Resultados para “{$busca}”" : ($nomeCat ?: 'Todos os produtos')) ?></h1>
  <a class="btn btn-claro" href="<?= $base ?>catalogo.php<?= $catSel ? '?categoria=' . e(urlencode($catSel)) : '' ?>" target="_blank">Catálogo em PDF</a>
</div>

<?php if (!$produtos): ?>
  <p class="vazio">Nenhum produto encontrado.</p>
<?php else: ?>
  <div class="grade">
    <?php foreach ($produtos as $p): ?>
      <a class="cartao" href="<?= $base ?>produto.php?p=<?= e(urlencode($p['slug'])) ?>">
        <div class="cartao-img"><img src="<?= e($p['imagem']) ?>" alt="<?= e($p['nome']) ?>" loading="lazy"></div>
        <div class="cartao-corpo">
          <small><?= e($p['categoria_nome']) ?></small>
          <h2><?= e($p['nome']) ?></h2>
          <p class="preco"><span>a partir de</span> <?= moeda($p['preco_min']) ?></p>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php $paginas = (int) ceil($total / $porPagina); if ($paginas > 1): ?>
    <nav class="paginacao">
      <?php for ($i = 1; $i <= $paginas; $i++):
          $qs = http_build_query(array_filter(['q' => $busca, 'categoria' => $catSel, 'pagina' => $i > 1 ? $i : null])); ?>
        <a href="?<?= e($qs) ?>" class="<?= $i === $pagina ? 'ativo' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
<?php rodape();
