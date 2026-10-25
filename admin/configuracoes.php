<?php
declare(strict_types=1);

require __DIR__ . '/_base.php';
exigir_login();

$grupos = [
    'Empresa' => [
        'nome_empresa' => ['Nome da empresa'],
        'slogan'       => ['Frase abaixo do nome'],
        'logo_url'     => ['Logo', 'Imagem PNG, JPG ou WEBP de até 2 MB.', 'logo'],
        'cor_primaria' => ['Cor principal', '', 'color'],
        'whatsapp'     => ['WhatsApp para pedidos', 'Com DDD, ex.: 51999999999'],
        'telefone'     => ['Telefone'],
        'email'        => ['E-mail'],
        'endereco'     => ['Endereço'],
    ],
    'Preços e prazos' => [
        'margem_padrao'    => ['Margem padrão (%)', 'Aplicada sobre o preço do fornecedor. Pode ser trocada por categoria ou por produto.', 'number'],
        'arredondamento'   => ['Arredondamento', '', 'select', ['centavos' => 'Sem arredondar (R$ 57,43)', '90' => 'Terminar em ,90 (R$ 57,90)', 'inteiro' => 'Real inteiro (R$ 58,00)']],
        'prazo_extra_dias' => ['Dias úteis somados ao prazo de produção', 'Folga para conferência de arte e envio.', 'number'],
        'mostrar_unitario' => ['Mostrar valor por unidade', '', 'select', ['1' => 'Sim', '0' => 'Não']],
    ],
    'Frete' => [
        'frete_padrao_valor' => ['Frete padrão (R$)', 'Usado quando o CEP não está em nenhuma região cadastrada.', 'number'],
        'frete_padrao_prazo' => ['Prazo de entrega padrão (dias úteis)', '', 'number'],
        'frete_gratis_acima' => ['Frete grátis a partir de (R$)', '0 = desativado', 'number'],
        'frete_texto'        => ['Texto sobre frete'],
        'retirada_ativa'     => ['Oferecer retirada', '', 'select', ['1' => 'Sim', '0' => 'Não']],
        'retirada_texto'     => ['Texto da retirada'],
    ],
    'Orçamentos' => [
        'orc_validade_dias' => ['Validade padrão do orçamento (dias)', '', 'number'],
        'orc_prazo_entrega' => ['Texto padrão de prazo'],
        'orc_pagamento'     => ['Forma de pagamento padrão', '', 'textarea'],
        'orc_observacoes'   => ['Observações padrão', '', 'textarea'],
    ],
    'Textos' => [
        'texto_rodape' => ['Observações do catálogo (rodapé e PDF)', '', 'textarea'],
    ],
    'Atualização de valores' => [
        'atualizacao_auto_horas' => ['Atualizar valores automaticamente a cada (horas)', '0 = só pelo botão "Atualizar valores". Para o automático funcionar, configure o cron (veja o README).', 'number'],
        'requisicoes_paralelas'  => ['Páginas lidas em paralelo', 'Entre 1 e 8.', 'number'],
    ],
];

if (post_valido()) {
    foreach ($grupos as $campos) {
        foreach (array_keys($campos) as $chave) {
            if (isset($_POST[$chave]) && $chave !== 'logo_url') {
                $v = trim((string) $_POST[$chave]);
                if (in_array($chave, ['margem_padrao', 'frete_padrao_valor', 'frete_gratis_acima'], true)) {
                    $v = (string) (float) str_replace(',', '.', $v);
                }
                salvar_cfg($chave, $v);
            }
        }
    }
    // Logo enviada: fica em assets/uploads, no mesmo domínio (necessário para o PDF)
    if (!empty($_POST['remover_logo'])) {
        salvar_cfg('logo_url', '');
    }
    $up = $_FILES['logo_arquivo'] ?? null;
    if ($up && $up['error'] === UPLOAD_ERR_OK) {
        $tipos = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($up['tmp_name']);
        if (!isset($tipos[$mime]) || $up['size'] > 2 * 1024 * 1024) {
            flash('A logo precisa ser PNG, JPG ou WEBP com até 2 MB.', true);
            voltar('configuracoes.php');
        }
        $pasta = RAIZ . '/assets/uploads';
        if (!is_dir($pasta)) {
            mkdir($pasta, 0755, true);
        }
        foreach (glob($pasta . '/logo.*') ?: [] as $antigo) {
            @unlink($antigo);
        }
        move_uploaded_file($up['tmp_name'], $pasta . '/logo.' . $tipos[$mime]);
        salvar_cfg('logo_url', 'assets/uploads/logo.' . $tipos[$mime] . '?v=' . time());
    }
    flash('Configurações salvas.');
    voltar('configuracoes.php');
}

admin_topo('Configurações', 'configuracoes.php');
abas_configuracoes('configuracoes.php');
?>
<form method="post" enctype="multipart/form-data">
  <?= campo_csrf() ?>
  <?php foreach ($grupos as $titulo => $campos): ?>
    <section class="painel">
      <h2><?= e($titulo) ?></h2>
      <div class="campos">
        <?php foreach ($campos as $chave => $def):
            [$rotulo, $ajuda, $tipo, $opcoes] = $def + ['', '', 'text', []];
            $valor = cfg($chave); ?>
          <div class="campo"<?= $tipo === 'textarea' ? ' style="grid-column:1/-1"' : '' ?>>
            <label for="<?= $chave ?>"><?= e($rotulo) ?></label>
            <?php if ($tipo === 'select'): ?>
              <select id="<?= $chave ?>" name="<?= $chave ?>">
                <?php foreach ($opcoes as $v => $t): ?><option value="<?= e($v) ?>" <?= (string) $v === $valor ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
              </select>
            <?php elseif ($tipo === 'logo'): ?>
              <?php if ($valor): ?><img src="<?= e(logo_src()) ?>" alt="" class="logo-previa"><label class="mudo"><input type="checkbox" name="remover_logo" value="1"> remover</label><?php endif; ?>
              <input id="<?= $chave ?>" name="logo_arquivo" type="file" accept="image/png,image/jpeg,image/webp">
            <?php elseif ($tipo === 'textarea'): ?>
              <textarea id="<?= $chave ?>" name="<?= $chave ?>" rows="3"><?= e($valor) ?></textarea>
            <?php else: ?>
              <input id="<?= $chave ?>" name="<?= $chave ?>" type="<?= $tipo ?>" value="<?= e($valor) ?>" <?= $tipo === 'number' ? 'step="any" min="0"' : '' ?>>
            <?php endif; ?>
            <?php if ($ajuda): ?><small><?= e($ajuda) ?></small><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  <button class="btn btn-grande" type="submit">Salvar configurações</button>
</form>
<p></p>
<?php admin_rodape();
