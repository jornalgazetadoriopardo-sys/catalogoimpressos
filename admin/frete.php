<?php
declare(strict_types=1);

require __DIR__ . '/_base.php';
exigir_login();

if (post_valido()) {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'excluir') {
        db_exec('DELETE FROM frete_regioes WHERE id = ?', [(int) $_POST['id']]);
        flash('Região excluída.');
    } elseif ($acao === 'salvar') {
        $ini = cep_limpo((string) $_POST['cep_inicio']);
        $fim = cep_limpo((string) $_POST['cep_fim']);
        $ini = str_pad($ini, 8, '0');
        $fim = str_pad($fim, 8, '9');
        if ($ini > $fim) {
            [$ini, $fim] = [$fim, $ini];
        }
        $dados = [
            trim((string) $_POST['nome']) ?: 'Região',
            $ini,
            $fim,
            (float) str_replace(',', '.', (string) $_POST['valor']),
            (int) $_POST['prazo_dias'],
        ];
        if (!empty($_POST['id'])) {
            db_exec('UPDATE frete_regioes SET nome = ?, cep_inicio = ?, cep_fim = ?, valor = ?, prazo_dias = ? WHERE id = ?', [...$dados, (int) $_POST['id']]);
        } else {
            db_exec('INSERT INTO frete_regioes (nome, cep_inicio, cep_fim, valor, prazo_dias) VALUES (?, ?, ?, ?, ?)', $dados);
        }
        flash('Região salva.');
    }
    voltar('frete.php');
}

$regioes = db_todos('SELECT * FROM frete_regioes ORDER BY cep_inicio');
$editar = isset($_GET['editar']) ? db_um('SELECT * FROM frete_regioes WHERE id = ?', [(int) $_GET['editar']]) : null;
$fmtCep = fn($c) => substr($c, 0, 5) . '-' . substr($c, 5);

admin_topo('Frete', 'frete.php');
?>
<div class="painel">
  <p>Cadastre faixas de CEP com valor e prazo de entrega. Quando um CEP cai em mais de uma faixa, vale a menor (mais específica).
    CEPs fora das faixas usam o frete padrão de <strong><?= moeda((float) cfg('frete_padrao_valor')) ?></strong> em <?= (int) cfg('frete_padrao_prazo') ?> dias úteis
    (<a href="configuracoes.php">alterar</a>).</p>
  <p class="mudo">Dica: só o começo do CEP já basta. Ex.: de <code>96</code> até <code>96</code> cobre todo o RS que começa com 96 (96000-000 a 96999-999). Para a cidade toda, use a faixa dela, ex. Rio Pardo: 96640-000 a 96649-999.</p>
  <div class="rolagem">
    <table class="tabela">
      <thead><tr><th>Região</th><th>CEP inicial</th><th>CEP final</th><th>Valor</th><th>Prazo</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($regioes as $r): ?>
          <tr>
            <td><?= e($r['nome']) ?></td><td><?= $fmtCep($r['cep_inicio']) ?></td><td><?= $fmtCep($r['cep_fim']) ?></td>
            <td><?= moeda((float) $r['valor']) ?></td><td><?= (int) $r['prazo_dias'] ?> dias úteis</td>
            <td>
              <a href="?editar=<?= (int) $r['id'] ?>">editar</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Excluir esta região?')">
                <?= campo_csrf() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="btn btn-claro" type="submit" style="padding:3px 8px">excluir</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$regioes): ?><tr><td colspan="6" class="mudo">Nenhuma região cadastrada; todos os CEPs usam o frete padrão.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<form method="post" class="painel">
  <h2><?= $editar ? 'Editar região' : 'Nova região' ?></h2>
  <?= campo_csrf() ?>
  <input type="hidden" name="acao" value="salvar">
  <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?: '' ?>">
  <div class="campos">
    <div class="campo"><label>Nome</label><input name="nome" required value="<?= e($editar['nome'] ?? '') ?>" placeholder="Ex.: Rio Pardo e região"></div>
    <div class="campo"><label>CEP inicial</label><input name="cep_inicio" required value="<?= e($editar ? $fmtCep($editar['cep_inicio']) : '') ?>" placeholder="96640-000"></div>
    <div class="campo"><label>CEP final</label><input name="cep_fim" required value="<?= e($editar ? $fmtCep($editar['cep_fim']) : '') ?>" placeholder="96649-999"></div>
    <div class="campo"><label>Valor (R$)</label><input name="valor" required inputmode="decimal" value="<?= e($editar['valor'] ?? '') ?>" placeholder="0,00"></div>
    <div class="campo"><label>Prazo de entrega (dias úteis)</label><input name="prazo_dias" type="number" min="0" required value="<?= e($editar['prazo_dias'] ?? '') ?>"></div>
  </div>
  <div class="acoes"><button class="btn" type="submit">Salvar região</button><?php if ($editar): ?> <a class="btn btn-claro" href="frete.php">Cancelar</a><?php endif; ?></div>
</form>
<?php admin_rodape();
