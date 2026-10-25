<?php
declare(strict_types=1);

require __DIR__ . '/_base.php';
require RAIZ . '/src/orcamentos.php';
exigir_login();

$campos = ['cliente_nome', 'cliente_telefone', 'cliente_email', 'cliente_documento', 'cliente_cep',
           'frete_descricao', 'prazo_entrega', 'pagamento', 'observacoes'];

if (post_valido()) {
    $id = (int) ($_POST['id'] ?? 0);
    $dados = [];
    foreach ($campos as $c) {
        $dados[$c] = trim((string) ($_POST[$c] ?? ''));
    }
    $dados['cliente_nome'] = $dados['cliente_nome'] ?: 'Cliente';
    $dados['frete_valor'] = max(0, numero_digitado($_POST['frete_valor'] ?? '0'));
    $dados['desconto'] = max(0, numero_digitado($_POST['desconto'] ?? '0'));
    $dados['validade_dias'] = max(1, (int) ($_POST['validade_dias'] ?? 7));
    $dados['atualizado_em'] = agora();

    db()->beginTransaction();
    if ($id && db_valor('SELECT 1 FROM orcamentos WHERE id = ?', [$id])) {
        $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($dados)));
        db_exec("UPDATE orcamentos SET $sets WHERE id = ?", [...array_values($dados), $id]);
        db_exec('DELETE FROM orcamento_itens WHERE orcamento_id = ?', [$id]);
    } else {
        $dados['token'] = bin2hex(random_bytes(16));
        $dados['criado_em'] = agora();
        db_exec('INSERT INTO orcamentos (' . implode(',', array_keys($dados)) . ') VALUES (' . implode(',', array_fill(0, count($dados), '?')) . ')', array_values($dados));
        $id = (int) db()->lastInsertId();
    }
    $ordem = 0;
    foreach ((array) ($_POST['itens'] ?? []) as $item) {
        $titulo = trim((string) ($item['titulo'] ?? ''));
        if ($titulo === '') {
            continue;
        }
        db_exec('INSERT INTO orcamento_itens (orcamento_id, ordem, produto_slug, imagem, titulo, detalhes, quantidade, unidade, valor, prazo_dias)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $id, $ordem++, ($item['produto_slug'] ?? '') ?: null, ($item['imagem'] ?? '') ?: null, mb_substr($titulo, 0, 255),
            trim((string) ($item['detalhes'] ?? '')), max(1, (int) ($item['quantidade'] ?? 1)), mb_substr(trim((string) ($item['unidade'] ?? '')), 0, 30),
            max(0, numero_digitado($item['valor'] ?? '0')), max(0, (int) ($item['prazo_dias'] ?? 0)),
        ]);
    }
    db()->commit();
    flash('Orçamento nº ' . orcamento_numero($id) . ' salvo.');
    voltar('orcamento.php?id=' . $id);
}

/** Aceita "1.234,56", "1234,56" ou "1234.56". */
function numero_digitado($v): float
{
    $v = trim((string) $v);
    if (str_contains($v, ',')) {
        $v = str_replace(['.', ','], ['', '.'], $v);
    }
    return round((float) $v, 2);
}

$orc = null;
if (isset($_GET['id'])) {
    $orc = carregar_orcamento('id', (int) $_GET['id']);
} elseif (isset($_GET['duplicar'])) {
    $orc = carregar_orcamento('id', (int) $_GET['duplicar']);
    if ($orc) {
        $orc['id'] = 0;
        $orc['cliente_nome'] .= ' (cópia)';
    }
}
$novo = !$orc || !$orc['id'];
$o = $orc ?? [
    'id' => 0, 'cliente_nome' => '', 'cliente_telefone' => '', 'cliente_email' => '', 'cliente_documento' => '', 'cliente_cep' => '',
    'frete_descricao' => '', 'frete_valor' => 0, 'desconto' => 0, 'validade_dias' => (int) cfg('orc_validade_dias'),
    'prazo_entrega' => cfg('orc_prazo_entrega'), 'pagamento' => cfg('orc_pagamento'), 'observacoes' => cfg('orc_observacoes'), 'itens' => [],
];
$itensJs = array_map(fn($i) => [
    'titulo' => $i['titulo'], 'detalhes' => (string) $i['detalhes'], 'quantidade' => (int) $i['quantidade'], 'unidade' => (string) $i['unidade'],
    'valor' => (float) $i['valor'], 'prazo_dias' => (int) $i['prazo_dias'], 'produto_slug' => (string) $i['produto_slug'],
    'imagem' => (string) $i['imagem'], 'miniatura' => $i['imagem'] ? img_url($i['imagem'], 120) : '',
], $o['itens']);
$produtos = db_todos('SELECT p.slug, p.nome, c.nome categoria FROM produtos p LEFT JOIN categorias c ON c.slug = p.categoria_slug ORDER BY c.nome, p.nome');

$acoes = '';
if (!$novo) {
    $acoes = '<div class="acoes" style="margin:0">'
        . '<a class="btn" href="../orcamento.php?t=' . e($o['token']) . '" target="_blank">Ver / Baixar PDF</a>'
        . ($o['cliente_telefone'] ? '<a class="btn btn-whats" target="_blank" rel="noopener" href="' . e(link_whatsapp(mensagem_orcamento($o), $o['cliente_telefone'])) . '">Enviar por WhatsApp</a>' : '')
        . '</div>';
}
admin_topo($novo ? 'Novo orçamento' : 'Orçamento nº ' . $o['numero'], 'orcamento.php', $acoes);
?>
<form method="post" id="form-orc" class="form-orc" autocomplete="off">
  <?= campo_csrf() ?>
  <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">

  <section class="painel">
    <h2>1. Cliente</h2>
    <div class="campos">
      <div class="campo"><label for="cliente_nome">Nome / empresa *</label><input id="cliente_nome" name="cliente_nome" required value="<?= e($o['cliente_nome']) ?>"></div>
      <div class="campo"><label for="cliente_telefone">WhatsApp / telefone</label><input id="cliente_telefone" name="cliente_telefone" inputmode="tel" value="<?= e($o['cliente_telefone']) ?>" placeholder="(51) 99999-9999"></div>
      <div class="campo"><label for="cliente_email">E-mail</label><input id="cliente_email" name="cliente_email" type="email" value="<?= e($o['cliente_email']) ?>"></div>
      <div class="campo"><label for="cliente_documento">CPF / CNPJ</label><input id="cliente_documento" name="cliente_documento" value="<?= e($o['cliente_documento']) ?>"></div>
    </div>
  </section>

  <section class="painel">
    <h2>2. Itens</h2>
    <div class="rolagem">
      <table class="tabela itens-orc">
        <thead><tr><th></th><th>Descrição</th><th>Qtd.</th><th>Valor (R$)</th><th>Prazo</th><th></th></tr></thead>
        <tbody id="itens"></tbody>
      </table>
    </div>
    <p class="mudo" id="sem-itens">Nenhum item ainda.</p>

    <div class="seletor" id="seletor">
      <div class="campo">
        <label for="busca-produto">Adicionar produto do catálogo</label>
        <input id="busca-produto" list="lista-produtos" placeholder="Digite o nome do produto…">
        <datalist id="lista-produtos">
          <?php foreach ($produtos as $p): ?><option value="<?= e($p['nome']) ?>" data-slug="<?= e($p['slug']) ?>"><?= e($p['categoria']) ?></option><?php endforeach; ?>
        </datalist>
        <?php if (!$produtos): ?><small>Nenhum produto cadastrado. <a href="adicionar.php">Adicione produtos</a> ou use um item avulso.</small><?php endif; ?>
      </div>
      <div id="opcoes-produto" class="campos" hidden></div>
      <div class="acoes">
        <button class="btn" type="button" id="btn-incluir" hidden>Incluir no orçamento</button>
        <button class="btn btn-claro" type="button" id="btn-avulso">+ Item avulso</button>
      </div>
    </div>
  </section>

  <section class="painel">
    <h2>3. Entrega e valores</h2>
    <div class="campos">
      <div class="campo"><label for="cliente_cep">CEP de entrega</label>
        <div class="frete-linha"><input id="cliente_cep" name="cliente_cep" inputmode="numeric" maxlength="9" value="<?= e($o['cliente_cep']) ?>" placeholder="00000-000">
          <button class="btn btn-claro" type="button" id="btn-frete">Calcular</button></div>
        <small><a href="#" id="btn-retirada">Retirada no local</a></small>
      </div>
      <div class="campo"><label for="frete_descricao">Entrega</label><input id="frete_descricao" name="frete_descricao" value="<?= e($o['frete_descricao']) ?>" placeholder="Ex.: Entrega em Rio Pardo"></div>
      <div class="campo"><label for="frete_valor">Frete (R$)</label><input id="frete_valor" name="frete_valor" inputmode="decimal" value="<?= e(number_format((float) $o['frete_valor'], 2, ',', '')) ?>"></div>
      <div class="campo"><label for="desconto">Desconto (R$)</label><input id="desconto" name="desconto" inputmode="decimal" value="<?= e(number_format((float) $o['desconto'], 2, ',', '')) ?>"></div>
    </div>
    <div class="totais">
      <div><span>Subtotal</span><strong id="t-subtotal">R$ 0,00</strong></div>
      <div><span>Frete</span><strong id="t-frete">R$ 0,00</strong></div>
      <div><span>Desconto</span><strong id="t-desconto">R$ 0,00</strong></div>
      <div class="total"><span>Total</span><strong id="t-total">R$ 0,00</strong></div>
    </div>
  </section>

  <section class="painel">
    <h2>4. Condições</h2>
    <div class="campos">
      <div class="campo"><label for="validade_dias">Validade (dias)</label><input id="validade_dias" name="validade_dias" type="number" min="1" value="<?= (int) $o['validade_dias'] ?>"></div>
      <div class="campo" style="grid-column:span 2"><label for="prazo_entrega">Prazo</label><input id="prazo_entrega" name="prazo_entrega" value="<?= e($o['prazo_entrega']) ?>"></div>
      <div class="campo" style="grid-column:1/-1"><label for="pagamento">Forma de pagamento</label><textarea id="pagamento" name="pagamento" rows="2"><?= e($o['pagamento']) ?></textarea></div>
      <div class="campo" style="grid-column:1/-1"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" rows="3"><?= e($o['observacoes']) ?></textarea></div>
    </div>
  </section>

  <div class="barra-salvar">
    <a class="btn btn-claro" href="orcamentos.php">Voltar</a>
    <button class="btn btn-grande" type="submit">Salvar orçamento</button>
  </div>
</form>

<script>window.ORC = <?= json_encode([
    'itens' => $itensJs,
    'produtos' => array_column($produtos, 'slug', 'nome'),
    'apiFrete' => url_base() . 'api/frete.php',
    'prazoExtra' => (int) cfg('prazo_extra_dias'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="../assets/orcamento-editor.js?v=2"></script>
<?php admin_rodape();
