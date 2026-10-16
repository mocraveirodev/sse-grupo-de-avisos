// A versão "Já chegou? Já chegou?" do grupo, para comparar com o SSE.

import { bolhaDeAviso, horaCurta } from './common.js';

const $ = (sel) => document.querySelector(sel);
let ultimoId = '';
let requisicoes = 0;
let vazias = 0;
let bytes = 0;
let timer;

function atualizarStats() {
  $('#req').textContent = requisicoes;
  $('#vazias').textContent = vazias;
  $('#pct').textContent = `${requisicoes ? Math.round((vazias / requisicoes) * 100) : 0}%`;
  $('#bytes').textContent = `${(bytes / 1024).toFixed(1)} KB`;
}

function logar(texto, chegou) {
  const linha = document.createElement('div');
  linha.textContent = `${horaCurta()} ${texto}`;
  if (chegou) linha.className = 'hit';
  $('#log').prepend(linha);
  while ($('#log').childElementCount > 80) $('#log').lastElementChild.remove();
}

async function perguntar() {
  try {
    const resposta = await fetch(`polling.php?depois=${encodeURIComponent(ultimoId)}`, { cache: 'no-store' });
    const texto = await resposta.text();
    bytes += texto.length;
    requisicoes++;
    const { itens, ultimoId: novoUltimo } = JSON.parse(texto);
    ultimoId = novoUltimo;

    const avisos = itens.filter((i) => i.tipo === 'aviso');
    if (itens.some((i) => i.tipo === 'reset')) {
      $('#feed').querySelectorAll('.bubble').forEach((b) => b.remove());
      $('#vazio').hidden = false;
    }
    if (avisos.length === 0) {
      vazias++;
      logar('Já chegou? → Ainda não.', false);
    } else {
      logar(`Já chegou? → Chegou! (${avisos.length})`, true);
      $('#vazio').hidden = true;
      for (const aviso of avisos) $('#feed').prepend(bolhaDeAviso(aviso.id, aviso.dados));
    }
  } catch {
    logar('Já chegou? → erro de rede', false);
  }
  atualizarStats();
}

function iniciar() {
  clearInterval(timer);
  timer = setInterval(perguntar, Number($('#intervalo').value));
}

$('#intervalo').addEventListener('change', iniciar);
perguntar();
iniciar();
