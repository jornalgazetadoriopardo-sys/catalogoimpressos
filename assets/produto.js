(function () {
  'use strict';
  var D = window.CATALOGO;
  var selects = Array.prototype.slice.call(document.querySelectorAll('#opcoes select'));
  var corpoTabela = document.querySelector('#tabela-precos tbody');
  var atual = D.combinacoes[0];
  var qtdSel = null;
  var frete = null;

  function moeda(v) {
    return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }
  function esc(t) {
    return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
  }
  function dias(n) {
    return n + (n === 1 ? ' dia útil' : ' dias úteis');
  }
  function qtdTexto(p) {
    return p.qtd.toLocaleString('pt-BR') + ' ' + p.un;
  }

  // Quando um atributo muda, escolhe a combinação que tem esse valor e mais se
  // parece com a seleção atual (nem todo cruzamento de opções existe).
  function escolher(mudado) {
    var sel = {};
    selects.forEach(function (s) { sel[s.dataset.atributo] = s.value; });
    var melhor = null, pontos = -1;
    D.combinacoes.forEach(function (c) {
      if (mudado && c.specs[mudado] !== sel[mudado]) return;
      var p = 0;
      for (var k in sel) if (c.specs[k] === sel[k]) p++;
      if (p > pontos) { melhor = c; pontos = p; }
    });
    atual = melhor || D.combinacoes[0];
    selects.forEach(function (s) {
      var v = atual.specs[s.dataset.atributo];
      if (v !== undefined) s.value = v;
      // marca opções que existem combinadas com as demais escolhas
      Array.prototype.forEach.call(s.options, function (o) {
        var existe = D.combinacoes.some(function (c) {
          if (c.specs[s.dataset.atributo] !== o.value) return false;
          for (var k in atual.specs) if (k !== s.dataset.atributo && c.specs[k] !== atual.specs[k]) return false;
          return true;
        });
        o.textContent = o.value + (existe ? '' : ' (outras opções)');
      });
    });
    var precoAnterior = qtdSel && qtdSel.qtd;
    qtdSel = atual.precos.filter(function (p) { return p.qtd === precoAnterior; })[0] || atual.precos[0];
    desenhar();
  }

  function desenhar() {
    corpoTabela.innerHTML = '';
    atual.precos.forEach(function (p) {
      var tr = document.createElement('tr');
      if (p === qtdSel) tr.className = 'selecionada';
      tr.innerHTML = '<td><label><input type="radio" name="qtd"' + (p === qtdSel ? ' checked' : '') + '> ' + esc(qtdTexto(p)) + '</label></td>' +
        (D.unitario ? '<td>' + moeda(p.preco / p.qtd) + '</td>' : '') +
        '<td><strong>' + moeda(p.preco) + '</strong></td>';
      tr.addEventListener('click', function () { qtdSel = p; desenhar(); if (frete) calcularFrete(); });
      corpoTabela.appendChild(tr);
    });
    document.getElementById('prazo').textContent = dias(atual.prazo);
    if (atual.imagem) document.getElementById('img-produto').src = atual.imagem;

    var extras = document.getElementById('extras');
    extras.hidden = !atual.extras.length;
    extras.querySelector('ul').innerHTML = atual.extras.map(function (x) {
      return '<li>' + esc(x.nome) + ' <small>(+' + x.dias + (x.dias === 1 ? ' dia' : ' dias') + ')</small></li>';
    }).join('');
    totais();
  }

  function totais() {
    var total = qtdSel.preco + (frete ? frete.valor : 0);
    document.getElementById('total').textContent = moeda(total);
    document.getElementById('total-detalhe').textContent = frete
      ? (frete.valor ? 'inclui frete de ' + moeda(frete.valor) : 'frete grátis') + ' · entrega em até ' + dias(atual.prazo + frete.prazo)
      : 'sem frete';
    var btn = document.getElementById('btn-pedido');
    if (btn) btn.href = 'https://wa.me/' + D.whatsapp + '?text=' + encodeURIComponent(mensagem());
  }

  function mensagem() {
    var linhas = ['Olá! Gostaria de fazer um pedido:', '', '*' + D.produto + '*'];
    for (var k in atual.specs) linhas.push(k + ': ' + atual.specs[k]);
    linhas.push('Quantidade: ' + qtdTexto(qtdSel));
    linhas.push('Valor: ' + moeda(qtdSel.preco));
    if (frete) {
      linhas.push('Frete (CEP ' + frete.cep + '): ' + (frete.valor ? moeda(frete.valor) : 'grátis'));
      linhas.push('Total: ' + moeda(qtdSel.preco + frete.valor));
    }
    linhas.push('Prazo de produção: ' + dias(atual.prazo));
    return linhas.join('\n');
  }

  function calcularFrete() {
    var cep = document.getElementById('cep').value.replace(/\D/g, '');
    var saida = document.getElementById('frete-resultado');
    if (cep.length !== 8) { saida.textContent = 'Informe um CEP válido.'; return; }
    saida.textContent = 'Calculando…';
    fetch(D.apiFrete + '?cep=' + cep + '&subtotal=' + qtdSel.preco)
      .then(function (r) { return r.json(); })
      .then(function (f) {
        if (f.erro) { saida.textContent = f.erro; frete = null; totais(); return; }
        frete = { cep: cep.replace(/(\d{5})(\d{3})/, '$1-$2'), valor: f.valor, prazo: f.prazo };
        saida.innerHTML = '<strong>' + (f.valor ? moeda(f.valor) : 'Frete grátis') + '</strong> · ' + esc(f.nome) +
          ' · entrega em ' + dias(f.prazo) + ' após a produção';
        totais();
      })
      .catch(function () { saida.textContent = 'Não foi possível calcular o frete agora.'; });
  }

  selects.forEach(function (s) { s.addEventListener('change', function () { escolher(s.dataset.atributo); }); });
  document.getElementById('btn-frete').addEventListener('click', calcularFrete);
  document.getElementById('cep').addEventListener('keydown', function (ev) { if (ev.key === 'Enter') calcularFrete(); });
  escolher(null);
})();
