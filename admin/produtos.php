<?php
declare(strict_types=1);

require __DIR__ . '/_base.php';
exigir_login();

$filtro = [
    'q'         => trim((string) ($_GET['q'] ?? '')),
    'categoria' => (string) ($_GET['categoria'] ?? ''),
    'status'    => (string) ($_GET['status'] ?? ''),
    'pagina'    => max(1, (int) ($_GET['pagina'] ?? 1)),
];

if (post_valido()) {
    $acao = $_POST['acao'] ?? 'salvar';
    $slugs = array_keys((array) ($_POST['prod'] ?? []));
    foreach ((array) ($_POST['prod'] ?? []) as $slug => $p) {
        if ($acao === 'salvar') {
            db_exec('UPDATE produtos SET margem = ?, visivel = ?, destaque = ? WHERE slug = ?', [
                margem_digitada($p['margem'] ?? ''), empty($p['visivel']) ? 0 : 1, empty($p['destaque']) ? 0 : 1, (string) $slug,
            ]);
        }
    }
    if (in_array($acao, ['mostrar', 'ocultar'], true) && $slugs) {
        $marc = implode(',', array_fill(0, count($slugs), '?'));
        db_exec("UPDATE produtos SET visivel = ? WHERE slug IN ($marc)", array_merge([$acao === 'mostrar' ? 1 : 0], $slugs));
    }
    flash('Produtos salvos.');
    voltar('produtos.php?' . http_build_query(array_filter($filtro)));
}

$where = ['p.ativo_origem = 1'];
$params = [];
if ($filtro['q'] !== '') {
    $where[] = 'p.nome LIKE ?';
    $params[] = '%' . $filtro['q'] . '%';
}
if ($filtro['categoria'] !== '') {
    $where[] = 'p.categoria_slug = ?';
    $params[] = $filtro['categoria'];
}
if ($filtro['status'] === 'visiveis') {
    $where[] = 'p.visivel = 1';
} elseif ($filtro['status'] === 'ocultos') {
    $where[] = 'p.visivel = 0';
} elseif ($filtro['status'] === 'margem') {
    $where[] = 'p.margem IS NOT NULL';
}
$sqlWhere = implode(' AND ', $where);
$total = (int) db_valor("SELECT COUNT(*) FROM produtos p WHERE $sqlWhere", $params);
$porPagina = 50;
$offset = ($filtro['pagina'] - 1) * $porPagina;

$produtos = db_todos(
    "SELECT p.slug, p.nome, p.imagem, p.margem, p.visivel, p.destaque, c.nome categoria, c.margem margem_categoria,
            (SELECT MIN(pr.custo) FROM combinacoes cb JOIN precos pr ON pr.combinacao_id = cb.id WHERE cb.produto_slug = p.slug) custo_min,
            (SELECT COUNT(*) FROM combinacoes cb WHERE cb.produto_slug = p.slug) combinacoes
     FROM produtos p LEFT JOIN categorias c ON c.slug = p.categoria_slug
     WHERE $sqlWhere ORDER BY c.nome, p.nome LIMIT $porPagina OFFSET $offset",
    $params
);
$categorias = db_todos('SELECT slug, nome FROM categorias ORDER BY nome');

admin_topo('Produtos', 'produtos.php');
?>
<form class="painel filtros" method="get">
  <input type="search" name="q" value="<?= e($filtro['q']) ?>" placeholder="Buscar por nome">
  <select name="categoria">
    <option value="">Todas as categorias</option>
    <?php foreach ($categorias as $c): ?><option value="<?= e($c['slug']) ?>" <?= $filtro['categoria'] === $c['slug'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
  </select>
  <select name="status">
    <option value="">Todos</option>
    <option value="visiveis" <?= $filtro['status'] === 'visiveis' ? 'selected' : '' ?>>Visíveis</option>
    <option value="ocultos" <?= $filtro['status'] === 'ocultos' ? 'selected' : '' ?>>Ocultos</option>
    <option value="margem" <?= $filtro['status'] === 'margem' ? 'selected' : '' ?>>Com margem própria</option>
  </select>
  <button class="btn btn-claro" type="submit">Filtrar</button>
  <span class="mudo"><?= $total ?> produtos</span>
</form>

<form method="post" class="painel">
  <?= campo_csrf() ?>
  <p class="mudo">Margem em branco = usa a da categoria ou a padrão. Os preços abaixo são da opção mais barata de cada produto.</p>
  <div class="rolagem">
    <table class="tabela">
      <thead><tr><th>Visível</th><th>Destaque</th><th></th><th>Produto</th><th>Opções</th><th>Custo a partir de</th><th>Margem (%)</th><th>Venda a partir de</th></tr></thead>
      <tbody>
        <?php foreach ($produtos as $p):
            $s = e($p['slug']);
            $m = margem_efetiva($p['margem'], $p['margem_categoria']);
            $custo = (float) $p['custo_min']; ?>
          <tr>
            <td><input type="checkbox" name="prod[<?= $s ?>][visivel]" value="1" <?= $p['visivel'] ? 'checked' : '' ?>></td>
            <td><input type="checkbox" name="prod[<?= $s ?>][destaque]" value="1" <?= $p['destaque'] ? 'checked' : '' ?>></td>
            <td><img src="<?= e($p['imagem']) ?>" alt="" loading="lazy"></td>
            <td><a href="../produto.php?p=<?= e(urlencode($p['slug'])) ?>" target="_blank"><?= e($p['nome']) ?></a><br><small class="mudo"><?= e($p['categoria']) ?></small></td>
            <td><?= (int) $p['combinacoes'] ?></td>
            <td><?= $custo ? moeda($custo) : '—' ?></td>
            <td><input type="text" inputmode="decimal" name="prod[<?= $s ?>][margem]" value="<?= e($p['margem'] === null ? '' : rtrim(rtrim((string) $p['margem'], '0'), '.')) ?>" placeholder="<?= e(rtrim(rtrim(number_format($m, 2, '.', ''), '0'), '.')) ?>"></td>
            <td><strong><?= $custo ? moeda(preco_venda($custo, $m)) : '—' ?></strong></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$produtos): ?><tr><td colspan="8" class="mudo">Nenhum produto encontrado.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="acoes">
    <button class="btn" type="submit" name="acao" value="salvar">Salvar alterações</button>
    <button class="btn btn-claro" type="submit" name="acao" value="mostrar">Mostrar todos desta página</button>
    <button class="btn btn-claro" type="submit" name="acao" value="ocultar">Ocultar todos desta página</button>
  </div>
</form>

<?php $paginas = (int) ceil($total / $porPagina); if ($paginas > 1): ?>
  <nav class="paginacao">
    <?php for ($i = 1; $i <= $paginas; $i++): ?>
      <a href="?<?= e(http_build_query(array_filter(['pagina' => $i] + $filtro))) ?>" class="<?= $i === $filtro['pagina'] ? 'ativo' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
<?php admin_rodape();
