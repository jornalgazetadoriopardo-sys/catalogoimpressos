<?php
declare(strict_types=1);

function topo(string $titulo, string $classeBody = ''): void
{
    $empresa = cfg('nome_empresa');
    $base = url_base();
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo === $empresa ? $empresa : "$titulo | $empresa") ?></title>
<meta name="description" content="<?= e(cfg('slogan')) ?>">
<link rel="stylesheet" href="<?= $base ?>assets/estilo.css?v=1">
<style>:root{--primaria:<?= e(cfg('cor_primaria') ?: '#0b5cab') ?>}</style>
</head>
<body class="<?= e($classeBody) ?>">
<header class="topo">
  <div class="container topo-linha">
    <a class="marca" href="<?= $base ?>">
      <?php if (cfg('logo_url')): ?><img src="<?= e(cfg('logo_url')) ?>" alt="<?= e($empresa) ?>"><?php endif; ?>
      <span><strong><?= e($empresa) ?></strong><small><?= e(cfg('slogan')) ?></small></span>
    </a>
    <form class="busca" action="<?= $base ?>" method="get">
      <input type="search" name="q" placeholder="Buscar produto…" value="<?= e($_GET['q'] ?? '') ?>" aria-label="Buscar produto">
    </form>
    <?php if (cfg('whatsapp')): ?>
      <a class="btn btn-whats" href="<?= e(link_whatsapp('Olá! Vim pelo catálogo e gostaria de um orçamento.')) ?>" target="_blank" rel="noopener">WhatsApp</a>
    <?php endif; ?>
  </div>
</header>
<main class="container">
<?php
}

function rodape(): void
{
    $itens = array_filter([cfg('telefone'), cfg('email'), cfg('endereco')]);
    ?>
</main>
<footer class="rodape">
  <div class="container">
    <p><strong><?= e(cfg('nome_empresa')) ?></strong><?= $itens ? ' · ' . e(implode(' · ', $itens)) : '' ?></p>
    <p class="mudo"><?= e(cfg('texto_rodape')) ?></p>
  </div>
</footer>
</body>
</html>
<?php
}

/** Categorias visíveis que têm produtos visíveis. */
function categorias_publicas(): array
{
    return db_todos(
        'SELECT c.slug, c.nome, COUNT(p.slug) total FROM categorias c
         JOIN produtos p ON p.categoria_slug = c.slug AND p.visivel = 1 AND p.ativo_origem = 1
         WHERE c.visivel = 1 GROUP BY c.slug, c.nome, c.ordem ORDER BY c.ordem, c.nome'
    );
}

/** Filtro SQL comum dos produtos exibidos ao público. */
const SQL_PRODUTO_PUBLICO = 'p.visivel = 1 AND p.ativo_origem = 1 AND (c.visivel = 1 OR c.slug IS NULL)';

/**
 * Produtos públicos com o menor custo ("a partir de") já convertido em preço de venda.
 * Como a margem é a mesma para todas as combinações do produto, o menor custo dá o menor preço.
 */
function produtos_publicos(string $where = '', array $params = [], string $limite = ''): array
{
    $linhas = db_todos(
        'SELECT p.slug, p.nome, p.imagem, p.descricao, p.margem, p.destaque, p.categoria_slug,
                c.nome categoria_nome, c.margem margem_categoria, MIN(pr.custo) custo_min
         FROM produtos p
         LEFT JOIN categorias c ON c.slug = p.categoria_slug
         JOIN combinacoes cb ON cb.produto_slug = p.slug
         JOIN precos pr ON pr.combinacao_id = cb.id
         WHERE ' . SQL_PRODUTO_PUBLICO . ($where ? " AND ($where)" : '') . '
         GROUP BY p.slug, p.nome, p.imagem, p.descricao, p.margem, p.destaque, p.categoria_slug, c.nome, c.margem, c.ordem
         ORDER BY c.ordem, c.nome, p.destaque DESC, p.nome ' . $limite,
        $params
    );
    foreach ($linhas as &$l) {
        $l['margem_efetiva'] = margem_efetiva($l['margem'], $l['margem_categoria']);
        $l['preco_min'] = preco_venda((float) $l['custo_min'], $l['margem_efetiva']);
    }
    return $linhas;
}

/** Combinações de um produto com os preços de venda calculados. */
function combinacoes_do_produto(array $produto): array
{
    $margem = margem_efetiva($produto['margem'], $produto['margem_categoria']);
    $combs = [];
    foreach (db_todos('SELECT * FROM combinacoes WHERE produto_slug = ? ORDER BY id', [$produto['slug']]) as $c) {
        $combs[$c['id']] = [
            'id'      => (int) $c['id'],
            'specs'   => json_decode((string) $c['especificacoes'], true) ?: [],
            'prazo'   => prazo_producao((int) $c['prazo_dias']),
            'imagem'  => $c['imagem'],
            'extras'  => array_map(fn($a) => ['nome' => $a['nome'], 'dias' => $a['dias']], json_decode((string) $c['adicionais'], true) ?: []),
            'precos'  => [],
        ];
    }
    if (!$combs) {
        return [];
    }
    $ids = implode(',', array_keys($combs));
    foreach (db_todos("SELECT combinacao_id, quantidade, unidade, custo FROM precos WHERE combinacao_id IN ($ids) ORDER BY quantidade") as $p) {
        $combs[$p['combinacao_id']]['precos'][] = [
            'qtd'   => (int) $p['quantidade'],
            'un'    => $p['unidade'],
            'preco' => preco_venda((float) $p['custo'], $margem),
        ];
    }
    return array_values(array_filter($combs, fn($c) => $c['precos']));
}

/** Remove menções ao fornecedor nos textos exibidos ao cliente. */
function texto_publico(string $t): string
{
    return trim(preg_replace('/\s*(da |na |pela )?(Gr[áa]fica )?Futura ?IM\b/iu', '', $t));
}
