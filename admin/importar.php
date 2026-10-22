<?php
declare(strict_types=1);

require __DIR__ . '/_base.php';
require RAIZ . '/src/importador.php';
exigir_login();

// Chamadas AJAX: iniciar e processar um lote (o navegador repete até acabar)
if (post_valido() && isset($_POST['acao'])) {
    session_write_close();
    header('Content-Type: application/json; charset=utf-8');
    @set_time_limit(120);
    $log = [];
    try {
        if ($_POST['acao'] === 'iniciar') {
            $log[] = 'Sitemap lido: ' . iniciar_sincronizacao() . ' produtos na fila.';
        }
        $r = processar_fila(25, function ($m) use (&$log) { $log[] = date('H:i:s') . '  ' . $m; });
        echo json_encode(['ok' => true, 'resumo' => $r, 'log' => $log], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $ex) {
        echo json_encode(['ok' => false, 'erro' => $ex->getMessage(), 'log' => $log], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$r = resumo_fila();
$erros = db_todos("SELECT url, erro FROM fila WHERE status = 'erro' ORDER BY url LIMIT 50");
$cronCli = '/usr/bin/php ' . RAIZ . '/cron/sincronizar.php';
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$cronUrl = $https . '://' . ($_SERVER['HTTP_HOST'] ?? 'seusite.com.br') . url_base() . 'cron/sincronizar.php?chave=' . urlencode((string) config('chave_cron'));

admin_topo('Sincronização de produtos', 'importar.php');
?>
<div class="painel">
  <div class="numeros">
    <div><strong id="n-pendente"><?= $r['pendente'] ?></strong>páginas na fila</div>
    <div><strong id="n-ok"><?= $r['ok'] ?></strong>páginas lidas</div>
    <div><strong id="n-erro"><?= $r['erro'] ?></strong>com erro</div>
    <div><strong id="n-status" style="font-size:16px"><?= $r['em_andamento'] ? 'Em andamento' : 'Parada' ?></strong>
      <?= $r['inicio'] ? 'iniciada em ' . e(date('d/m H:i', strtotime($r['inicio']))) : '' ?>
      <?= $r['fim'] ? '<br>concluída em ' . e(date('d/m H:i', strtotime($r['fim']))) : '' ?></div>
  </div>
  <p class="mudo">A sincronização lê o sitemap do fornecedor e depois cada combinação de cada produto (material, formato, acabamento…), com a tabela de preços por quantidade.
    A primeira leitura completa leva algumas horas. O ideal é deixar o <strong>cron</strong> da hospedagem fazendo isso sozinho (veja abaixo).
    Você também pode acompanhar por aqui, mas precisa deixar esta página aberta.</p>
  <div class="acoes">
    <button class="btn" id="btn-continuar" type="button" <?= $r['em_andamento'] ? '' : 'hidden' ?>>Continuar por aqui</button>
    <button class="btn btn-claro" id="btn-iniciar" type="button">Iniciar nova sincronização</button>
    <button class="btn btn-claro" id="btn-parar" type="button" hidden>Pausar</button>
  </div>
  <div class="log" id="log" hidden></div>
</div>

<div class="painel">
  <h2>Sincronização automática (cron)</h2>
  <p>No hPanel da Hostinger: <strong>Avançado → Cron Jobs</strong>. Crie um cron de <strong>10 em 10 minutos</strong> com o comando:</p>
  <pre class="log"><?= e($cronCli) ?></pre>
  <p class="mudo">Se a hospedagem pedir uma URL em vez de um comando, use:</p>
  <pre class="log"><?= e('wget -q -O /dev/null "' . $cronUrl . '"') ?></pre>
  <p class="mudo">Cada execução continua de onde a anterior parou. Concluída a leitura, uma nova só começa após <?= (int) cfg('intervalo_sync_horas') ?> horas (<a href="configuracoes.php">alterar</a>).</p>
</div>

<?php if ($erros): ?>
<div class="painel">
  <h2>Páginas com erro</h2>
  <div class="rolagem"><table class="tabela"><tbody>
    <?php foreach ($erros as $er): ?><tr><td><?= e($er['url']) ?></td><td class="mudo"><?= e($er['erro']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<script>
(function () {
  var csrf = <?= json_encode(token_csrf()) ?>, rodando = false;
  var log = document.getElementById('log');
  function escrever(linhas) {
    log.hidden = false;
    linhas.forEach(function (l) { log.textContent += l + '\n'; });
    log.scrollTop = log.scrollHeight;
  }
  function lote(acao) {
    if (!rodando) return;
    var corpo = new FormData();
    corpo.append('csrf', csrf);
    corpo.append('acao', acao);
    fetch('importar.php', { method: 'POST', body: corpo })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        escrever(d.log || []);
        if (!d.ok) { escrever(['ERRO: ' + d.erro]); parar(); return; }
        ['pendente', 'ok', 'erro'].forEach(function (k) { document.getElementById('n-' + k).textContent = d.resumo[k]; });
        if (d.resumo.em_andamento) { lote('lote'); }
        else { escrever(['Sincronização concluída.']); document.getElementById('n-status').textContent = 'Concluída'; parar(); }
      })
      .catch(function (e) { escrever(['Falha de conexão, tentando de novo em 10 s…']); setTimeout(function () { lote('lote'); }, 10000); });
  }
  function iniciar(acao) {
    rodando = true;
    document.getElementById('btn-parar').hidden = false;
    document.getElementById('btn-iniciar').disabled = true;
    document.getElementById('btn-continuar').disabled = true;
    document.getElementById('n-status').textContent = 'Em andamento';
    lote(acao);
  }
  function parar() {
    rodando = false;
    document.getElementById('btn-parar').hidden = true;
    document.getElementById('btn-iniciar').disabled = false;
    document.getElementById('btn-continuar').disabled = false;
  }
  document.getElementById('btn-continuar').onclick = function () { iniciar('lote'); };
  document.getElementById('btn-iniciar').onclick = function () {
    if (confirm('Começar uma nova leitura completa do site do fornecedor?')) iniciar('iniciar');
  };
  document.getElementById('btn-parar').onclick = function () { parar(); escrever(['Pausado. O cron continua de onde parou.']); };
})();
</script>
<?php admin_rodape();
