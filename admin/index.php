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
<meta name="robots" content="noindex"><title>Entrar | Admin</title><link rel="stylesheet" href="../assets/estilo.css?v=1"></head>
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

$n = [
    'produtos'   => (int) db_valor('SELECT COUNT(*) FROM produtos WHERE ativo_origem = 1'),
    'visiveis'   => (int) db_valor('SELECT COUNT(*) FROM produtos p LEFT JOIN categorias c ON c.slug = p.categoria_slug WHERE p.visivel = 1 AND p.ativo_origem = 1 AND (c.visivel = 1 OR c.slug IS NULL)'),
    'categorias' => (int) db_valor('SELECT COUNT(*) FROM categorias'),
    'combinacoes'=> (int) db_valor('SELECT COUNT(*) FROM combinacoes'),
    'precos'     => (int) db_valor('SELECT COUNT(*) FROM precos'),
];

admin_topo('Início', 'index.php');
?>
<div class="painel">
  <div class="numeros">
    <div><strong><?= $n['visiveis'] ?></strong>produtos no catálogo</div>
    <div><strong><?= $n['produtos'] ?></strong>produtos importados</div>
    <div><strong><?= $n['categorias'] ?></strong>categorias</div>
    <div><strong><?= number_format($n['combinacoes'], 0, ',', '.') ?></strong>combinações</div>
    <div><strong><?= number_format($n['precos'], 0, ',', '.') ?></strong>preços</div>
  </div>
  <p class="mudo">Margem padrão: <strong><?= e(cfg('margem_padrao')) ?>%</strong> ·
    Última sincronização concluída: <strong><?= cfg('sync_fim') ? e(date('d/m/Y H:i', strtotime(cfg('sync_fim')))) : 'nunca' ?></strong></p>
  <?php if ($n['produtos'] === 0): ?>
    <div class="msg">Nenhum produto importado ainda. Vá em <a href="importar.php">Sincronização</a> para importar os produtos.</div>
  <?php endif; ?>
  <div class="acoes">
    <a class="btn" href="../" target="_blank">Abrir catálogo</a>
    <a class="btn btn-claro" href="../catalogo.php" target="_blank">Catálogo em PDF</a>
  </div>
</div>
<?php admin_rodape();
