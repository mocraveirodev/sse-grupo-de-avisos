// Funções compartilhadas entre as páginas (sem framework, sem build).

export const REACOES = ['👍', '😂', '🔥', '❤️', '🤯'];

export function horaCurta(iso) {
  const data = iso ? new Date(iso) : new Date();
  return data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

/** Cria a bolha de um aviso. Usa textContent: nada de HTML vindo do servidor. */
export function bolhaDeAviso(id, aviso) {
  const el = document.createElement('article');
  el.className = 'bubble';
  el.dataset.id = id;

  const quem = document.createElement('div');
  quem.className = 'who';
  quem.textContent = aviso.autor || 'Admin';

  const texto = document.createElement('p');
  texto.className = 'txt';
  texto.textContent = aviso.texto;

  const meta = document.createElement('div');
  meta.className = 'meta';
  const hora = document.createElement('span');
  hora.textContent = horaCurta(aviso.em);
  const idEl = document.createElement('span');
  idEl.className = 'mono';
  idEl.textContent = `id: ${id}`;
  meta.append(hora, idEl);

  el.append(quem, texto, meta);
  return el;
}

/** Faz alguns emojis subirem pela tela quando o placar aumenta. */
export function animarReacoes(anterior, atual) {
  for (const emoji of REACOES) {
    const novos = Math.min((atual[emoji] ?? 0) - (anterior[emoji] ?? 0), 6);
    for (let i = 0; i < novos; i++) {
      setTimeout(() => {
        const el = document.createElement('div');
        el.className = 'float-emoji';
        el.textContent = emoji;
        el.style.left = `${10 + Math.random() * 80}vw`;
        document.body.append(el);
        el.addEventListener('animationend', () => el.remove());
      }, i * 120);
    }
  }
}

export function definirStatus(el, estado, texto) {
  el.classList.remove('on', 'off');
  if (estado) el.classList.add(estado);
  el.textContent = texto;
}

export function lerLocal(chave, padrao = '') {
  try {
    return localStorage.getItem(chave) ?? padrao;
  } catch {
    return padrao;
  }
}

export function gravarLocal(chave, valor) {
  try {
    localStorage.setItem(chave, valor);
  } catch {
    // modo anônimo ou storage bloqueado: segue sem lembrar
  }
}
