<?php
declare(strict_types=1);

require __DIR__ . '/_base.php';
exigir_login();

$grupos = [
    'Empresa' => [
        'nome_empresa' => ['Nome da empresa'],
        'slogan'       => ['Frase abaixo do nome'],
        'logo_url'     => ['Endereço da logo (URL da imagem)', 'Envie a logo pelo Gerenciador de Arquivos da Hostinger e cole o link aqui.'],
        'cor_primaria' => ['Cor principal', '', 'color'],
        'whatsapp'     => ['WhatsApp para pedidos', 'Com DDI e DDD, ex.: 5551999999999'],
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
    'Textos' => [
        'texto_rodape' => ['Observações (rodapé e PDF)', '', 'textarea'],
    ],
    'Sincronização' => [
        'modelo_produtos'       => ['Modo modelo: quantos produtos importar', 'Use 0 para importar o catálogo completo (~600 produtos, leva horas). Vale na próxima sincronização.', 'number'],
        'modelo_combinacoes'    => ['Modo modelo: combinações por produto', 'Quantas opções (material, formato…) ler de cada produto. 0 = todas.', 'number'],
        'intervalo_sync_horas'  => ['Atualizar preços a cada (horas)', 'Uma leitura completa leva algumas horas; 24 h ou mais é o recomendado.', 'number'],
        'requisicoes_paralelas' => ['Páginas lidas em paralelo', 'Entre 1 e 8. Mais rápido, porém mais carga no site de origem.', 'number'],
        'atraso_requisicao_ms'  => ['Pausa entre lotes (ms)', '', 'number'],
    ],
];

if (post_valido()) {
    foreach ($grupos as $campos) {
        foreach (array_keys($campos) as $chave) {
            if (isset($_POST[$chave])) {
                $v = trim((string) $_POST[$chave]);
                if (in_array($chave, ['margem_padrao', 'frete_padrao_valor', 'frete_gratis_acima'], true)) {
                    $v = (string) (float) str_replace(',', '.', $v);
                }
                salvar_cfg($chave, $v);
            }
        }
    }
    flash('Configurações salvas.');
    voltar('configuracoes.php');
}

admin_topo('Configurações', 'configuracoes.php');
?>
<form method="post">
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
