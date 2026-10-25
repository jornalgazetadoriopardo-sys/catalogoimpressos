<?php
declare(strict_types=1);

require __DIR__ . '/_base.php';

if (!admin_logado()) {
    $erro = '';
    if (post_valido()) {
        $okUsuario = hash_equals((string) config('admin_usuario'), (string) ($_POST['usuario'] ?? ''));
        $okSenha = hash_equals((string) config('admin_senha'), (string) ($_POST['senha'] ?? ''));
        if ($okUsuario && $okSenha) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            voltar('index.php');
        }
        sleep(1);
        $erro = 'Usuário ou senha incorretos.';
    }
    ?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title>Entrar | Admin</title><link rel="stylesheet" href="../assets/estilo.css?v=3"></head>
<body><form class="painel login" method="post">
  <h2>Painel do catálogo</h2>
  <?php if ($erro): ?><div class="msg erro"><?= e($erro) ?></div><?php endif; ?>
  <?= campo_csrf() ?>
  <div class="campo"><label for="usuario">Usuário</label><input id="usuario" name="usuario" autocomplete="username" required></div>
  <div class="campo" style="margin-top:10px"><label for="senha">Senha</label><input id="senha" name="senha" type="password" autocomplete="current-password" required></div>
  <div class="acoes"><button class="btn" type="submit">Entrar</button></div>
</form></body></html>
<?php
    exit;
}

require RAIZ . '/src/orcamentos.php';

$n = [
    'produtos'    => (int) db_valor('SELECT COUNT(*) FROM produtos'),
    'visiveis'    => (int) db_valor('SELECT COUNT(*) FROM produtos p LEFT JOIN categorias c ON c.slug = p.categoria_slug WHERE p.visivel = 1 AND (c.visivel = 1 OR c.slug IS NULL)'),
    'orcamentos'  => (int) db_valor('SELECT COUNT(*) FROM orcamentos'),
];
$ultima = cfg('ultima_atualizacao');
$recentes = db_todos(
    'SELECT o.id, o.cliente_nome, o.criado_em, o.frete_valor, o.desconto,
            (SELECT COALESCE(SUM(i.valor), 0) FROM orcamento_itens i WHERE i.orcamento_id = o.id) subtotal
     FROM orcamentos o ORDER BY o.id DESC LIMIT 5'
);

admin_topo('Olá! O que vamos fazer hoje?', 'index.php');
?>
<div class="atalhos">
  <a class="atalho" href="orcamento.php"><span class="atalho-icone">📝</span><strong>Novo orçamento</strong><small>Monte e envie em PDF</small></a>
  <a class="atalho" href="adicionar.php"><span class="atalho-icone">➕</span><strong>Adicionar produtos</strong><small>Escolha no site da gráfica</small></a>
  <a class="atalho" href="#" data-fila="atualizar" data-titulo="Atualizando valores de todos os produtos…"
     data-confirmar="Buscar os preços atuais de todos os produtos no site da gráfica?"><span class="atalho-icone">↻</span><strong>Atualizar valores</strong>
     <small><?= $ultima ? 'Última: ' . e(date('d/m/Y H:i', strtotime($ultima))) : 'Ainda não atualizado' ?></small></a>
  <a class="atalho" href="../" target="_blank"><span class="atalho-icone">🛍️</span><strong>Ver catálogo</strong><small><?= $n['visiveis'] ?> produtos à venda</small></a>
</div>

<?= painel_progresso() ?>

<?php if (!$n['produtos']): ?>
  <div class="painel passos">
    <h2>Primeiros passos</h2>
    <ol>
      <li><a href="configuracoes.php">Configure</a> o nome da empresa, logo, WhatsApp e a margem de lucro.</li>
      <li><a href="adicionar.php">Adicione os produtos</a> que você quer vender.</li>
      <li>Compartilhe o <a href="../" target="_blank">catálogo</a> e crie <a href="orcamento.php">orçamentos</a>.</li>
    </ol>
  </div>
<?php endif; ?>

<div class="painel">
  <div class="lista-topo"><h2>Últimos orçamentos</h2><a href="orcamentos.php">ver todos (<?= $n['orcamentos'] ?>)</a></div>
  <?php if (!$recentes): ?>
    <p class="mudo">Nenhum orçamento ainda.</p>
  <?php else: ?>
    <table class="tabela">
      <tbody>
        <?php foreach ($recentes as $o): ?>
          <tr>
            <td><strong><?= orcamento_numero((int) $o['id']) ?></strong></td>
            <td><a href="orcamento.php?id=<?= (int) $o['id'] ?>"><?= e($o['cliente_nome']) ?></a></td>
            <td><?= e(date('d/m/Y', strtotime($o['criado_em']))) ?></td>
            <td><strong><?= moeda(max(0, (float) $o['subtotal'] + (float) $o['frete_valor'] - (float) $o['desconto'])) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php admin_rodape();
