<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

session_name('catalogo_admin');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);

function admin_logado(): bool
{
    return !empty($_SESSION['admin']);
}

function exigir_login(): void
{
    if (!admin_logado()) {
        header('Location: index.php');
        exit;
    }
}

function token_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . token_csrf() . '">';
}

/** Valida o token em todo POST. */
function post_valido(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals(token_csrf(), (string) ($_POST['csrf'] ?? ''));
}

function flash(?string $msg = null, bool $erro = false): string
{
    if ($msg !== null) {
        $_SESSION['flash'] = [$msg, $erro];
        return '';
    }
    if (empty($_SESSION['flash'])) {
        return '';
    }
    [$m, $e] = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="msg' . ($e ? ' erro' : '') . '">' . e($m) . '</div>';
}

function voltar(string $pagina): void
{
    header('Location: ' . $pagina);
    exit;
}

/** Converte o texto digitado num campo de margem: vazio = herdar. */
function margem_digitada($v): ?string
{
    $v = trim(str_replace(',', '.', (string) $v));
    return $v === '' ? null : (string) (float) $v;
}

function admin_topo(string $titulo, string $ativo): void
{
    $menu = [
        'index.php'         => 'Início',
        'configuracoes.php' => 'Configurações',
        'categorias.php'    => 'Categorias',
        'produtos.php'      => 'Produtos',
        'frete.php'         => 'Frete',
        'importar.php'      => 'Sincronização',
    ];
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($titulo) ?> | Admin</title>
<link rel="stylesheet" href="../assets/estilo.css?v=1">
</head>
<body>
<header class="admin-topo"><div class="container">
  <strong><?= e(cfg('nome_empresa')) ?></strong>
  <?php foreach ($menu as $arq => $nome): ?>
    <a href="<?= $arq ?>" class="<?= $ativo === $arq ? 'ativo' : '' ?>"><?= $nome ?></a>
  <?php endforeach; ?>
  <a href="../" target="_blank">Ver catálogo ↗</a>
  <a class="sair" href="sair.php">Sair</a>
</div></header>
<main class="container">
<h1><?= e($titulo) ?></h1>
<?= flash() ?>
<?php
}

function admin_rodape(): void
{
    echo "</main>\n</body>\n</html>\n";
}
