// Painel da admin: manda avisos (POST) e acompanha o grupo pelo mesmo SSE da plateia.

import { REACOES, bolhaDeAviso, animarReacoes, definirStatus, lerLocal, gravarLocal } from './common.js';

const $ = (sel) => document.querySelector(sel);
const status = $('#status');
const tokenInput = $('#token');
const urlInput = $('#url');
const feed = $('#feed');
const vazio = $('#vazio');

const ATALHOS = [
  'Bom dia, grupo! ☀️',
  'Já chegou? 🫏',
  'O café acabou ☕',
  'Coffee break em 5 minutos!',
  'Quem tá usando PHP-FPM levanta a mão ✋',
  'Esse aviso chegou por SSE 😎',
];

// ---- configuração (lembrada neste navegador) ---------------------------------------
tokenInput.value = lerLocal('admin-token');
tokenInput.addEventListener('change', () => gravarLocal('admin-token', tokenInput.value));

const salvo = lerLocal('public-url');
const LOCAL = /localhost|127\.0\.0\.1/;
// Descarta o que não serve mais: túnel antigo, ou localhost quando o painel
// já está aberto num endereço público (ex.: Render), que vira o padrão.
const salvoInvalido = !salvo || /trycloudflare\.com/.test(salvo) || (LOCAL.test(salvo) && !LOCAL.test(location.hostname));
urlInput.value = salvoInvalido ? `${location.origin}/` : salvo;
urlInput.addEventListener('input', () => {
  gravarLocal('public-url', urlInput.value);
  $('#url-origem').textContent = '';
  desenharQr();
});

// PUBLIC_URL do .env: se o campo ainda estiver com um endereço que o celular
// não alcança (localhost), troca por ele.
async function descobrirEndereco() {
  try {
    const resposta = await fetch('config.php', { cache: 'no-store' });
    const { publicUrl } = await resposta.json();
    if (!publicUrl) return;
    if (urlInput.value !== publicUrl && (!urlInput.value || LOCAL.test(urlInput.value))) {
      urlInput.value = publicUrl;
      gravarLocal('public-url', publicUrl);
      desenharQr();
    }
    if (urlInput.value === publicUrl) $('#url-origem').textContent = '✅ do PUBLIC_URL';
  } catch {
    // sem config: segue com o que está no campo
  }
}
descobrirEndereco();

function desenharQr() {
  const url = urlInput.value.trim() || `${location.origin}/`;
  $('#url-texto').textContent = url;
  const qr = qrcode(0, 'M'); // biblioteca em assets/vendor/qrcode.js (MIT)
  qr.addData(url);
  qr.make();
  $('#qr').innerHTML = qr.createSvgTag({ cellSize: 8, margin: 0, scalable: true });
}
desenharQr();

// ---- envio de avisos -------------------------------------------------------------
async function postAdmin(endpoint, corpo) {
  const resposta = await fetch(endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Admin-Token': tokenInput.value },
    body: JSON.stringify(corpo),
  });
  const json = await resposta.json().catch(() => ({}));
  if (!resposta.ok) throw new Error(json.erro || `Erro ${resposta.status}`);
  return json;
}

function mostrar(el, texto, tipo) {
  el.textContent = texto;
  el.className = `msg ${tipo}`;
  if (tipo === 'ok') setTimeout(() => { if (el.textContent === texto) el.textContent = ''; }, 2500);
}

async function enviar(texto) {
  const msg = $('#msg-aviso');
  if (!texto.trim()) {
    mostrar(msg, 'Escreva um aviso antes de enviar.', 'err');
    return;
  }
  try {
    const { id } = await postAdmin('send.php', { texto });
    mostrar(msg, `Enviado (id ${id})`, 'ok');
    $('#texto').value = '';
  } catch (erro) {
    mostrar(msg, erro.message, 'err');
  }
}

$('#form-aviso').addEventListener('submit', (e) => {
  e.preventDefault();
  enviar($('#texto').value);
});
$('#texto').addEventListener('input', () => { $('#msg-aviso').textContent = ''; });
$('#texto').addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
    e.preventDefault();
    enviar($('#texto').value);
  }
});

for (const texto of ATALHOS) {
  const chip = document.createElement('button');
  chip.type = 'button';
  chip.textContent = texto;
  chip.addEventListener('click', () => enviar(texto));
  $('#atalhos').append(chip);
}

$('#derrubar').addEventListener('click', async () => {
  try {
    const { segundos } = await postAdmin('derrubar.php', { segundos: 10 });
    mostrar($('#msg-config'), `Todo mundo desconectado por ${segundos}s. Mande uns avisos agora!`, 'ok');
  } catch (erro) {
    mostrar($('#msg-config'), erro.message, 'err');
  }
});

$('#reset').addEventListener('click', async () => {
  if (!confirm('Apagar todos os avisos e reações do grupo?')) return;
  try {
    await postAdmin('reset.php', {});
    mostrar($('#msg-config'), 'Grupo limpo.', 'ok');
  } catch (erro) {
    mostrar($('#msg-config'), erro.message, 'err');
  }
});

// ---- placar ---------------------------------------------------------------------
const placarEls = {};
for (const emoji of REACOES) {
  const item = document.createElement('div');
  item.innerHTML = `${emoji}<span>0</span>`;
  placarEls[emoji] = item.querySelector('span');
  $('#placar').append(item);
}
let placar = Object.fromEntries(REACOES.map((e) => [e, 0]));
let primeiraCarga = true;

function atualizarPlacar(novo) {
  if (!primeiraCarga) animarReacoes(placar, novo);
  primeiraCarga = false;
  placar = { ...placar, ...novo };
  for (const emoji of REACOES) placarEls[emoji].textContent = placar[emoji] ?? 0;
}

// ---- SSE: o painel é só mais um membro do grupo ---------------------------------------
const vistos = new Set();
// ?papel=admin: o painel não entra na contagem de pessoas no grupo.
const fonte = new EventSource('stream.php?papel=admin');

fonte.addEventListener('open', () => definirStatus(status, 'on', 'conectado'));
fonte.addEventListener('error', () => {
  definirStatus(status, 'off', fonte.readyState === EventSource.CLOSED ? 'desconectado' : 'reconectando…');
});

fonte.addEventListener('aviso', (evento) => {
  if (vistos.has(evento.lastEventId)) return;
  vistos.add(evento.lastEventId);
  vazio.hidden = true;
  feed.prepend(bolhaDeAviso(evento.lastEventId, JSON.parse(evento.data)));
});

fonte.addEventListener('reacoes', (evento) => atualizarPlacar(JSON.parse(evento.data)));

fonte.addEventListener('presenca', (evento) => {
  $('#online').textContent = JSON.parse(evento.data).online;
});

fonte.addEventListener('reset', () => {
  vistos.clear();
  feed.querySelectorAll('.bubble').forEach((b) => b.remove());
  vazio.hidden = false;
  primeiraCarga = true;
  atualizarPlacar(Object.fromEntries(REACOES.map((e) => [e, 0])));
});
