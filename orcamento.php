<?php
/** Orçamento para o cliente: abre pelo link com o código e baixa em PDF. */
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require RAIZ . '/src/orcamentos.php';

$o = carregar_orcamento('token', preg_replace('/[^a-f0-9]/', '', (string) ($_GET['t'] ?? '')));
if (!$o) {
    http_response_code(404);
    exit('Orçamento não encontrado.');
}

// Botões de edição só para quem está logado no painel
$admin = false;
if (isset($_COOKIE['catalogo_admin'])) {
    session_name('catalogo_admin');
    session_start(['read_and_close' => true]);
    $admin = !empty($_SESSION['admin']);
}

$empresa = cfg('nome_empresa');
$contato = array_filter([cfg('whatsapp') ? 'WhatsApp ' . cfg('whatsapp') : '', cfg('telefone'), cfg('email')]);
$arquivo = 'Orcamento-' . $o['numero'] . '-' . preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $o['cliente_nome']) ?: 'cliente');
$base = url_base();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Orçamento <?= e($o['numero']) ?> | <?= e($empresa) ?></title>
<link rel="stylesheet" href="<?= $base ?>assets/estilo.css?v=3">
<style>:root{--primaria:<?= e(cfg('cor_primaria') ?: '#0b5cab') ?>}</style>
</head>
<body class="pg-orcamento">

<div class="barra-pdf nao-imprimir">
  <strong>Orçamento nº <?= e($o['numero']) ?></strong>
  <button class="btn" type="button" id="btn-pdf">Baixar PDF</button>
  <button class="btn btn-claro" type="button" onclick="window.print()">Imprimir</button>
  <?php if ($admin): ?>
    <a class="btn btn-claro" href="<?= $base ?>admin/orcamento.php?id=<?= (int) $o['id'] ?>">Editar</a>
    <?php if ($o['cliente_telefone']): ?>
      <a class="btn btn-whats" target="_blank" rel="noopener" href="<?= e(link_whatsapp(mensagem_orcamento($o), $o['cliente_telefone'])) ?>">Enviar por WhatsApp</a>
    <?php endif; ?>
  <?php elseif (cfg('whatsapp')): ?>
    <a class="btn btn-whats" target="_blank" rel="noopener" href="<?= e(link_whatsapp("Olá! Sobre o orçamento nº {$o['numero']}:")) ?>">Falar no WhatsApp</a>
  <?php endif; ?>
</div>

<article class="folha" id="folha">
  <header class="folha-topo">
    <div class="folha-empresa">
      <?php if (cfg('logo_url')): ?><img src="<?= e(logo_src()) ?>" alt="" class="folha-logo"><?php endif; ?>
      <div>
        <strong><?= e($empresa) ?></strong>
        <?php if ($contato): ?><span><?= e(implode(' · ', $contato)) ?></span><?php endif; ?>
        <?php if (cfg('endereco')): ?><span><?= e(cfg('endereco')) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="folha-num">
      <span>ORÇAMENTO</span>
      <strong>Nº <?= e($o['numero']) ?></strong>
      <span>Emitido em <?= e(date('d/m/Y', strtotime($o['criado_em']))) ?></span>
      <span>Válido até <?= e($o['validade']) ?></span>
    </div>
  </header>

  <section class="folha-cliente">
    <span class="rotulo">Cliente</span>
    <strong><?= e($o['cliente_nome']) ?></strong>
    <span><?= e(implode(' · ', array_filter([$o['cliente_documento'], $o['cliente_telefone'], $o['cliente_email']]))) ?></span>
  </section>

  <table class="folha-itens">
    <thead><tr><th colspan="2">Produto</th><th>Qtd.</th><th>Valor</th></tr></thead>
    <tbody>
      <?php foreach ($o['itens'] as $i): ?>
        <tr>
          <td class="folha-foto"><?php if ($i['imagem']): ?><img src="<?= e(img_url($i['imagem'], 120)) ?>" alt=""><?php endif; ?></td>
          <td><strong><?= e($i['titulo']) ?></strong>
            <?php if ($i['detalhes']): ?><div class="folha-detalhes"><?= nl2br(e($i['detalhes'])) ?></div><?php endif; ?>
            <?php if ((int) $i['prazo_dias']): ?><div class="folha-detalhes">Produção: <?= (int) $i['prazo_dias'] ?> <?= (int) $i['prazo_dias'] === 1 ? 'dia útil' : 'dias úteis' ?></div><?php endif; ?>
          </td>
          <td class="num"><?= number_format((int) $i['quantidade'], 0, ',', '.') ?> <?= e($i['unidade']) ?></td>
          <td class="num"><?= moeda((float) $i['valor']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <section class="folha-totais">
    <div><span>Subtotal</span><span><?= moeda($o['subtotal']) ?></span></div>
    <div><span>Frete<?= $o['frete_descricao'] ? ' — ' . e($o['frete_descricao']) : '' ?></span><span><?= (float) $o['frete_valor'] ? moeda((float) $o['frete_valor']) : 'sem custo' ?></span></div>
    <?php if ((float) $o['desconto']): ?><div><span>Desconto</span><span>− <?= moeda((float) $o['desconto']) ?></span></div><?php endif; ?>
    <div class="folha-total"><span>Total</span><span><?= moeda($o['total']) ?></span></div>
  </section>

  <section class="folha-condicoes">
    <?php if ($o['prazo_entrega'] || $o['prazo_producao']): ?>
      <p><strong>Prazo:</strong> <?= $o['prazo_producao'] ? 'até ' . $o['prazo_producao'] . ' dias úteis de produção. ' : '' ?><?= e($o['prazo_entrega']) ?></p>
    <?php endif; ?>
    <?php if ($o['pagamento']): ?><p><strong>Pagamento:</strong> <?= nl2br(e($o['pagamento'])) ?></p><?php endif; ?>
    <?php if ($o['observacoes']): ?><p><strong>Observações:</strong> <?= nl2br(e($o['observacoes'])) ?></p><?php endif; ?>
    <p class="mudo">Orçamento válido até <?= e($o['validade']) ?>. Após este prazo, os valores podem ser revistos.</p>
  </section>
</article>

<script src="<?= $base ?>assets/vendor/html2pdf.bundle.min.js"></script>
<script>
document.getElementById('btn-pdf').addEventListener('click', function () {
  var b = this; b.disabled = true; b.textContent = 'Gerando PDF…';
  html2pdf().set({
    margin: [8, 8, 8, 8],
    filename: <?= json_encode($arquivo . '.pdf') ?>,
    image: { type: 'jpeg', quality: 0.95 },
    html2canvas: { scale: 2, useCORS: true },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
    pagebreak: { mode: ['css', 'legacy'], avoid: 'tr' }
  }).from(document.getElementById('folha')).save().then(function () {
    b.disabled = false; b.textContent = 'Baixar PDF';
  });
});
</script>
</body>
</html>
