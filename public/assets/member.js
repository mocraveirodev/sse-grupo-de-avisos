// Página do celular: recebe avisos por SSE e manda reações por POST.

import { REACOES, bolhaDeAviso, animarReacoes, definirStatus } from './common.js';

const feed = document.querySelector('#feed');
const vazio = document.querySelector('#vazio');
const status = document.querySelector('#status');
const online = document.querySelector('#online');
const lastIdEl = document.querySelector('#lastId');
const barra = document.querySelector('#reacoes');

const vistos = new Set();
let placar = Object.fromEntries(REACOES.map((e) => [e, 0]));
let primeiraCarga = true;
let voltaEm = 0; // Ato 3: quando o servidor mandou a gente voltar

// ---- botões de reação ---------------------------------------------------------
const botoes = {};
for (const emoji of REACOES) {
  const botao = document.createElement('button');
  botao.type = 'button';
  botao.innerHTML = `${emoji}<span>0</span>`;
  botao.setAttribute('aria-label', `Reagir com ${emoji}`);
  let ultimoClique = 0;
  botao.addEventListener('click', () => {
    const agora = Date.now();
    if (agora - ultimoClique < 250) return; // calma, não precisa metralhar
    ultimoClique = agora;
    navigator.vibrate?.(15);
    // O cliente fala com o servidor por um POST normal...
    fetch('react.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ emoji }),
    }).catch(() => {});
  });
  botoes[emoji] = botao.querySelector('span');
  barra.append(botao);
}

function atualizarPlacar(novo) {
  animarReacoes(placar, novo);
  placar = { ...placar, ...novo };
  for (const emoji of REACOES) botoes[emoji].textContent = placar[emoji] ?? 0;
}

// ---- SSE ------------------------------------------------------------------------
// ...e o servidor fala com todo mundo por aqui. É só isso. Sério.
const fonte = new EventSource('stream.php');

fonte.addEventListener('open', () => {
  voltaEm = 0; // voltou: encerra a contagem regressiva do Ato 3, se houver
  definirStatus(status, 'on', 'conectado');
});

fonte.addEventListener('error', () => {
  // O EventSource reconecta sozinho (respeitando o "retry:" do servidor)
  // e manda o header Last-Event-ID. Aqui só avisamos quem está olhando.
  const fechado = fonte.readyState === EventSource.CLOSED;
  if (!fechado && Date.now() < voltaEm) return; // a contagem regressiva já está na tela
  definirStatus(status, 'off', fechado ? 'desconectado' : 'reconectando…');
  if (fechado) setTimeout(() => location.reload(), 5000);
});

// Ato 3: o servidor mandou um "retry:" maior e fechou a conexão.
fonte.addEventListener('derrubar', (evento) => {
  lastIdEl.textContent = evento.lastEventId || '—';
  const { segundos } = JSON.parse(evento.data);
  voltaEm = Date.now() + segundos * 1000;
  const tique = () => {
    const faltam = Math.ceil((voltaEm - Date.now()) / 1000);
    if (faltam <= 0 || Date.now() >= voltaEm) {
      clearInterval(contagem);
      return;
    }
    definirStatus(status, 'off', `🛗 volto em ${faltam}s`);
  };
  const contagem = setInterval(tique, 250);
  tique();
});

fonte.addEventListener('aviso', (evento) => {
  lastIdEl.textContent = evento.lastEventId || '—';
  if (vistos.has(evento.lastEventId)) return;
  vistos.add(evento.lastEventId);

  const bolha = bolhaDeAviso(evento.lastEventId, JSON.parse(evento.data));
  vazio.hidden = true;
  feed.append(bolha);

  if (!primeiraCarga) {
    bolha.classList.add('fresh');
    if (navigator.userActivation?.hasBeenActive) navigator.vibrate?.([60, 40, 60]);
    bolha.scrollIntoView({ behavior: 'smooth', block: 'end' });
  }
});

fonte.addEventListener('reacoes', (evento) => {
  if (evento.lastEventId) lastIdEl.textContent = evento.lastEventId;
  // A primeira leitura do placar não anima: é só o estado atual.
  if (primeiraCarga) {
    placar = { ...placar, ...JSON.parse(evento.data) };
    for (const emoji of REACOES) botoes[emoji].textContent = placar[emoji] ?? 0;
    primeiraCarga = false;
    window.scrollTo({ top: document.body.scrollHeight });
    return;
  }
  atualizarPlacar(JSON.parse(evento.data));
});

fonte.addEventListener('presenca', (evento) => {
  const { online: n } = JSON.parse(evento.data);
  online.textContent = `${n} ${n === 1 ? 'pessoa' : 'pessoas'} no grupo`;
});

fonte.addEventListener('reset', (evento) => {
  lastIdEl.textContent = evento.lastEventId || '—';
  vistos.clear();
  feed.querySelectorAll('.bubble').forEach((b) => b.remove());
  vazio.hidden = false;
  placar = Object.fromEntries(REACOES.map((e) => [e, 0]));
  for (const emoji of REACOES) botoes[emoji].textContent = 0;
});
