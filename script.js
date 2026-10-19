// PERSISTÊNCIA DE DADOS COM LOCALSTORAGE
let dbClientes = JSON.parse(localStorage.getItem('dbClientes')) || [];
let dbProfissionais = JSON.parse(localStorage.getItem('dbProfissionais')) || [];
let dbAgendamentos = JSON.parse(localStorage.getItem('dbAgendamentos')) || [];

/* ============================================================
   SEGURANÇA E AUTENTICAÇÃO
   ------------------------------------------------------------
   LIMITE IMPORTANTE: este site é estático (sem servidor). Tudo
   aqui roda no navegador, então é uma barreira de ORGANIZAÇÃO
   do fluxo, não uma fronteira de segurança real: quem abrir o
   DevTools consegue desligar o JavaScript e editar o
   localStorage. As medidas abaixo (hash de senha, sessão com
   expiração, limite de tentativas, vínculo da conta à sessão)
   eliminam vazamentos e erros de lógica graves, mas a proteção
   que realmente segura o dado exige backend. See o roteiro de
   produção no README_SECURITY.md.
   ============================================================ */

const ADMIN_EMAIL = 'admin@studionaylopes.com';
// Senha de.bootstrap do painel. É a única credencial que existe em
// texto puro no projeto, e só serve para o PRIMEIRO acesso: no login
// ela é trocada por hash PBKDF2 e o admin é obrigado a definir outra.
const ADMIN_SENHA_BOOTSTRAP = 'admin123';

const SESSAO_KEY = 'sbd-sessao';
const ADMIN_USER_KEY = 'sbd-admin-user';
const TENTATIVAS_KEY = 'sbd-login-tentativas';

const SESSAO_TTL_MINUTOS = 30;
const PBKDF2_ITERACOES = 150000;
const MAX_TENTATIVAS_LOGIN = 5;
const BLOQUEIO_TENTATIVAS_MS = 5 * 60 * 1000;
const MIN_LEN_SENHA = 8;

// crypto.subtle só existe em contexto seguro (https:// ou localhost).
const CRIPTO_FORTE = !!(window.crypto && window.crypto.subtle && typeof TextEncoder === 'function');

if (!CRIPTO_FORTE) {
    console.warn(
        '[segurança] Página fora de contexto seguro. O hash cai para o modo ' +
        'reduzido e a proteção fica apenas simbólica. Publique o site sob HTTPS.'
    );
}

function bytesParaHex(bytes) {
    return Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
}

function hexParaBytes(hex) {
    const saida = new Uint8Array(hex.length / 2);
    for (let i = 0; i < saida.length; i++) saida[i] = parseInt(hex.substr(i * 2, 2), 16);
    return saida;
}

function gerarSalt() {
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    return bytesParaHex(bytes);
}

/* Digest de último recurso para quando não há crypto.subtle.
   NÃO é criptografia: existe só para o login não quebrar fora de
   HTTPS. O aviso no console acima deixa isso explícito. */
function digestReduzido(texto) {
    let h1 = 0x811c9dc5, h2 = 0x01000193;
    for (let i = 0; i < texto.length; i++) {
        const c = texto.charCodeAt(i);
        h1 = ((h1 ^ c) * 0x01000193) >>> 0;
        h2 = ((h2 + c * (i + 7)) ^ (h2 << 5)) >>> 0;
    }
    return (h1 >>> 0).toString(16).padStart(8, '0') + (h2 >>> 0).toString(16).padStart(8, '0');
}

async function derivarHash(senha, saltHex) {
    if (CRIPTO_FORTE) {
        const encoder = new TextEncoder();
        const chave = await window.crypto.subtle.importKey(
            'raw', encoder.encode(senha), 'PBKDF2', false, ['deriveBits']
        );
        const bits = await window.crypto.subtle.deriveBits({
            name: 'PBKDF2',
            salt: hexParaBytes(saltHex),
            iterations: PBKDF2_ITERACOES,
            hash: 'SHA-256'
        }, chave, 256);
        return bytesParaHex(new Uint8Array(bits));
    }
    return 'reduzido:' + digestReduzido(saltHex + '|' + senha);
}

/* Comparação de tempo constante: evita que o tempo de resposta
   revele quantos caracteres da senha já batem. */
function comparacaoSegura(a, b) {
    if (typeof a !== 'string' || typeof b !== 'string') return false;
    if (a.length !== b.length) return false;
    let diferenca = 0;
    for (let i = 0; i < a.length; i++) diferenca |= a.charCodeAt(i) ^ b.charCodeAt(i);
    return diferenca === 0;
}

async function criarCredencial(senha) {
    const salt = gerarSalt();
    return { salt, hash: await derivarHash(senha, salt) };
}

async function conferirSenha(senha, credencial) {
    if (!credencial || !credencial.hash || !credencial.salt) return false;
    return comparacaoSegura(await derivarHash(senha, credencial.salt), credencial.hash);
}

function normalizarEmail(valor) {
    return String(valor == null ? '' : valor).trim().toLowerCase();
}

/* Só aceita páginas do próprio projeto. Sem isso, o parâmetro
   ?next= viraria um open redirect para sites externos. */
function sanearDestino(destino) {
    const valor = String(destino || '');
    return /^[A-Za-z0-9_-]+\.html$/.test(valor) ? valor : null;
}

/* ---------- SESSÃO ---------- */

function criarSessao(email, perfil) {
    const agora = Date.now();
    const sessao = {
        email,
        perfil,                                  // 'cliente' | 'admin'
        criadaEm: agora,
        expiraEm: agora + SESSAO_TTL_MINUTOS * 60 * 1000
    };
    // sessionStorage (e não localStorage): fechar o navegador encerra a sessão.
    try { sessionStorage.setItem(SESSAO_KEY, JSON.stringify(sessao)); } catch (e) {}
    return sessao;
}

function obterSessao() {
    let sessao = null;
    try { sessao = JSON.parse(sessionStorage.getItem(SESSAO_KEY)); } catch (e) { sessao = null; }
    if (!sessao || !sessao.email || !sessao.expiraEm || Date.now() > sessao.expiraEm) {
        encerrarSessao();
        return null;
    }
    return sessao;
}

function encerrarSessao() {
    try { sessionStorage.removeItem(SESSAO_KEY); } catch (e) {}
}

function exigirLoginCliente(pagina = 'minha-conta.html') {
    const sessao = obterSessao();
    if (sessao && sessao.perfil === 'cliente') return sessao;
    encerrarSessao();
    // Depois do login o visitante volta para a página que tentou abrir.
    window.location.replace('login.html?next=' + encodeURIComponent(pagina));
    return null;
}

/* ---------- LIMITE DE TENTATIVAS ---------- */

function estadoTentativas() {
    try {
        const estado = JSON.parse(sessionStorage.getItem(TENTATIVAS_KEY));
        if (estado && typeof estado.falhas === 'number') return estado;
    } catch (e) { /* sessão nova */ }
    return { falhas: 0, bloqueadoAte: 0 };
}

function salvarTentativas(estado) {
    try { sessionStorage.setItem(TENTATIVAS_KEY, JSON.stringify(estado)); } catch (e) {}
}

function registrarFalha() {
    const estado = estadoTentativas();
    estado.falhas += 1;
    if (estado.falhas >= MAX_TENTATIVAS_LOGIN) {
        estado.bloqueadoAte = Date.now() + BLOQUEIO_TENTATIVAS_MS;
    }
    salvarTentativas(estado);
    return estado;
}

function limparTentativas() {
    salvarTentativas({ falhas: 0, bloqueadoAte: 0 });
}

function minutosDeBloqueio() {
    const estado = estadoTentativas();
    const restante = estado.bloqueadoAte - Date.now();
    return restante > 0 ? Math.ceil(restante / 60000) : 0;
}

/* ---------- CONTA ADMIN ---------- */

async function garantirAdminPadrao() {
    let admin = null;
    try { admin = JSON.parse(localStorage.getItem(ADMIN_USER_KEY)); } catch (e) { admin = null; }

    if (admin && admin.credencial && admin.credencial.hash) return admin;

    // Primeiro uso: cria o admin já com a senha convertida em hash.
    admin = {
        email: ADMIN_EMAIL,
        credencial: await criarCredencial(ADMIN_SENHA_BOOTSTRAP),
        senhaAlterada: false
    };
    try { localStorage.setItem(ADMIN_USER_KEY, JSON.stringify(admin)); } catch (e) {}
    return admin;
}

/* ---------- MIGRAÇÃO DE CONTAS ANTIGAS ----------
   Contas criadas antes desta versão guardavam a senha em texto puro
   no localStorage. No primeiro login bem-sucedido a senha é
   reaproveitada como segredo e gravada apenas como hash. */

async function verificarSenhaCliente(cliente, senha) {
    if (!cliente) return false;

    if (cliente.credencial && cliente.credencial.hash) {
        return conferirSenha(senha, cliente.credencial);
    }

    if (typeof cliente.senha === 'string' && cliente.senha) {
        if (senha !== cliente.senha) return false;
        await migrarSenhaCliente(cliente, senha);
        return true;
    }

    return false;
}

async function migrarSenhaCliente(cliente, senha) {
    cliente.credencial = await criarCredencial(senha);
    delete cliente.senha;
    delete cliente.senhaProvisoria;
    localStorage.setItem('dbClientes', JSON.stringify(dbClientes));
}

// Janela de atendimento informada na página de Contato.
// A agenda deve respeitar os mesmos limites para não gerar reservas inválidas.
const HORARIO_ATENDIMENTO = {
    dias: [1, 2, 3, 4, 5, 6], // segunda a sábado
    abertura: 9,               // 9h
    fechamento: 19             // 19h
};

function salvarAgendamentos() {
    localStorage.setItem('dbAgendamentos', JSON.stringify(dbAgendamentos));
}

/* Escapa texto antes de inserir via innerHTML.
   Dados cadastrados por usuários não podem injetar HTML. */
function escapar(texto) {
    return String(texto == null ? '' : texto)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/* Formata valores em Real no padrão brasileiro (R$ 1.234,56). */
function formatarMoeda(valor) {
    return Number(valor || 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });
}

/* Indica se a data escolhida está dentro da janela de atendimento. */
function dentroDoHorarioAtendimento(data) {
    if (!data) return false;
    if (!HORARIO_ATENDIMENTO.dias.includes(data.getDay())) return false;
    return data.getHours() >= HORARIO_ATENDIMENTO.abertura
        && data.getHours() < HORARIO_ATENDIMENTO.fechamento;
}

/* ============================================================
   ALTERNÂNCIA DE TEMA (LIGHT / DARK MODE)
   - Preferência salva no localStorage ('sbd-theme')
   - Respeita a preferência do sistema quando não há escolha salva
   ============================================================ */
const THEME_KEY = 'sbd-theme';

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try {
        localStorage.setItem(THEME_KEY, theme);
    } catch (e) { /* armazenamento indisponível */ }

    const btn = document.getElementById('theme-toggle');
    if (btn) {
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        }
        btn.setAttribute('aria-label', theme === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro');
        btn.title = theme === 'dark' ? 'Modo claro' : 'Modo escuro';
    }
}

function getPreferredTheme() {
    let saved = null;
    try { saved = localStorage.getItem(THEME_KEY); } catch (e) {}
    if (saved === 'dark' || saved === 'light') return saved;
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        return 'dark';
    }
    return 'light';
}

function initTheme() {
    applyTheme(getPreferredTheme());
    const btn = document.getElementById('theme-toggle');
    if (btn) {
        btn.addEventListener('click', () => {
            const atual = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            applyTheme(atual === 'dark' ? 'light' : 'dark');
        });
    }
}

/* ============================================================
   MENU MOBILE (HAMBÚRGUER)
   ============================================================ */
function initMobileNav() {
    const toggle = document.querySelector('.nav-toggle');
    const links = document.querySelector('.nav-links');
    if (!toggle || !links) return;

    const setIcon = (open) => {
        const icon = toggle.querySelector('i');
        if (icon) icon.className = open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
    };

    toggle.addEventListener('click', () => {
        setIcon(links.classList.toggle('open'));
    });

    // Fecha o menu ao escolher um link
    links.querySelectorAll('a').forEach(a => {
        a.addEventListener('click', () => {
            links.classList.remove('open');
            setIcon(false);
        });
    });

    // Fecha o menu ao voltar para o desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth > 820 && links.classList.contains('open')) {
            links.classList.remove('open');
            setIcon(false);
        }
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && links.classList.contains('open')) {
            links.classList.remove('open');
            setIcon(false);
            toggle.focus();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initMobileNav();
    initDelegacaoAcoesAdmin();
    initSessaoNav();

    // RF09 - o painel administrativo exige autenticação.
    if (document.getElementById('users-table-body') || document.querySelector('.admin-dashboard')) {
        if (exigirLoginAdmin()) {
            garantirAdminPadrao();
            renderAdminDashboard();
            renderTabelaServicos();   // RF19
            renderTabelaHorarios();   // RF21
        }
        return; // Nada da área do cliente é renderizado dentro do painel.
    }

    if (document.getElementById('agendar-profissional')) {
        popularSelectsAgendamento();
        vincularAgendamentoASessao();
    }

    // RF04 / RF14 - área Minha Conta, exclusiva para quem está logado.
    if (document.getElementById('perfil-email')) {
        if (exigirLoginCliente()) {
            carregarPerfil();
            renderConfirmacoes();
        }
        return;
    }

    // RF15 - Meus Agendamentos, exclusivo para quem está logado.
    if (document.getElementById('meus-agendamentos-body')) {
        if (exigirLoginCliente('meus-agendamentos.html')) {
            renderMeusAgendamentos();
        }
        return;
    }

    // RF19 - a vitrine pública reflete o catálogo gerenciado pelo admin.
    if (document.getElementById('lista-servicos')) {
        sincronizarVitrineServicos();
    }

    // RF21 - Contato e Agendar mostram a janela real de atendimento.
    atualizarExibicaoHorarios();
    // RF07 - o catálogo exibe a profissional titular do studio.
    // Profissionais extras ficam restritos ao painel administrativo e ao seletor de agendamento.
    const listaProfs = document.getElementById('lista-profissionais');
    if (listaProfs) {
        listaProfs.dataset.catalogo = 'publico';
    }
});

/* Enquanto não há sessão o menu mostra "Login". Depois do acesso ele
   vira "Sair", e o atalho de Minha Conta passa a exibir a conta usada.
   Feito por JS para não ter o mesmo bloco repetido em 11 páginas. */
function initSessaoNav() {
    const linkLogin = document.querySelector('.nav-links a[href="login.html"]');
    if (!linkLogin) return; // página sem esse link (ex.: o próprio painel admin)

    const sessao = obterSessao();
    if (!sessao) return;

    linkLogin.removeAttribute('href');
    linkLogin.classList.remove('btn-admin-nav');
    linkLogin.innerHTML = '<i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Sair';
    linkLogin.addEventListener('click', event => {
        event.preventDefault();
        handleLogout();
    });

    if (sessao.perfil === 'cliente') {
        const conta = document.querySelector('.nav-links a[href="minha-conta.html"]');
        if (conta) {
            conta.innerHTML = '<i class="fa-solid fa-user-gear" aria-hidden="true"></i> Minha Conta';
            conta.title = sessao.email;
        }
    }
}

/* Quando existe sessão, o e-mail do agendamento é travado no da conta.
   Sem isso o cliente marcaria com um e-mail solto e depois não
   encontraria o próprio agendamento em "Meus Agendamentos". */
function vincularAgendamentoASessao() {
    const sessao = obterSessao();
    if (!sessao || sessao.perfil !== 'cliente') return;

    const campoEmail = document.getElementById('agendar-email');
    if (campoEmail) {
        campoEmail.value = sessao.email;
        campoEmail.readOnly = true;
        campoEmail.title = 'Agendamentos usam o e-mail da sua conta.';
    }
    const campoNome = document.getElementById('agendar-nome');
    const cliente = dbClientes.find(c => c.email === sessao.email);
    if (campoNome && cliente && !campoNome.value) campoNome.value = cliente.nome;
}

/* ============================================================
   RF03 / RF04 - RECUPERAÇÃO DE SENHA E DADOS PESSOAIS
   Sem backend, a "recuperação" valida o e-mail e gera uma
   senha provisória que o próprio cliente troca no primeiro acesso.
   ============================================================ */

function gerarSenhaProvisoria() {
    const letras = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let senha = '';
    for (let i = 0; i < 8; i++) {
        senha += letras.charAt(Math.floor(Math.random() * letras.length));
    }
    return senha + 'A1';
}

async function solicitarRecuperacaoSenha(event) {
    event.preventDefault();
    const email = normalizarEmail(document.getElementById('recuperar-email').value);
    const cliente = dbClientes.find(c => c.email === email);

    if (!cliente) {
        // Resposta neutra: não revela se o e-mail existe.
        alert('Se o e-mail estiver cadastrado, as instruções de redefinição serão enviadas.');
        return;
    }

    const senhaProvisoria = gerarSenhaProvisoria();
    // A senha provisória entra direto como hash: nem o próprio
    // localStorage guarda o texto que o cliente vai digitar.
    cliente.credencial = await criarCredencial(senhaProvisoria);
    cliente.senhaProvisoria = true;   // apenas a flag "precisa trocar"
    delete cliente.senha;
    localStorage.setItem('dbClientes', JSON.stringify(dbClientes));

    alert(
        `📧 Instruções enviadas para ${email}.\n\n` +
        `Neste ambiente de demonstração não há servidor de e-mail, ` +
        `portanto exibimos a senha provisória:\n\n` +
        `Senha provisória: ${senhaProvisoria}\n\n` +
        `Use-a para entrar e depois defina uma nova senha em Minha Conta.`
    );
    document.getElementById('recuperar-senha-form').reset();
}

async function handleUpdatePerfil(event) {
    event.preventDefault();

    const sessao = obterSessao();
    if (!sessao || sessao.perfil !== 'cliente') {
        window.location.replace('login.html?next=minha-conta.html');
        return;
    }

    /* A conta editada vem SEMPRE da sessão, nunca do campo de e-mail.
       Antes, a busca era feita pelo valor digitado no formulário: bastava
       escrever o e-mail de outra pessoa para sobrescrever nome, telefone
       e senha da conta alheia (IDOR). */
    const cliente = dbClientes.find(c => c.email === sessao.email);
    if (!cliente) {
        encerrarSessao();
        window.location.replace('login.html?next=minha-conta.html');
        return;
    }

    const nome = document.getElementById('perfil-nome').value.trim();
    const telefone = document.getElementById('perfil-telef').value.trim();
    const novoEmail = normalizarEmail(document.getElementById('perfil-email').value);

    if (!nome || !telefone) {
        alert('⚠️ Preencha nome e telefone.');
        return;
    }
    if (novoEmail !== cliente.email && dbClientes.some(c => c.email === novoEmail)) {
        alert('⚠️ Este e-mail já pertence a outra conta.');
        return;
    }

    cliente.nome = nome;
    cliente.telef = telefone;
    cliente.email = novoEmail;

    const novaSenha = document.getElementById('perfil-senha').value;
    if (novaSenha) {
        if (novaSenha.length < MIN_LEN_SENHA) {
            alert(`⚠️ A senha deve ter no mínimo ${MIN_LEN_SENHA} caracteres.`);
            return;
        }
        cliente.credencial = await criarCredencial(novaSenha);
        delete cliente.senha;
        delete cliente.senhaProvisoria;
    }

    localStorage.setItem('dbClientes', JSON.stringify(dbClientes));

    // Mantém a sessão apontando para a conta, mesmo após trocar o e-mail.
    if (novoEmail !== sessao.email) criarSessao(novoEmail, 'cliente');

    alert('✅ Dados atualizados com sucesso!');
    carregarPerfil();
    document.getElementById('perfil-senha').value = '';
}

function carregarPerfil() {
    const sessao = obterSessao();
    if (!sessao || sessao.perfil !== 'cliente') return;

    const cliente = dbClientes.find(c => c.email === sessao.email);
    if (!cliente) {
        encerrarSessao();
        return;
    }

    document.getElementById('perfil-nome').value = cliente.nome;
    document.getElementById('perfil-email').value = cliente.email;
    document.getElementById('perfil-telef').value = cliente.telef;

    const aviso = document.getElementById('perfil-senha-provisoria');
    if (cliente.senhaProvisoria) {
        aviso.textContent = '⚠️ Você está usando uma senha provisória. Defina uma nova senha abaixo.';
        aviso.style.display = 'block';
    } else {
        aviso.style.display = 'none';
    }
}

/* ============================================================
   RF14 - CONFIRMAÇÃO DE AGENDAMENTO
   Sem servidor de e-mail, o registro fica no histórico do
   cliente e pode ser consultado na área Minha Conta.
   ============================================================ */

function registrarConfirmacao(ag) {
    const confirmacoes = JSON.parse(localStorage.getItem('dbConfirmacoes')) || [];
    confirmacoes.push({
        id: ag.id,
        cliente: ag.cliente,
        email: ag.email,
        telefone: ag.telefone,
        servico: ag.servico,
        data: ag.data,
        valor: ag.valor,
        enviadaEm: new Date().toISOString()
    });
    localStorage.setItem('dbConfirmacoes', JSON.stringify(confirmacoes));
}

function renderConfirmacoes() {
    const corpo = document.getElementById('confirmacoes-body');
    if (!corpo) return;

    const sessao = obterSessao();
    if (!sessao || sessao.perfil !== 'cliente') {
        // Sem sessão o filtro cairia no "mostra tudo" e vazaria as
        // confirmações de todos os clientes.
        corpo.innerHTML = '<tr><td colspan="4">Faça login para ver suas confirmações.</td></tr>';
        return;
    }

    const confirmacoes = JSON.parse(localStorage.getItem('dbConfirmacoes')) || [];
    const lista = confirmacoes.filter(c => normalizarEmail(c.email) === sessao.email);

    corpo.innerHTML = '';
    if (lista.length === 0) {
        corpo.innerHTML = '<tr><td colspan="4">Nenhuma confirmação registrada.</td></tr>';
        return;
    }

    lista.slice().reverse().forEach(c => {
        corpo.innerHTML += `
            <tr>
                <td>#${escapar(c.id)}</td>
                <td>${escapar(c.servico)}</td>
                <td>${escapar(c.data)}</td>
                <td>${escapar(new Date(c.enviadaEm).toLocaleString('pt-BR'))}</td>
            </tr>`;
    });
}

/* RF19 - Mantém a vitrine pública em sincronia com o catálogo do admin. */
function sincronizarVitrineServicos() {
    const grid = document.getElementById('lista-servicos');
    if (!grid) return;

    SERVICOS.forEach(servico => {
        const cards = grid.querySelectorAll('.service-card');
        for (const card of cards) {
            const titulo = card.querySelector('h3');
            if (!titulo || titulo.textContent.trim() !== servico.nome) continue;

            const preco = card.querySelector('.price');
            if (preco) {
                preco.textContent = servico.variacao
                    ? `A partir de ${formatarMoeda(servico.valor)}`
                    : formatarMoeda(servico.valor);
            }
            break;
        }
    });
}

/* RF21 - Mantém Contato e Agendar alinhados à janela definida pelo admin. */
function atualizarExibicaoHorarios() {
    const abre = String(HORARIO_ATENDIMENTO.abertura).padStart(2, '0');
    const fecha = String(HORARIO_ATENDIMENTO.fechamento).padStart(2, '0');
    const dias = HORARIO_ATENDIMENTO.dias
        .slice()
        .sort((a, b) => (a === 0 ? 7 : a) - (b === 0 ? 7 : b))
        .map(d => NOMES_DIAS[d])
        .join(', ');

    const texto = `${dias} — das ${abre}h às ${fecha}h`;

    const contato = document.getElementById('contato-horario');
    if (contato) contato.textContent = texto;

    const aviso = document.getElementById('agenda-horario-aviso');
    if (aviso) aviso.textContent = `Atendimento ${texto}. Agenda flexível de acordo com o horário marcado.`;
}

// ALTERNAR FORMULÁRIO DE CADASTRO
function switchForm(type) {
    const formCli = document.getElementById('form-cliente');
    const formProf = document.getElementById('form-profissional');
    const btns = document.querySelectorAll('.tab-btn');

    if (type === 'cliente') {
        formCli.style.display = 'block';
        formProf.style.display = 'none';
        btns[0].classList.add('active');
        btns[1].classList.remove('active');
    } else {
        formCli.style.display = 'none';
        formProf.style.display = 'block';
        btns[0].classList.remove('active');
        btns[1].classList.add('active');
    }
}

// CADASTRO DE CLIENTES/PROFISSIONAIS
async function handleRegister(event, type) {
    event.preventDefault();

    if (type === 'cliente') {
        const email = normalizarEmail(document.getElementById('cli-email').value);

        // Evita duplicidade de conta, que quebraria o login por e-mail.
        if (dbClientes.some(c => c.email === email)) {
            alert('⚠️ Já existe uma conta com este e-mail.');
            return;
        }

        const senha = document.getElementById('cli-senha').value;
        if (senha.length < MIN_LEN_SENHA) {
            alert(`⚠️ A senha deve ter no mínimo ${MIN_LEN_SENHA} caracteres.`);
            return;
        }

        const cliente = {
            nome: document.getElementById('cli-nome').value.trim(),
            email: email,
            telef: document.getElementById('cli-telef').value.trim(),
            // A senha nunca é gravada: só o hash PBKDF2 e o sal.
            credencial: await criarCredencial(senha)
        };
        dbClientes.push(cliente);
        localStorage.setItem('dbClientes', JSON.stringify(dbClientes));

        // Já deixa o usuário logado, indo direto para a área protegida.
        encerrarSessao();
        criarSessao(cliente.email, 'cliente');
        alert('✨ Cliente cadastrado com sucesso! Você já está logado.');
        event.target.reset();
        window.location.href = 'minha-conta.html';
        return;
    }

    const prof = {
        nome: document.getElementById('prof-nome').value.trim(),
        especialidade: document.getElementById('prof-especialidade').value.trim(),
        telef: document.getElementById('prof-telef').value.trim()
    };
    dbProfissionais.push(prof);
    localStorage.setItem('dbProfissionais', JSON.stringify(dbProfissionais));
    alert('✨ Profissional cadastrado com sucesso!');
    event.target.reset();
}

/* ============================================================
   RF19 / RF21 - CATÁLOGO DE SERVIÇOS E HORÁRIOS DE ATENDIMENTO
   O catálogo inicial vem do servidor simulado; alterações feitas
   pelo admin passam a valer e são preservadas no localStorage.
   ============================================================ */

const SERVICOS_PADRAO = [
    { nome: "Luzes", valor: 350.00, variacao: true },
    { nome: "Progressiva", valor: 130.00, variacao: true },
    { nome: "Escova", valor: 60.00, variacao: true },
    { nome: "Corte", valor: 45.00, variacao: false },
    { nome: "Sobrancelha", valor: 15.00, variacao: true },
    { nome: "Cílios", valor: 80.00, variacao: false },
    { nome: "Manutenção de cílios até 15 dias", valor: 45.00, variacao: false },
    { nome: "Remoção de cílios", valor: 45.00, variacao: false }
];

const SERVICOS_KEY = 'dbServicos';

function carregarServicos() {
    const salvos = localStorage.getItem(SERVICOS_KEY);
    if (salvos) {
        try {
            const lista = JSON.parse(salvos);
            if (Array.isArray(lista) && lista.length) return lista;
        } catch (e) { /* armazenamento corrompido: recomeça do padrão */ }
    }
    return SERVICOS_PADRAO.map(s => ({ ...s }));
}

function salvarServicos() {
    localStorage.setItem(SERVICOS_KEY, JSON.stringify(SERVICOS));
}

let SERVICOS = carregarServicos();

/* RF19 - Admin cadastra serviço. */
function adicionarServico(event) {
    event.preventDefault();

    const nome = document.getElementById('servico-nome').value.trim();
    const valor = Number(document.getElementById('servico-valor').value);
    const variacao = document.getElementById('servico-variacao').checked;

    if (!nome) {
        alert('⚠️ Informe o nome do serviço.');
        return;
    }
    if (!Number.isFinite(valor) || valor < 0) {
        alert('⚠️ Informe um valor válido.');
        return;
    }
    if (SERVICOS.some(s => s.nome.toLowerCase() === nome.toLowerCase())) {
        alert('⚠️ Já existe um serviço com esse nome.');
        return;
    }

    SERVICOS.push({ nome, valor, variacao });
    salvarServicos();
    renderTabelaServicos();
    popularSelectsAgendamento();
    event.target.reset();
    alert('✅ Serviço cadastrado.');
}

/* RF19 - Admin edita valor de um serviço. */
function editarServico(nome) {
    const servico = SERVICOS.find(s => s.nome === nome);
    if (!servico) return;

    const novoValor = prompt(`Novo valor para "${nome}" (R$ ${servico.valor.toFixed(2)}):`,
        servico.valor.toFixed(2));

    if (novoValor === null) return;

    const valor = Number(novoValor.replace(',', '.'));
    if (!Number.isFinite(valor) || valor < 0) {
        alert('⚠️ Valor inválido.');
        return;
    }

    servico.valor = valor;
    salvarServicos();
    renderTabelaServicos();
    popularSelectsAgendamento();
    alert('✅ Serviço atualizado.');
}

/* RF19 - Admin remove serviço. */
function excluirServico(nome) {
    if (!confirm(`Remover o serviço "${nome}" do catálogo?`)) return;
    const indice = SERVICOS.findIndex(s => s.nome === nome);
    if (indice === -1) return;

    SERVICOS.splice(indice, 1);
    salvarServicos();
    renderTabelaServicos();
    popularSelectsAgendamento();
    alert('✅ Serviço removido.');
}

/* RF19 - Admin restaura o catálogo original. */
function restaurarServicos() {
    if (!confirm('Restaurar o catálogo original? Alterações serão perdidas.')) return;
    localStorage.removeItem(SERVICOS_KEY);
    SERVICOS = SERVICOS_PADRAO.map(s => ({ ...s }));
    renderTabelaServicos();
    popularSelectsAgendamento();
    alert('✅ Catálogo restaurado.');
}

function renderTabelaServicos() {
    const corpo = document.getElementById('servicos-table-body');
    if (!corpo) return;

    corpo.innerHTML = '';
    if (SERVICOS.length === 0) {
        corpo.innerHTML = '<tr><td colspan="4">Nenhum serviço cadastrado.</td></tr>';
        return;
    }

    SERVICOS.forEach(s => {
        const preco = s.variacao ? `A partir de ${formatarMoeda(s.valor)}` : formatarMoeda(s.valor);
        /* O nome do serviço é dado digitado por quem usa o painel.
           Antes ele era interpolado num onclick="editarServico('...')":
           o escapar() converte ' em &#39;, o navegador decodifica de volta
           para ' ao montar o atributo e o JS quebra a string — XSS.
           Agora o nome vai em data-nome e o clique é resolvido por
           delegação, fora de qualquer contexto de string JS. */
        corpo.innerHTML += `
            <tr>
                <td>${escapar(s.nome)}</td>
                <td>${preco}</td>
                <td>${s.variacao ? 'Sim' : 'Não'}</td>
                <td>
                    <button type="button" class="btn-cancelar" data-acao="editar-servico" data-nome="${escapar(s.nome)}">Editar</button>
                    <button type="button" class="btn-cancelar" data-acao="excluir-servico" data-nome="${escapar(s.nome)}">Excluir</button>
                </td>
            </tr>`;
    });
}

/* Delegação de eventos para as tabelas cujas ações não podem usar
   onclick inline com texto vindo do usuário. */
function initDelegacaoAcoesAdmin() {
    const alvos = [
        { id: 'servicos-table-body', acoes: { 'editar-servico': editarServico, 'excluir-servico': excluirServico } },
        { id: 'meus-agendamentos-body', acoes: { 'cancelar-agendamento': cancelarAgendamento } },
        { id: 'db-table-body', acoes: { 'cancelar-agendamento-admin': cancelarAgendamentoAdmin } }
    ];

    alvos.forEach(({ id, acoes }) => {
        const corpo = document.getElementById(id);
        if (!corpo || corpo.dataset.delegado === 'true') return;
        corpo.dataset.delegado = 'true';

        corpo.addEventListener('click', event => {
            const botao = event.target.closest('button[data-acao]');
            if (!botao || !corpo.contains(botao)) return;

            const acao = acoes[botao.dataset.acao];
            if (typeof acao !== 'function') return;

            const idOuNome = botao.dataset.acao === 'editar-servico' || botao.dataset.acao === 'excluir-servico'
                ? botao.dataset.nome
                : botao.dataset.id;

            acao(idOuNome);
        });
    });
}

/* RF21 - Admin gerencia a janela de atendimento. */
const HORARIOS_KEY = 'dbHorarios';

function carregarHorarios() {
    const salvos = localStorage.getItem(HORARIOS_KEY);
    if (salvos) {
        try { return { ...HORARIO_ATENDIMENTO, ...JSON.parse(salvos) }; } catch (e) { /* padrão */ }
    }
    return { ...HORARIO_ATENDIMENTO };
}

function salvarHorarios() {
    localStorage.setItem(HORARIOS_KEY, JSON.stringify({
        dias: HORARIO_ATENDIMENTO.dias,
        abertura: HORARIO_ATENDIMENTO.abertura,
        fechamento: HORARIO_ATENDIMENTO.fechamento
    }));
    renderTabelaHorarios();
}

const NOMES_DIAS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

function handleSalvarHorarios(event) {
    event.preventDefault();

    const abertura = Number(document.getElementById('horario-abertura').value);
    const fechamento = Number(document.getElementById('horario-fechamento').value);

    if (!Number.isFinite(abertura) || !Number.isFinite(fechamento) || abertura >= fechamento) {
        alert('⚠️ O horário de abertura deve ser menor que o de fechamento.');
        return;
    }

    const dias = [];
    document.querySelectorAll('.dia-check:checked').forEach(c => dias.push(Number(c.value)));
    if (dias.length === 0) {
        alert('⚠️ Selecione ao menos um dia de atendimento.');
        return;
    }

    HORARIO_ATENDIMENTO.abertura = abertura;
    HORARIO_ATENDIMENTO.fechamento = fechamento;
    HORARIO_ATENDIMENTO.dias = dias;
    salvarHorarios();

    alert('✅ Horários de atendimento atualizados.');
}

function renderTabelaHorarios() {
    const corpo = document.getElementById('horarios-resumo');
    if (!corpo) return;

    const dias = HORARIO_ATENDIMENTO.dias.map(d => NOMES_DIAS[d]).join(', ');
    corpo.textContent =
        `Atendimento: ${dias} — das ` +
        `${String(HORARIO_ATENDIMENTO.abertura).padStart(2, '0')}h às ` +
        `${String(HORARIO_ATENDIMENTO.fechamento).padStart(2, '0')}h`;

    document.querySelectorAll('.dia-check').forEach(c => {
        c.checked = HORARIO_ATENDIMENTO.dias.includes(Number(c.value));
    });
    document.getElementById('horario-abertura').value = HORARIO_ATENDIMENTO.abertura;
    document.getElementById('horario-fechamento').value = HORARIO_ATENDIMENTO.fechamento;
}

// PREENCHER SELECTS DO FORMULÁRIO DE AGENDAMENTO
function popularSelectsAgendamento() {
    const selServico = document.getElementById('agendar-servico');
    const selProf = document.getElementById('agendar-profissional');

    if (selServico) {
        selServico.innerHTML = SERVICOS.map(s => {
            const preco = s.variacao
                ? `A partir de ${formatarMoeda(s.valor)}`
                : formatarMoeda(s.valor);
            return `<option value="${escapar(s.nome)}" data-valor="${s.valor}">${escapar(s.nome)} — ${preco}</option>`;
        }).join('');
    }
    if (selProf) {
        selProf.innerHTML = '<option value="">Qualquer profissional</option>' +
            dbProfissionais.map(p => `<option value="${escapar(p.nome)}">${escapar(p.nome)} (${escapar(p.especialidade)})</option>`).join('');
    }
}

// CRIAR AGENDAMENTO
function handleBooking(event) {
    event.preventDefault();

    const servicoSel = document.getElementById('agendar-servico');
    const servico = SERVICOS.find(s => s.nome === servicoSel.value);
    const dataHora = document.getElementById('agendar-data').value;

    // RF08 - a data precisa estar dentro da janela de atendimento.
    const escolhida = new Date(dataHora);
    if (!dentroDoHorarioAtendimento(escolhida)) {
        alert('⚠️ Escolha um horário de segunda a sábado, das 9h às 19h.');
        return;
    }

    // RF12 - impede reserva duplicada no mesmo horário (evita choque de agenda).
    // A comparação usa o timestamp, pois o texto formatado não é parseável de forma confiável.
    const jaExiste = dbAgendamentos.some(a => {
        if (a.status === 'Cancelado') return false;
        const d = a.dataIso ? new Date(a.dataIso) : null;
        return d && !isNaN(d.getTime()) && d.getTime() === escolhida.getTime();
    });
    if (jaExiste) {
        alert('⚠️ Este horário já foi reservado. Escolha outro.');
        return;
    }

    const sessao = obterSessao();
    const ag = {
        id: Date.now().toString().slice(-6),
        cliente: document.getElementById('agendar-nome').value,
        telefone: document.getElementById('agendar-telef') ? document.getElementById('agendar-telef').value : '',
        // Com sessão ativa, o e-mail vem da conta logada e não do campo,
        // para que o agendamento sempre apareça em "Meus Agendamentos".
        email: sessao && sessao.perfil === 'cliente'
            ? sessao.email
            : normalizarEmail(document.getElementById('agendar-email') ? document.getElementById('agendar-email').value : ''),
        servico: servicoSel.value,
        dataIso: dataHora || '',
        data: dataHora ? escolhida.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '',
        valor: servico ? servico.valor : 0,
        status: "Pendente"
    };

    dbAgendamentos.push(ag);
    salvarAgendamentos();

    // RF14 - registra a confirmação enviada ao cliente.
    registrarConfirmacao(ag);

    const preco = servico && servico.variacao
        ? `A partir de ${formatarMoeda(ag.valor)}`
        : formatarMoeda(ag.valor);

    alert(`✨ Agendamento confirmado para ${ag.data}!\nServiço: ${ag.servico} — ${preco}\n\nA confirmação foi registrada e pode ser consultada em Minha Conta.`);
    event.target.reset();
    // O reset também limpa o e-mail travado; devolve os dados da conta.
    vincularAgendamentoASessao();
    renderMeusAgendamentos();
}

/* RF16 - Cancelar um agendamento existente.
   Regra de antecedência mínima: 2 horas antes do horário marcado. */
const ANTECEDENCIA_MINIMA_HORAS = 2;

function cancelarAgendamento(id) {
    const indice = dbAgendamentos.findIndex(a => a.id === id);
    if (indice === -1) return;

    const agendamento = dbAgendamentos[indice];

    /* Só o dono da reserva ou o admin podem cancelar. Sem esta checagem,
       qualquer visitante poderia cancelar o horário de outra pessoa
       simplesmente passando o id. */
    const sessao = obterSessao();
    const souDono = sessao && sessao.perfil === 'cliente'
        && normalizarEmail(agendamento.email) === sessao.email;
    const souAdmin = sessao && sessao.perfil === 'admin';

    if (!souDono && !souAdmin) {
        alert('⚠️ Você só pode cancelar os próprios agendamentos.');
        return;
    }

    if (agendamento.status === 'Cancelado') {
        alert('Este agendamento já está cancelado.');
        return;
    }

    // Usa o timestamp armazenado; o texto formatado serve apenas para exibição.
    const data = agendamento.dataIso ? new Date(agendamento.dataIso) : new Date('');
    const limite = new Date(data.getTime() - ANTECEDENCIA_MINIMA_HORAS * 60 * 60 * 1000);

    if (!isNaN(data.getTime()) && Date.now() > limite) {
        alert(`⚠️ O cancelamento deve ser feito com pelo menos ${ANTECEDENCIA_MINIMA_HORAS}h de antecedência.`);
        return;
    }

    agendamento.status = 'Cancelado';
    salvarAgendamentos();

    if (typeof renderAdminDashboard === 'function') renderAdminDashboard();
    if (typeof renderMeusAgendamentos === 'function') renderMeusAgendamentos();
    alert('✅ Agendamento cancelado.');
}

/* RF23 - Cancelamento pelo administrador, sem regra de antecedência. */
function cancelarAgendamentoAdmin(id) {
    const sessao = obterSessao();
    if (!sessao || sessao.perfil !== 'admin') {
        window.location.replace('login.html?next=admin.html');
        return;
    }

    const agendamento = dbAgendamentos.find(a => a.id === id);
    if (!agendamento) return;
    if (agendamento.status === 'Cancelado') {
        alert('Este agendamento já está cancelado.');
        return;
    }
    if (!confirm(`Cancelar o agendamento #${agendamento.id} de ${agendamento.cliente}?`)) return;

    agendamento.status = 'Cancelado';
    salvarAgendamentos();
    renderAdminDashboard();
}

/* RF15 - Consultar os agendamentos do cliente. */
function renderMeusAgendamentos() {
    const corpo = document.getElementById('meus-agendamentos-body');
    if (!corpo) return;

    const sessao = obterSessao();
    if (!sessao || sessao.perfil !== 'cliente') {
        corpo.innerHTML = '<tr><td colspan="5">Faça login para consultar seus agendamentos.</td></tr>';
        return;
    }

    /* A lista é sempre a da conta logada. Antes, sem filtro por nome a
       tabela trazia TODOS os agendamentos do studio, e o campo de nome
       permitia ver e cancelar os de qualquer pessoa. */
    const lista = dbAgendamentos.filter(a => normalizarEmail(a.email) === sessao.email);

    corpo.innerHTML = '';
    if (lista.length === 0) {
        corpo.innerHTML = '<tr><td colspan="5">Nenhum agendamento encontrado.</td></tr>';
        return;
    }

    lista.slice().reverse().forEach(a => {
        const classe = a.status === 'Cancelado' ? 'badge-cancelado' : (a.status === 'Pago' ? 'badge-pago' : 'badge-pendente');
        const acao = a.status === 'Cancelado'
            ? '<span style="color:var(--text-secondary);font-size:12px;">—</span>'
            : `<button type="button" class="btn-cancelar" data-acao="cancelar-agendamento" data-id="${escapar(a.id)}">Cancelar</button>`;
        corpo.innerHTML += `
            <tr>
                <td>#${escapar(a.id)}</td>
                <td>${escapar(a.servico)}</td>
                <td>${escapar(a.data)}</td>
                <td><span class="${classe}">${escapar(a.status)}</span></td>
                <td>${acao}</td>
            </tr>`;
    });
}

/* LOGIN (cliente cadastrado ou administrador).
   A senha do admin não é comparada em texto puro: o script só tem o
   hash PBKDF2 guardado no navegador. */
async function handleLogin(event) {
    event.preventDefault();

    const bloqueio = minutosDeBloqueio();
    if (bloqueio > 0) {
        alert(`⛔ Muitas tentativas incorretas. Tente novamente em ${bloqueio} minuto(s).`);
        return;
    }

    const email = normalizarEmail(document.getElementById('login-email').value);
    const senha = document.getElementById('login-senha').value;

    if (!email || !senha) {
        alert('⚠️ Informe e-mail e senha.');
        return;
    }

    // Só páginas internas do projeto são aceitas como destino (anti open redirect).
    const destino = sanearDestino(new URLSearchParams(location.search).get('next'));

    const admin = await garantirAdminPadrao();
    if (email === normalizarEmail(admin.email)) {
        if (await conferirSenha(senha, admin.credencial)) {
            limparTentativas();
            encerrarSessao();
            criarSessao(admin.email, 'admin');
            window.location.href = destino || 'admin.html';
        } else {
            avisarFalhaNoLogin();
        }
        return;
    }

    const cliente = dbClientes.find(c => c.email === email);
    if (cliente && await verificarSenhaCliente(cliente, senha)) {
        limparTentativas();
        encerrarSessao();
        criarSessao(cliente.email, 'cliente');

        // RF03 - o primeiro acesso usa a senha provisória.
        if (cliente.senhaProvisoria) {
            alert('💖 Bem-vinda! Você está usando uma senha provisória.\nDefina uma nova senha em Minha Conta.');
        } else {
            alert(`💖 Bem-vinda, ${cliente.nome}!`);
        }
        window.location.href = destino || 'minha-conta.html';
    } else {
        avisarFalhaNoLogin();
    }
}

function avisarFalhaNoLogin() {
    const estado = registrarFalha();
    const faltam = MAX_TENTATIVAS_LOGIN - estado.falhas;
    if (faltam <= 0) {
        alert(`⛔ Acesso bloqueado por ${Math.round(BLOQUEIO_TENTATIVAS_MS / 60000)} minutos após ${MAX_TENTATIVAS_LOGIN} tentativas.`);
    } else {
        alert(`❌ E-mail ou senha incorretos.\nVocê ainda tem ${faltam} tentativa(s) antes do bloqueio temporário.`);
    }
}

/* RF09 - O Painel Admin só abre para quem passou pelo login. */
function exigirLoginAdmin() {
    const sessao = obterSessao();
    if (sessao && sessao.perfil === 'admin') return true;
    encerrarSessao();
    window.location.replace('login.html?next=admin.html');
    return false;
}

function handleLogout() {
    encerrarSessao();
    window.location.href = 'login.html';
}

// CARREGAR DADOS NO PAINEL ADMIN
function renderAdminDashboard() {
    const usersTbody = document.getElementById('users-table-body');
    const bookingsTbody = document.getElementById('db-table-body');

    if (usersTbody) {
        usersTbody.innerHTML = '';
        dbClientes.forEach(c => {
            usersTbody.innerHTML += `<tr><td><span class="badge-cli">Cliente</span></td><td>${escapar(c.nome)}</td><td>${escapar(c.telef)} | ${escapar(c.email)}</td></tr>`;
        });
        dbProfissionais.forEach(p => {
            usersTbody.innerHTML += `<tr><td><span class="badge-prof">Profissional</span></td><td>${escapar(p.nome)}</td><td>${escapar(p.especialidade)} (${escapar(p.telef)})</td></tr>`;
        });
        if (dbClientes.length === 0 && dbProfissionais.length === 0) {
            usersTbody.innerHTML = '<tr><td colspan="3">Nenhum registro ainda.</td></tr>';
        }
    }

    if (bookingsTbody) {
        bookingsTbody.innerHTML = '';
        dbAgendamentos.forEach(b => {
            const classe = b.status === 'Cancelado' ? 'badge-cancelado' : (b.status === 'Pago' ? 'badge-pago' : 'badge-pendente');
            const acao = b.status === 'Cancelado'
                ? '<span style="color:var(--text-secondary);font-size:12px;">—</span>'
                : `<button type="button" class="btn-cancelar" data-acao="cancelar-agendamento-admin" data-id="${escapar(b.id)}">Cancelar</button>`;
            bookingsTbody.innerHTML += `
                <tr>
                    <td>#${escapar(b.id)}</td>
                    <td>${escapar(b.cliente)}</td>
                    <td>${escapar(b.servico)}</td>
                    <td>${escapar(b.data)}</td>
                    <td>${formatarMoeda(b.valor)}</td>
                    <td><span class="${classe}">${escapar(b.status)}</span></td>
                    <td>${acao}</td>
                </tr>`;
        });
    }

    // Atualizar métricas
    const totalRev = dbAgendamentos.reduce((acc, curr) => acc + Number(curr.valor), 0);
    const elRev = document.getElementById('total-revenue');
    const elBookings = document.getElementById('total-bookings');
    const elOccupancy = document.getElementById('occupancy-rate');
    if (elRev) elRev.innerText = formatarMoeda(totalRev);
    if (elBookings) elBookings.innerText = dbAgendamentos.length;
    if (elOccupancy) elOccupancy.innerText = Math.min(100, dbAgendamentos.length * 10) + '%';
}

/* ============================================================
   GALERIA: AMPLIAR IMAGENS E REPRODUZIR VÍDEOS
   ============================================================ */

function initMediaLightbox() {
    const lightbox = document.getElementById('media-lightbox');
    const content = document.getElementById('media-lightbox-content');
    const caption = document.getElementById('media-lightbox-caption');

    if (!lightbox || !content || !caption) {
        return;
    }

    // Evita registrar os mesmos eventos mais de uma vez.
    if (lightbox.dataset.initialized === 'true') {
        return;
    }

    lightbox.dataset.initialized = 'true';

    function fecharLightbox() {
        content.querySelectorAll('video').forEach(video => {
            video.pause();
            video.removeAttribute('src');
            video.load();
        });

        content.replaceChildren();
        caption.textContent = '';
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.gallery-item').forEach(item => {
        item.addEventListener('click', () => {
            content.replaceChildren();

            const imagem = item.querySelector('img');
            const videoOriginal = item.querySelector('video');
            const caminhoVideo = item.dataset.videoSrc;

            if (caminhoVideo || videoOriginal) {
                const video = document.createElement('video');

                video.src = caminhoVideo ||
                    videoOriginal.currentSrc ||
                    videoOriginal.src;

                video.controls = true;
                video.playsInline = true;
                video.preload = 'metadata';
                video.autoplay = true;

                content.appendChild(video);

                // Alguns navegadores podem bloquear o autoplay.
                const tentativa = video.play();
                if (tentativa && typeof tentativa.catch === 'function') {
                    tentativa.catch(() => {});
                }
            } else if (imagem) {
                const imagemAmpliada = document.createElement('img');

                imagemAmpliada.src = imagem.currentSrc || imagem.src;
                imagemAmpliada.alt = imagem.alt || 'Imagem ampliada';

                content.appendChild(imagemAmpliada);
            } else {
                return;
            }

            caption.textContent = item.dataset.caption ||
                (imagem ? imagem.alt : 'Vídeo da galeria');

            lightbox.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        });
    });

    const botaoFechar = lightbox.querySelector('.media-lightbox-close');

    if (botaoFechar) {
        botaoFechar.addEventListener('click', fecharLightbox);
    }

    lightbox.addEventListener('click', event => {
        if (event.target === lightbox) {
            fecharLightbox();
        }
    });

    document.addEventListener('keydown', event => {
        if (
            event.key === 'Escape' &&
            lightbox.getAttribute('aria-hidden') === 'false'
        ) {
            fecharLightbox();
        }
    });
}

// Funciona mesmo se o script carregar depois do HTML.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMediaLightbox);
} else {
    initMediaLightbox();
}
