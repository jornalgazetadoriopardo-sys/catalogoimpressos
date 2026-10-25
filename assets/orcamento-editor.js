(function () {
  'use strict';
  var D = window.ORC;
  var itens = D.itens.slice();
  var corpo = document.getElementById('itens');
  var produtoAtual = null; // { slug, nome, combinacoes }

  function $(id) { return document.getElementById(id); }
  function esc(t) {
    return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
  }
  function moeda(v) { return (v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }
  function numero(v) {
    v = String(v || '').trim();
    if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.');
    return parseFloat(v) || 0;
  }
  function decimal(v) { return (Math.round(v * 100) / 100).toFixed(2).replace('.', ','); }

  // ---- Itens ----
  function desenharItens() {
    corpo.innerHTML = '';
    itens.forEach(function (it, i) {
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + (it.miniatura ? '<img src="' + esc(it.miniatura) + '" alt="">' : '') + '</td>' +
        '<td><input class="largo" name="itens[' + i + '][titulo]" value="' + esc(it.titulo) + '" placeholder="Produto ou serviço" required>' +
        '<textarea name="itens[' + i + '][detalhes]" rows="2" placeholder="Especificações">' + esc(it.detalhes) + '</textarea></td>' +
        '<td><input type="number" min="1" name="itens[' + i + '][quantidade]" value="' + (it.quantidade || 1) + '">' +
        '<input class="unid" name="itens[' + i + '][unidade]" value="' + esc(it.unidade) + '" placeholder="un."></td>' +
        '<td><input inputmode="decimal" name="itens[' + i + '][valor]" value="' + decimal(it.valor || 0) + '" data-campo="valor"></td>' +
        '<td><input type="number" min="0" name="itens[' + i + '][prazo_dias]" value="' + (it.prazo_dias || 0) + '" title="Dias úteis de produção"> <small>dias</small></td>' +
        '<td class="acoes-linha"><button type="button" class="btn btn-claro btn-peq" data-subir="' + i + '" title="Subir"' + (i ? '' : ' disabled') + '>↑</button>' +
        '<button type="button" class="btn btn-claro btn-peq" data-remover="' + i + '" title="Remover">✕</button>' +
        '<input type="hidden" name="itens[' + i + '][produto_slug]" value="' + esc(it.produto_slug) + '">' +
        '<input type="hidden" name="itens[' + i + '][imagem]" value="' + esc(it.imagem) + '"></td>';
      // mantém os dados ao digitar
      tr.querySelectorAll('input, textarea').forEach(function (campo) {
        campo.addEventListener('input', function () {
          var m = campo.name.match(/\[(\w+)\]$/);
          if (!m) return;
          it[m[1]] = m[1] === 'valor' ? numero(campo.value) : campo.value;
          totais();
        });
      });
      corpo.appendChild(tr);
    });
    $('sem-itens').hidden = itens.length > 0;
    totais();
  }

  corpo.addEventListener('click', function (ev) {
    var r = ev.target.closest('[data-remover]'), s = ev.target.closest('[data-subir]');
    if (r) { itens.splice(+r.dataset.remover, 1); desenharItens(); }
    if (s) { var i = +s.dataset.subir; itens.splice(i - 1, 0, itens.splice(i, 1)[0]); desenharItens(); }
  });

  function totais() {
    var sub = itens.reduce(function (t, it) { return t + (it.valor || 0); }, 0);
    var frete = numero($('frete_valor').value), desc = numero($('desconto').value);
    $('t-subtotal').textContent = moeda(sub);
    $('t-frete').textContent = frete ? moeda(frete) : 'grátis / não incluso';
    $('t-desconto').textContent = desc ? '− ' + moeda(desc) : moeda(0);
    $('t-total').textContent = moeda(Math.max(0, sub + frete - desc));
    return sub;
  }
  ['frete_valor', 'desconto'].forEach(function (id) { $(id).addEventListener('input', totais); });

  // ---- Seletor de produto do catálogo ----
  // carrega ao escolher um nome da lista (input) ou ao sair do campo (change),
  // sem recarregar o mesmo produto — senão o botão "Incluir" muda de lugar no clique
  var carregando = null;
  function aoEscolherProduto() {
    var slug = D.produtos[this.value];
    if (!slug || slug === carregando || (produtoAtual && produtoAtual.slug === slug)) return;
    carregando = slug;
    var caixa = $('opcoes-produto');
    caixa.hidden = false;
    caixa.innerHTML = '<p class="mudo">Carregando opções…</p>';
    fetch('dados.php?p=' + encodeURIComponent(slug), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        carregando = null;
        if (d.erro) { caixa.innerHTML = '<p class="mudo">' + esc(d.erro) + '</p>'; return; }
        produtoAtual = { slug: slug, nome: d.nome, combinacoes: d.combinacoes, imagem: d.imagem };
        montarOpcoes();
      });
  }
  $('busca-produto').addEventListener('input', aoEscolherProduto);
  $('busca-produto').addEventListener('change', aoEscolherProduto);

  function montarOpcoes() {
    var atributos = {};
    produtoAtual.combinacoes.forEach(function (c) {
      Object.keys(c.specs).forEach(function (k) { (atributos[k] = atributos[k] || {})[c.specs[k]] = true; });
    });
    var html = '';
    Object.keys(atributos).forEach(function (k) {
      var vals = Object.keys(atributos[k]).sort(function (a, b) { return a.localeCompare(b, 'pt-BR', { numeric: true }); });
      html += '<div class="campo"><label>' + esc(k) + '</label><select data-atributo="' + esc(k) + '"' + (vals.length === 1 ? ' disabled' : '') + '>' +
        vals.map(function (v) { return '<option>' + esc(v) + '</option>'; }).join('') + '</select></div>';
    });
    html += '<div class="campo"><label>Quantidade</label><select id="sel-qtd"></select></div>';
    $('opcoes-produto').innerHTML = html;
    $('opcoes-produto').querySelectorAll('[data-atributo]').forEach(function (s) {
      s.addEventListener('change', function () { escolher(s.dataset.atributo); });
    });
    $('btn-incluir').hidden = false;
    escolher(null);
  }

  // mesma lógica da página do produto: acha a combinação mais parecida com a escolha
  function escolher(mudado) {
    var selects = $('opcoes-produto').querySelectorAll('[data-atributo]'), sel = {};
    selects.forEach(function (s) { sel[s.dataset.atributo] = s.value; });
    var melhor = null, pontos = -1;
    produtoAtual.combinacoes.forEach(function (c) {
      if (mudado && c.specs[mudado] !== sel[mudado]) return;
      var p = 0;
      for (var k in sel) if (c.specs[k] === sel[k]) p++;
      if (p > pontos) { melhor = c; pontos = p; }
    });
    produtoAtual.comb = melhor || produtoAtual.combinacoes[0];
    selects.forEach(function (s) { var v = produtoAtual.comb.specs[s.dataset.atributo]; if (v !== undefined) s.value = v; });
    var q = $('sel-qtd'), anterior = q.value;
    q.innerHTML = produtoAtual.comb.precos.map(function (p, i) {
      return '<option value="' + i + '">' + p.qtd.toLocaleString('pt-BR') + ' ' + esc(p.un) + ' — ' + moeda(p.preco) + '</option>';
    }).join('');
    if (anterior && q.options[anterior]) q.value = anterior;
  }

  $('btn-incluir').addEventListener('click', function () {
    if (!produtoAtual) return;
    var c = produtoAtual.comb, p = c.precos[+$('sel-qtd').value || 0];
    itens.push({
      titulo: produtoAtual.nome,
      detalhes: Object.keys(c.specs).map(function (k) { return k + ': ' + c.specs[k]; }).join('\n'),
      quantidade: p.qtd, unidade: p.un, valor: p.preco, prazo_dias: c.prazo,
      produto_slug: produtoAtual.slug, imagem: c.imagem_original || produtoAtual.imagem || '', miniatura: c.imagem || ''
    });
    desenharItens();
    $('busca-produto').value = '';
    $('opcoes-produto').hidden = true;
    $('btn-incluir').hidden = true;
    produtoAtual = null;
  });

  $('btn-avulso').addEventListener('click', function () {
    itens.push({ titulo: '', detalhes: '', quantidade: 1, unidade: 'un.', valor: 0, prazo_dias: 0, produto_slug: '', imagem: '', miniatura: '' });
    desenharItens();
    var campos = corpo.querySelectorAll('input.largo');
    campos[campos.length - 1].focus();
  });

  // ---- Frete ----
  $('btn-frete').addEventListener('click', function () {
    var cep = $('cliente_cep').value.replace(/\D/g, '');
    if (cep.length !== 8) { alert('Informe um CEP com 8 números.'); return; }
    fetch(D.apiFrete + '?cep=' + cep + '&subtotal=' + totais())
      .then(function (r) { return r.json(); })
      .then(function (f) {
        if (f.erro) { alert(f.erro); return; }
        $('frete_valor').value = decimal(f.valor);
        $('frete_descricao').value = (f.gratis ? 'Frete grátis' : f.nome) + ' — entrega em até ' + f.prazo + (f.prazo === 1 ? ' dia útil' : ' dias úteis') + ' após a produção';
        totais();
      });
  });
  $('btn-retirada').addEventListener('click', function (ev) {
    ev.preventDefault();
    $('frete_valor').value = '0,00';
    $('frete_descricao').value = 'Retirada no local';
    totais();
  });

  $('form-orc').addEventListener('submit', function (ev) {
    if (!itens.length && !confirm('O orçamento não tem itens. Salvar mesmo assim?')) ev.preventDefault();
  });

  desenharItens();
})();
