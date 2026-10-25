<?php
declare(strict_types=1);

require __DIR__ . '/_base.php';
require RAIZ . '/src/leitor.php';
exigir_login();

$origem = rtrim((string) config('origem'), '/');
$busca = trim((string) ($_GET['q'] ?? ''));
$cat = preg_replace('/[^a-z0-9-]/', '', (string) ($_GET['cat'] ?? ''));
$erro = '';

// Departamentos do site: guardados por 7 dias (ou até clicar em "recarregar")
$deps = json_decode(cfg('departamentos_cache'), true) ?: [];
if (!$deps || isset($_GET['recarregar']) || strtotime(cfg('departamentos_data') ?: '2000-01-01') < time() - 7 * 86400) {
    try {
        $deps = departamentos_do_site($origem);
        salvar_cfg('departamentos_cache', json_encode($deps, JSON_UNESCAPED_UNICODE));
        salvar_cfg('departamentos_data', agora());
    } catch (Throwable $ex) {
        $erro = 'Não foi possível ler as categorias do site: ' . $ex->getMessage();
    }
}

$nomeLista = '';
$cartoes = [];
if ($busca !== '' || $cat !== '') {
    try {
        $url = $busca !== '' ? $origem . '/busca?query=' . rawurlencode($busca) : $origem . '/' . $cat;
        $cartoes = ler_cartoes_produtos(baixar($url));
        $nomeLista = $busca !== '' ? "Resultados para “{$busca}”" : $cat;
        foreach ($deps as $d) {
            foreach ($d['categorias'] as $c) {
                if ($c['slug'] === $cat) {
                    $nomeLista = $c['nome'];
                }
            }
        }
    } catch (Throwable $ex) {
        $erro = 'Não foi possível abrir esta página do site: ' . $ex->getMessage();
    }
}
$cadastrados = array_flip(array_column(db_todos('SELECT slug FROM produtos'), 'slug'));
$margem = (float) cfg('margem_padrao');

admin_topo('Adicionar produtos', 'adicionar.php');
?>
<p class="mudo">Escolha os produtos do site da gráfica que vão para o seu catálogo. Ao adicionar, todas as opções do produto
  (material, formato, acabamento e quantidades) são lidas e ficam cadastradas.</p>

<div class="painel buscas">
  <form method="get" class="filtros">
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Buscar no site da gráfica (ex.: cartão de visita, banner…)" style="flex:1;min-width:220px">
    <button class="btn" type="submit">Buscar</button>
  </form>
  <form class="filtros" id="form-link">
    <input type="url" id="link-produto" placeholder="ou cole o link de um produto do site" style="flex:1;min-width:220px">
    <button class="btn btn-claro" type="submit" data-bloquear>Adicionar pelo link</button>
  </form>
</div>

<?= painel_progresso() ?>
<?php if ($erro): ?><div class="msg erro"><?= e($erro) ?></div><?php endif; ?>

<div class="adicionar">
  <aside class="painel departamentos">
    <strong>Categorias do site</strong>
    <?php foreach ($deps as $d):
        $aberto = $cat !== '' && in_array($cat, array_column($d['categorias'], 'slug'), true); ?>
      <details <?= $aberto ? 'open' : '' ?>>
        <summary><?= e($d['nome']) ?></summary>
        <?php foreach ($d['categorias'] as $c): ?>
          <a href="?cat=<?= e($c['slug']) ?>" class="<?= $c['slug'] === $cat ? 'ativo' : '' ?>"><?= e($c['nome']) ?></a>
        <?php endforeach; ?>
      </details>
    <?php endforeach; ?>
    <a class="mudo recarregar" href="?recarregar=1">↻ recarregar categorias</a>
  </aside>

  <section>
    <?php if (!$cartoes): ?>
      <div class="painel vazio-admin">
        <?php if ($busca !== '' || $cat !== ''): ?>Nenhum produto encontrado nesta página.
        <?php else: ?>Escolha uma categoria ao lado ou faça uma busca.<?php endif; ?>
      </div>
    <?php else: ?>
      <div class="lista-topo">
        <h2><?= e($nomeLista) ?> <small class="mudo"><?= count($cartoes) ?> produtos</small></h2>
        <button class="btn" id="btn-adicionar-sel" type="button" data-bloquear disabled>Adicionar selecionados</button>
      </div>
      <div class="grade-admin">
        <?php foreach ($cartoes as $c): $ja = isset($cadastrados[$c['slug']]); ?>
          <label class="cartao-admin <?= $ja ? 'ja' : '' ?>">
            <?php if (!$ja): ?><input type="checkbox" class="sel-produto" value="<?= e($c['slug']) ?>"><?php endif; ?>
            <img src="<?= e(img_url($c['imagem'])) ?>" alt="" loading="lazy">
            <span class="nome"><?= e($c['nome']) ?></span>
            <?php if ($c['preco'] !== null): ?>
              <span class="mudo">Custo a partir de <?= moeda($c['preco']) ?><?= $c['unidade'] ? ' / ' . e($c['unidade']) : '' ?></span>
              <span>Venda (<?= e(cfg('margem_padrao')) ?>%): <strong><?= moeda(preco_venda($c['preco'], $margem)) ?></strong></span>
            <?php endif; ?>
            <?php if ($ja): ?><span class="selo-ok">✓ Já cadastrado</span>
            <?php else: ?><button class="btn btn-claro btn-peq" type="button" data-adicionar="<?= e($c['slug']) ?>" data-bloquear>Adicionar</button><?php endif; ?>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  function adicionar(itens) {
    window.rodarFila('adicionar', { itens: itens }, 'Adicionando ' + itens.length + ' produto(s)…', function () { location.reload(); });
  }
  document.querySelectorAll('[data-adicionar]').forEach(function (b) {
    b.addEventListener('click', function (ev) { ev.preventDefault(); adicionar([b.dataset.adicionar]); });
  });
  var sel = document.getElementById('btn-adicionar-sel');
  function contar() {
    var n = document.querySelectorAll('.sel-produto:checked').length;
    if (sel) { sel.disabled = !n; sel.textContent = n ? 'Adicionar ' + n + ' selecionado(s)' : 'Adicionar selecionados'; }
  }
  document.querySelectorAll('.sel-produto').forEach(function (c) { c.addEventListener('change', contar); });
  if (sel) sel.addEventListener('click', function () {
    adicionar([].map.call(document.querySelectorAll('.sel-produto:checked'), function (c) { return c.value; }));
  });
  document.getElementById('form-link').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var v = document.getElementById('link-produto').value.trim();
    if (!/\/produto\//.test(v)) { alert('Cole o link de uma página de produto (ele tem /produto/ no endereço).'); return; }
    adicionar([v]);
  });
});
</script>
<?php admin_rodape();
