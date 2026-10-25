<?php
declare(strict_types=1);

require __DIR__ . '/_base.php';
require RAIZ . '/src/orcamentos.php';
exigir_login();

if (post_valido() && ($_POST['acao'] ?? '') === 'excluir') {
    $id = (int) $_POST['id'];
    db_exec('DELETE FROM orcamento_itens WHERE orcamento_id = ?', [$id]);
    db_exec('DELETE FROM orcamentos WHERE id = ?', [$id]);
    flash('Orçamento excluído.');
    voltar('orcamentos.php');
}

$busca = trim((string) ($_GET['q'] ?? ''));
$params = [];
$where = '';
if ($busca !== '') {
    $where = 'WHERE o.cliente_nome LIKE ? OR o.cliente_telefone LIKE ? OR o.id = ?';
    $params = ["%$busca%", "%$busca%", (int) ltrim($busca, '0#')];
}
$lista = db_todos(
    "SELECT o.id, o.token, o.cliente_nome, o.cliente_telefone, o.criado_em, o.validade_dias, o.frete_valor, o.desconto,
            (SELECT COALESCE(SUM(i.valor), 0) FROM orcamento_itens i WHERE i.orcamento_id = o.id) subtotal,
            (SELECT COUNT(*) FROM orcamento_itens i WHERE i.orcamento_id = o.id) itens
     FROM orcamentos o $where ORDER BY o.id DESC LIMIT 200",
    $params
);

admin_topo('Orçamentos', 'orcamentos.php', '<a class="btn" href="orcamento.php">+ Novo orçamento</a>');
?>
<form class="painel filtros" method="get">
  <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Buscar por cliente, telefone ou número" style="flex:1">
  <button class="btn btn-claro" type="submit">Buscar</button>
</form>

<div class="painel">
  <?php if (!$lista): ?>
    <div class="vazio-admin">Nenhum orçamento<?= $busca !== '' ? ' encontrado' : ' ainda' ?>.<br><br><a class="btn" href="orcamento.php">Criar o primeiro orçamento</a></div>
  <?php else: ?>
    <div class="rolagem">
      <table class="tabela">
        <thead><tr><th>Nº</th><th>Cliente</th><th>Data</th><th>Validade</th><th>Itens</th><th>Total</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($lista as $o):
              $total = max(0, (float) $o['subtotal'] + (float) $o['frete_valor'] - (float) $o['desconto']);
              $vence = strtotime($o['criado_em'] . ' +' . (int) $o['validade_dias'] . ' days');
              $vencido = $vence < strtotime('today'); ?>
            <tr>
              <td><strong><?= orcamento_numero((int) $o['id']) ?></strong></td>
              <td><a href="orcamento.php?id=<?= (int) $o['id'] ?>"><?= e($o['cliente_nome']) ?></a><br><small class="mudo"><?= e($o['cliente_telefone']) ?></small></td>
              <td><?= e(date('d/m/Y', strtotime($o['criado_em']))) ?></td>
              <td class="<?= $vencido ? 'vencido' : '' ?>"><?= e(date('d/m/Y', $vence)) ?><?= $vencido ? ' (vencido)' : '' ?></td>
              <td><?= (int) $o['itens'] ?></td>
              <td><strong><?= moeda($total) ?></strong></td>
              <td class="acoes-linha">
                <a class="btn btn-claro btn-peq" href="../orcamento.php?t=<?= e($o['token']) ?>" target="_blank">Ver / PDF</a>
                <a class="btn btn-claro btn-peq" href="orcamento.php?id=<?= (int) $o['id'] ?>">Editar</a>
                <a class="btn btn-claro btn-peq" href="orcamento.php?duplicar=<?= (int) $o['id'] ?>">Duplicar</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php admin_rodape();
