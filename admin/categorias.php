<?php
declare(strict_types=1);

require __DIR__ . '/_base.php';
exigir_login();

if (post_valido()) {
    foreach ((array) ($_POST['cat'] ?? []) as $slug => $c) {
        db_exec('UPDATE categorias SET margem = ?, visivel = ?, ordem = ? WHERE slug = ?', [
            margem_digitada($c['margem'] ?? ''), empty($c['visivel']) ? 0 : 1, (int) ($c['ordem'] ?? 0), (string) $slug,
        ]);
    }
    flash('Categorias salvas.');
    voltar('categorias.php');
}

$categorias = db_todos(
    'SELECT c.*, COUNT(p.slug) total, SUM(CASE WHEN p.visivel = 1 THEN 1 ELSE 0 END) visiveis
     FROM categorias c LEFT JOIN produtos p ON p.categoria_slug = c.slug AND p.ativo_origem = 1
     GROUP BY c.slug, c.nome, c.margem, c.visivel, c.ordem ORDER BY c.ordem, c.nome'
);

admin_topo('Categorias', 'categorias.php');
?>
<form method="post" class="painel">
  <?= campo_csrf() ?>
  <p class="mudo">Deixe a margem em branco para usar a margem padrão (<?= e(cfg('margem_padrao')) ?>%). Categorias ocultas não aparecem no catálogo, e as variações dos seus produtos não são lidas na sincronização.</p>
  <div class="rolagem">
    <table class="tabela">
      <thead><tr><th>Visível</th><th>Categoria</th><th>Produtos</th><th>Margem (%)</th><th>Ordem</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($categorias as $c): $s = e($c['slug']); ?>
          <tr>
            <td><input type="checkbox" name="cat[<?= $s ?>][visivel]" value="1" <?= $c['visivel'] ? 'checked' : '' ?>></td>
            <td><?= e($c['nome']) ?></td>
            <td><?= (int) $c['visiveis'] ?> de <?= (int) $c['total'] ?> visíveis</td>
            <td><input type="text" inputmode="decimal" name="cat[<?= $s ?>][margem]" value="<?= e($c['margem'] === null ? '' : rtrim(rtrim((string) $c['margem'], '0'), '.')) ?>" placeholder="padrão"></td>
            <td><input type="number" name="cat[<?= $s ?>][ordem]" value="<?= (int) $c['ordem'] ?>"></td>
            <td><a href="produtos.php?categoria=<?= e(urlencode($c['slug'])) ?>">produtos</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$categorias): ?><tr><td colspan="6" class="mudo">Nenhuma categoria. Faça a sincronização primeiro.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="acoes"><button class="btn" type="submit">Salvar</button></div>
</form>
<?php admin_rodape();
