<?php
/**
 * Catálogo para impressão / PDF. No navegador: "Imprimir" > "Salvar como PDF".
 *   catalogo.php                         todas as categorias, modo resumido
 *   catalogo.php?categoria[]=banner      só as categorias escolhidas
 *   catalogo.php?modo=completo           tabela de preços por combinação
 */
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require RAIZ . '/src/layout.php';

$modo = ($_GET['modo'] ?? '') === 'completo' ? 'completo' : 'resumo';
$catsSel = array_values(array_filter((array) ($_GET['categoria'] ?? []), 'is_string'));
$todasCats = categorias_publicas();

$where = '';
$params = [];
if ($catsSel) {
    $where = 'p.categoria_slug IN (' . implode(',', array_fill(0, count($catsSel), '?')) . ')';
    $params = $catsSel;
}
$produtos = produtos_publicos($where, $params);
$porCategoria = [];
foreach ($produtos as $p) {
    $porCategoria[$p['categoria_nome'] ?? 'Outros'][] = $p;
}

/** Para o modo completo: só os atributos que variam entre as combinações. */
function linhas_completas(array $produto): array
{
    $combs = combinacoes_do_produto($produto);
    $valores = [];
    foreach ($combs as $c) {
        foreach ($c['specs'] as $k => $v) {
            $valores[$k][$v] = true;
        }
    }
    $variam = array_keys(array_filter($valores, fn($v) => count($v) > 1));
    $fixos = [];
    foreach ($valores as $k => $v) {
        if (!in_array($k, $variam, true)) {
            $fixos[$k] = array_key_first($v);
        }
    }
    $qtds = [];
    foreach ($combs as $c) {
        foreach ($c['precos'] as $p) {
            $qtds[$p['qtd']] = ($qtds[$p['qtd']] ?? 0) + 1;
        }
    }
    arsort($qtds);
    $colunas = array_slice(array_keys($qtds), 0, 6);
    sort($colunas);
    $linhas = [];
    foreach ($combs as $c) {
        $porQtd = array_column($c['precos'], 'preco', 'qtd');
        $linhas[] = [
            'desc'   => implode(' · ', array_map(fn($k) => $c['specs'][$k] ?? '—', $variam)),
            'prazo'  => $c['prazo'],
            'precos' => array_map(fn($q) => $porQtd[$q] ?? null, $colunas),
        ];
    }
    usort($linhas, fn($a, $b) => strnatcasecmp($a['desc'], $b['desc']));
    return ['variam' => $variam, 'fixos' => $fixos, 'colunas' => $colunas, 'linhas' => $linhas];
}

$base = url_base();
$empresa = cfg('nome_empresa');
$regioes = db_todos('SELECT * FROM frete_regioes ORDER BY valor, nome');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Catálogo | <?= e($empresa) ?></title>
<link rel="stylesheet" href="<?= $base ?>assets/estilo.css?v=1">
<style>:root{--primaria:<?= e(cfg('cor_primaria') ?: '#0b5cab') ?>}</style>
</head>
<body class="pg-catalogo">

<form class="barra-pdf nao-imprimir" method="get">
  <strong>Catálogo em PDF</strong>
  <details>
    <summary>Categorias (<?= $catsSel ? count($catsSel) : 'todas' ?>)</summary>
    <div class="lista-check">
      <?php foreach ($todasCats as $c): ?>
        <label><input type="checkbox" name="categoria[]" value="<?= e($c['slug']) ?>" <?= in_array($c['slug'], $catsSel, true) ? 'checked' : '' ?>> <?= e($c['nome']) ?> (<?= (int) $c['total'] ?>)</label>
      <?php endforeach; ?>
    </div>
  </details>
  <label>Formato
    <select name="modo">
      <option value="resumo" <?= $modo === 'resumo' ? 'selected' : '' ?>>Resumido (a partir de)</option>
      <option value="completo" <?= $modo === 'completo' ? 'selected' : '' ?>>Completo (tabelas de preço)</option>
    </select>
  </label>
  <button class="btn btn-claro" type="submit">Atualizar</button>
  <button class="btn" type="button" onclick="window.print()">Imprimir / Salvar PDF</button>
  <?php if ($modo === 'completo' && !$catsSel): ?><span class="aviso">O modo completo com todas as categorias gera um PDF muito grande. Escolha algumas categorias.</span><?php endif; ?>
</form>

<section class="capa">
  <?php if (cfg('logo_url')): ?><img src="<?= e(cfg('logo_url')) ?>" alt=""><?php endif; ?>
  <h1><?= e($empresa) ?></h1>
  <p><?= e(cfg('slogan')) ?></p>
  <p class="mudo">Tabela válida em <?= date('d/m/Y') ?></p>
  <p><?= e(implode(' · ', array_filter([cfg('whatsapp') ? 'WhatsApp ' . cfg('whatsapp') : '', cfg('telefone'), cfg('email')]))) ?></p>
</section>

<?php foreach ($porCategoria as $nomeCat => $lista): ?>
  <section class="cat-pdf">
    <h2><?= e($nomeCat) ?></h2>
    <?php if ($modo === 'resumo'): ?>
      <div class="grade-pdf">
        <?php foreach ($lista as $p): ?>
          <div class="item-pdf">
            <img src="<?= e($p['imagem']) ?>" alt="" loading="lazy">
            <div><h3><?= e($p['nome']) ?></h3><p class="preco"><span>a partir de</span> <?= moeda($p['preco_min']) ?></p></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <?php foreach ($lista as $p): $t = linhas_completas($p); ?>
        <div class="produto-pdf">
          <div class="produto-pdf-topo">
            <img src="<?= e($p['imagem']) ?>" alt="" loading="lazy">
            <div>
              <h3><?= e($p['nome']) ?></h3>
              <p class="mudo"><?= e(implode(' · ', array_map(fn($k, $v) => "$k: $v", array_keys($t['fixos']), $t['fixos']))) ?></p>
            </div>
          </div>
          <table class="tabela-pdf">
            <thead><tr><th><?= e(implode(' / ', $t['variam']) ?: 'Opção') ?></th><th>Prazo</th>
              <?php foreach ($t['colunas'] as $q): ?><th><?= number_format($q, 0, ',', '.') ?> un.</th><?php endforeach; ?></tr></thead>
            <tbody>
              <?php foreach ($t['linhas'] as $l): ?>
                <tr><td><?= e($l['desc'] ?: 'Padrão') ?></td><td><?= (int) $l['prazo'] ?>d</td>
                  <?php foreach ($l['precos'] as $v): ?><td><?= $v === null ? '—' : moeda($v) ?></td><?php endforeach; ?></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<section class="condicoes">
  <h2>Condições</h2>
  <ul>
    <li>Prazos em dias úteis, contados a partir da aprovação da arte e do pagamento, mais o prazo de entrega.</li>
    <li><?= e(cfg('frete_texto')) ?><?php if ((float) cfg('frete_gratis_acima') > 0): ?> Frete grátis em pedidos a partir de <?= moeda((float) cfg('frete_gratis_acima')) ?>.<?php endif; ?></li>
    <?php if (cfg('retirada_ativa') === '1'): ?><li><?= e(cfg('retirada_texto')) ?></li><?php endif; ?>
    <li>Acabamentos extras (vincos, furos, cortes especiais) sob consulta.</li>
    <li><?= e(cfg('texto_rodape')) ?></li>
  </ul>
  <?php if ($regioes): ?>
    <table class="tabela-pdf frete-pdf">
      <thead><tr><th>Região de entrega</th><th>Frete</th><th>Prazo de entrega</th></tr></thead>
      <tbody><?php foreach ($regioes as $r): ?><tr><td><?= e($r['nome']) ?></td><td><?= moeda((float) $r['valor']) ?></td><td><?= (int) $r['prazo_dias'] ?> dias úteis</td></tr><?php endforeach; ?></tbody>
    </table>
  <?php endif; ?>
</section>
</body>
</html>
