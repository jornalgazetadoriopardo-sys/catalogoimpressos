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

function admin_topo(string $titulo, string $ativo, string $acoes = ''): void
{
    $menu = [
        'index.php'         => 'Início',
        'orcamentos.php'    => 'Orçamentos',
        'produtos.php'      => 'Produtos',
        'adicionar.php'     => 'Adicionar produtos',
        'configuracoes.php' => 'Configurações',
    ];
    $grupoConfig = ['configuracoes.php', 'frete.php', 'categorias.php'];
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($titulo) ?> | Admin</title>
<link rel="stylesheet" href="../assets/estilo.css?v=3">
<script>window.ADMIN = { csrf: <?= json_encode(token_csrf()) ?>, continuarFila: <?= db_valor("SELECT 1 FROM fila WHERE status = 'pendente' LIMIT 1") ? 'true' : 'false' ?> };</script>
<script src="../assets/admin.js?v=2" defer></script>
</head>
<body class="admin">
<header class="admin-topo"><div class="container">
  <a class="admin-marca" href="index.php"><?= e(cfg('nome_empresa')) ?></a>
  <nav class="admin-menu">
    <?php foreach ($menu as $arq => $nome):
        $on = $ativo === $arq || ($arq === 'configuracoes.php' && in_array($ativo, $grupoConfig, true))
            || ($arq === 'orcamentos.php' && $ativo === 'orcamento.php'); ?>
      <a href="<?= $arq ?>" class="<?= $on ? 'ativo' : '' ?>"><?= $nome ?></a>
    <?php endforeach; ?>
  </nav>
  <a href="../" target="_blank">Ver catálogo ↗</a>
  <a class="sair" href="sair.php">Sair</a>
</div></header>
<main class="container">
<div class="admin-titulo"><h1><?= e($titulo) ?></h1><?= $acoes ?></div>
<?= flash() ?>
<?php
}

/** Abas da área de configurações. */
function abas_configuracoes(string $ativa): void
{
    $abas = ['configuracoes.php' => 'Geral', 'frete.php' => 'Frete por região', 'categorias.php' => 'Categorias'];
    echo '<nav class="abas">';
    foreach ($abas as $arq => $nome) {
        echo '<a href="' . $arq . '" class="' . ($ativa === $arq ? 'ativo' : '') . '">' . $nome . '</a>';
    }
    echo '</nav>';
}

/** Painel que mostra o andamento de "adicionar produtos" / "atualizar valores". */
function painel_progresso(): string
{
    return '<div class="painel progresso" id="progresso" hidden>
      <div class="progresso-topo"><strong id="progresso-titulo">Lendo o site da gráfica…</strong><span id="progresso-numeros"></span></div>
      <div class="barra"><div id="progresso-barra"></div></div>
      <p class="mudo" id="progresso-dica">Pode levar alguns minutos. Mantenha esta página aberta.</p>
      <details><summary>Detalhes</summary><div class="log" id="progresso-log"></div></details>
    </div>';
}

function admin_rodape(): void
{
    echo "</main>\n</body>\n</html>\n";
}
