(function () {
  'use strict';

  function $(id) { return document.getElementById(id); }

  /**
   * Roda uma tarefa da fila (adicionar / atualizar) mostrando o andamento no
   * painel #progresso. Repete as chamadas até a fila acabar.
   */
  function rodarFila(acao, dados, titulo, aoTerminar) {
    var painel = $('progresso');
    if (!painel) return;
    painel.hidden = false;
    painel.classList.remove('concluido', 'com-erro');
    $('progresso-titulo').textContent = titulo || 'Lendo o site da gráfica…';
    $('progresso-dica').textContent = 'Pode levar alguns minutos. Mantenha esta página aberta.';
    var log = $('progresso-log');
    log.textContent = '';
    painel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.querySelectorAll('[data-bloquear]').forEach(function (b) { b.disabled = true; });

    function chamar(acaoAtual, extra) {
      var corpo = new FormData();
      corpo.append('csrf', window.ADMIN.csrf);
      corpo.append('acao', acaoAtual);
      Object.keys(extra || {}).forEach(function (k) {
        [].concat(extra[k]).forEach(function (v) { corpo.append(k + '[]', v); });
      });
      fetch('fila.php', { method: 'POST', body: corpo, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          (d.log || []).forEach(function (l) { log.textContent += l + '\n'; });
          log.scrollTop = log.scrollHeight;
          if (!d.ok) { fim(false, d.erro); return; }
          var r = d.resumo, total = r.ok + r.erro + r.pendente;
          $('progresso-numeros').textContent = (r.ok + r.erro) + ' de ' + total + ' páginas';
          $('progresso-barra').style.width = (total ? Math.round(100 * (r.ok + r.erro) / total) : 100) + '%';
          if (r.em_andamento) chamar('continuar');
          else fim(true, r.erro ? r.erro + ' página(s) não puderam ser lidas.' : '');
        })
        .catch(function () {
          log.textContent += 'Falha de conexão; tentando de novo em 10 s…\n';
          setTimeout(function () { chamar('continuar'); }, 10000);
        });
    }

    function fim(ok, aviso) {
      painel.classList.add(ok ? 'concluido' : 'com-erro');
      $('progresso-titulo').textContent = ok ? 'Concluído!' : 'Não foi possível concluir';
      $('progresso-dica').textContent = aviso || 'Tudo certo.';
      document.querySelectorAll('[data-bloquear]').forEach(function (b) { b.disabled = false; });
      if (ok && aoTerminar) setTimeout(aoTerminar, 1200);
    }

    chamar(acao, dados);
  }

  window.rodarFila = rodarFila;

  // Botões com data-fila="atualizar" (atualizar valores de todos os produtos)
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-fila]');
    if (!b) return;
    ev.preventDefault();
    if (b.dataset.confirmar && !confirm(b.dataset.confirmar)) return;
    rodarFila(b.dataset.fila, {}, b.dataset.titulo, function () { location.reload(); });
  });

  // Fila que ficou pela metade (página fechada no meio): continua sozinha
  if (window.ADMIN.continuarFila) {
    document.addEventListener('DOMContentLoaded', function () {
      rodarFila('continuar', {}, 'Continuando a leitura anterior…', function () { location.reload(); });
    });
  }
})();
