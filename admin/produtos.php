<?php
declare(strict_types=1);

require __DIR__ . '/_base.php';
require RAIZ . '/src/importador.php';
exigir_login();

$filtro = [
    'q'         => trim((string) ($_GET['q'] ?? '')),
    'categoria' => (string) ($_GET['categoria'] ?? ''),
    'pagina'    => max(1, (int) ($_GET['pagina'] ?? 1)),
];

if (post_valido()) {
    $acao = (string) ($_POST['acao'] ?? 'salvar');
    $selecionados = array_values(array_filter((array) ($_POST['sel'] ?? []), 'is_string'));
    if ($acao === 'salvar') {
        foreach ((array) ($_POST['prod'] ?? []) as $slug => $p) {
            db_exec('UPDATE produtos SET margem = ?, visivel = ?, destaque = ? WHERE slug = ?', [
                margem_digitada($p['margem'] ?? ''), empty($p['visivel']) ? 0 : 1, empty($p['destaque']) ? 0 : 1, (string) $slug,
            ]);
        }
        flash('Alterações salvas.');
    } elseif ($acao === 'remover' && $selecionados) {
        foreach ($selecionados as $slug) {
            remover_produto($slug);
        }
        flash(count($selecionados) . ' produto(s) removido(s) do catálogo.');
    }
    voltar('produtos.php?' . http_build_query(array_filter($filtro)));
}

$where = ['1 = 1'];
$params = [];
if ($filtro['q'] !== '') {
    $where[] = 'p.nome LIKE ?';
    $params[] = '%' . $filtro['q'] . '%';
}
if ($filtro['categoria'] !== '') {
    $where[] = 'p.categoria_slug = ?';
    $params[] = $filtro['categoria'];
}
$sqlWhere = implode(' AND ', $where);
$total = (int) db_valor("SELECT COUNT(*) FROM produtos p WHERE $sqlWhere", $params);
$porPagina = 50;
$offset = ($filtro['pagina'] - 1) * $porPagina;

$produtos = db_todos(
    "SELECT p.slug, p.nome, p.imagem, p.margem, p.visivel, p.destaque, c.nome categoria, c.margem margem_categoria,
            (SELECT MIN(pr.custo) FROM combinacoes cb JOIN precos pr ON pr.combinacao_id = cb.id WHERE cb.produto_slug = p.slug) custo_min,
            (SELECT COUNT(*) FROM combinacoes cb WHERE cb.produto_slug = p.slug) opcoes,
            (SELECT MIN(cb.atualizado_em) FROM combinacoes cb WHERE cb.produto_slug = p.slug) atualizado
     FROM produtos p LEFT JOIN categorias c ON c.slug = p.categoria_slug
     WHERE $sqlWhere ORDER BY c.nome, p.nome LIMIT $porPagina OFFSET $offset",
    $params
);
$categorias = db_todos('SELECT c.slug, c.nome FROM categorias c WHERE EXISTS (SELECT 1 FROM produtos p WHERE p.categoria_slug = c.slug) ORDER BY c.nome');
$ultima = cfg('ultima_atualizacao');
$totalGeral = (int) db_valor('SELECT COUNT(*) FROM produtos');

$botoes = $totalGeral
    ? '<button class="btn" type="button" data-fila="atualizar" data-bloquear data-titulo="Atualizando valores de todos os produtos…"
         data-confirmar="Buscar os preços atuais de todos os produtos no site da gráfica?">↻ Atualizar valores</button>'
    : '';
admin_topo('Produtos cadastrados', 'produtos.php', $botoes);
?>
<p class="mudo">
  <?= $totalGeral ?> produto(s) no catálogo.
  Valores atualizados pela última vez em <strong><?= $ultima ? e(date('d/m/Y \à\s H:i', strtotime($ultima))) : '— (ainda não atualizado)' ?></strong>.
  <a href="adicionar.php">+ Adicionar produtos</a>
</p>

<?= painel_progresso() ?>

<?php if (!$totalGeral): ?>
  <div class="painel vazio-admin">
    Nenhum produto cadastrado ainda.<br><br>
    <a class="btn" href="adicionar.php">Adicionar produtos do site da gráfica</a>
  </div>
<?php else: ?>
<form class="painel filtros" method="get">
  <input type="search" name="q" value="<?= e($filtro['q']) ?>" placeholder="Buscar por nome">
  <select name="categoria" onchange="this.form.submit()">
    <option value="">Todas as categorias</option>
    <?php foreach ($categorias as $c): ?><option value="<?= e($c['slug']) ?>" <?= $filtro['categoria'] === $c['slug'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-claro" type="submit">Filtrar</button>
</form>

<form method="post" class="painel" id="form-produtos">
  <?= campo_csrf() ?>
  <div class="rolagem">
    <table class="tabela">
      <thead><tr><th><input type="checkbox" id="sel-todos" title="Selecionar todos"></th><th></th><th>Produto</th><th>Opções</th>
        <th>Custo a partir de</th><th>Margem %</th><th>Venda a partir de</th><th>No catálogo</th><th>Destaque</th></tr></thead>
      <tbody>
        <?php foreach ($produtos as $p):
            $s = e($p['slug']);
            $m = margem_efetiva($p['margem'], $p['margem_categoria']);
            $custo = (float) $p['custo_min']; ?>
          <tr>
            <td><input type="checkbox" name="sel[]" value="<?= $s ?>" class="sel"></td>
            <td><img src="<?= e(img_url($p['imagem'], 120)) ?>" alt="" loading="lazy"></td>
            <td><a href="../produto.php?p=<?= e(urlencode($p['slug'])) ?>" target="_blank"><?= e($p['nome']) ?></a>
              <br><small class="mudo"><?= e($p['categoria']) ?><?= $p['atualizado'] ? ' · atualizado ' . e(date('d/m H:i', strtotime($p['atualizado']))) : '' ?></small></td>
            <td><?= (int) $p['opcoes'] ?></td>
            <td><?= $custo ? moeda($custo) : '—' ?></td>
            <td><input type="text" inputmode="decimal" name="prod[<?= $s ?>][margem]" value="<?= e($p['margem'] === null ? '' : rtrim(rtrim((string) $p['margem'], '0'), '.')) ?>" placeholder="<?= e(rtrim(rtrim(number_format($m, 2, '.', ''), '0'), '.')) ?>" title="Em branco = margem da categoria ou padrão"></td>
            <td><strong><?= $custo ? moeda(preco_venda($custo, $m)) : '—' ?></strong></td>
            <td><input type="checkbox" name="prod[<?= $s ?>][visivel]" value="1" <?= $p['visivel'] ? 'checked' : '' ?>></td>
            <td><input type="checkbox" name="prod[<?= $s ?>][destaque]" value="1" <?= $p['destaque'] ? 'checked' : '' ?>></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$produtos): ?><tr><td colspan="9" class="mudo">Nenhum produto encontrado com esse filtro.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="acoes">
    <button class="btn" type="submit" name="acao" value="salvar">Salvar alterações</button>
    <button class="btn btn-claro" type="button" id="btn-atualizar-sel" data-bloquear>↻ Atualizar valores dos selecionados</button>
    <button class="btn btn-claro btn-perigo-claro" type="submit" name="acao" value="remover"
            onclick="return document.querySelectorAll('.sel:checked').length ? confirm('Remover os produtos selecionados do catálogo?') : (alert('Selecione os produtos a remover.'), false)">Remover selecionados</button>
  </div>
  <p class="mudo">Margem em branco usa a da categoria ou a padrão (<?= e(cfg('margem_padrao')) ?>%), definidas em <a href="configuracoes.php">Configurações</a>.</p>
</form>

<?php $paginas = (int) ceil($total / $porPagina); if ($paginas > 1): ?>
  <nav class="paginacao">
    <?php for ($i = 1; $i <= $paginas; $i++): ?>
      <a href="?<?= e(http_build_query(array_filter(['pagina' => $i] + $filtro))) ?>" class="<?= $i === $filtro['pagina'] ? 'ativo' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var todos = document.getElementById('sel-todos');
  if (todos) todos.addEventListener('change', function () {
    document.querySelectorAll('.sel').forEach(function (c) { c.checked = todos.checked; });
  });
  var b = document.getElementById('btn-atualizar-sel');
  if (b) b.addEventListener('click', function () {
    var slugs = [].map.call(document.querySelectorAll('.sel:checked'), function (c) { return c.value; });
    if (!slugs.length) { alert('Selecione os produtos que quer atualizar.'); return; }
    window.rodarFila('atualizar', { slugs: slugs }, 'Atualizando ' + slugs.length + ' produto(s)…', function () { location.reload(); });
  });
});
</script>
<?php admin_rodape();
