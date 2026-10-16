// PERSISTÊNCIA DE DADOS COM LOCALSTORAGE
let dbClientes = JSON.parse(localStorage.getItem('dbClientes')) || [];
let dbProfissionais = JSON.parse(localStorage.getItem('dbProfissionais')) || [];
let dbAgendamentos = JSON.parse(localStorage.getItem('dbAgendamentos')) || [
    { id: 101, cliente: "Sarah Jenkins", servico: "Cortes Modernos", data: "24/08/2026 - 09:00", valor: 120.00, status: "Pago" }
];

function salvarAgendamentos() {
    localStorage.setItem('dbAgendamentos', JSON.stringify(dbAgendamentos));
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

    const metaTheme = document.getElementById('meta-theme-color');
    if (metaTheme) metaTheme.setAttribute('content', theme === 'dark' ? '#21191b' : '#fdf6f3');

    const btn = document.getElementById('theme-toggle');
    if (btn) {
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        }
        btn.setAttribute('aria-label', theme === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro');
        btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
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

// Segue a preferência do sistema quando o usuário nunca escolheu manualmente
if (window.matchMedia) {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const onSystemChange = (event) => {
        let saved = null;
        try { saved = localStorage.getItem(THEME_KEY); } catch (e) {}
        if (saved !== 'dark' && saved !== 'light') {
            applyTheme(event.matches ? 'dark' : 'light');
        }
    };
    if (mediaQuery.addEventListener) mediaQuery.addEventListener('change', onSystemChange);
    else if (mediaQuery.addListener) mediaQuery.addListener(onSystemChange);
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
        if (window.innerWidth > 900 && links.classList.contains('open')) {
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

    if (document.getElementById('users-table-body') || document.querySelector('.admin-dashboard')) {
        renderAdminDashboard();
    }
    if (document.getElementById('agendar-profissional')) {
        popularSelectsAgendamento();
    }
    const listaProfs = document.getElementById('lista-profissionais');
    if (listaProfs) {
        dbProfissionais.forEach(p => {
            listaProfs.innerHTML += `<div class="service-card"><i class="fa-solid fa-user-tie"></i><h3>${p.nome}</h3><p>${p.especialidade} — ${p.telef}</p></div>`;
        });
    }
});

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
function handleRegister(event, type) {
    event.preventDefault();

    if (type === 'cliente') {
        const cliente = {
            nome: document.getElementById('cli-nome').value,
            email: document.getElementById('cli-email').value,
            telef: document.getElementById('cli-telef').value,
            senha: document.getElementById('cli-senha').value
        };
        dbClientes.push(cliente);
        localStorage.setItem('dbClientes', JSON.stringify(dbClientes));
        alert('✨ Cliente cadastrado com sucesso!');
    } else {
        const prof = {
            nome: document.getElementById('prof-nome').value,
            especialidade: document.getElementById('prof-especialidade').value,
            telef: document.getElementById('prof-telef').value
        };
        dbProfissionais.push(prof);
        localStorage.setItem('dbProfissionais', JSON.stringify(dbProfissionais));
        alert('✨ Profissional cadastrado com sucesso!');
    }

    event.target.reset();
}

// SERVIÇOS DISPONÍVEIS (nome, profissional opcional, valor)
const SERVICOS = [
    { nome: "Luzes", valor: 350.00 },
    { nome: "Progressiva", valor: 130.00 },
    { nome: "Escova", valor: 60.00 },
    { nome: "Corte", valor: 45.00 },
    { nome: "Sobrancelha", valor: 15.00 },
    { nome: "Cílios", valor: 80.00 },
    { nome: "Manutenção de cílios até 15 dias", valor: 45.00 },
    { nome: "Remoção de cílios", valor: 45.00 }
];

// PREENCHER SELECTS DO FORMULÁRIO DE AGENDAMENTO
function popularSelectsAgendamento() {
    const selServico = document.getElementById('agendar-servico');
    const selProf = document.getElementById('agendar-profissional');

    if (selServico) {
        selServico.innerHTML = SERVICOS.map(s => `<option value="${s.nome}" data-valor="${s.valor}">${s.nome} — R$ ${s.valor.toFixed(2)}</option>`).join('');
    }
    if (selProf) {
        selProf.innerHTML = '<option value="">Qualquer profissional</option>' +
            dbProfissionais.map(p => `<option value="${p.nome}">${p.nome} (${p.especialidade})</option>`).join('');
    }
}

// CRIAR AGENDAMENTO
function handleBooking(event) {
    event.preventDefault();

    const servicoSel = document.getElementById('agendar-servico');
    const servico = SERVICOS.find(s => s.nome === servicoSel.value);
    const dataHora = document.getElementById('agendar-data').value;

    const ag = {
        id: Date.now().toString().slice(-6),
        cliente: document.getElementById('agendar-nome').value,
        servico: servicoSel.value,
        data: dataHora ? new Date(dataHora).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '',
        valor: servico ? servico.valor : 0,
        status: "Pendente"
    };

    dbAgendamentos.push(ag);
    salvarAgendamentos();
    alert(`✨ Agendamento confirmado para ${ag.data}!\nServiço: ${ag.servico} — R$ ${ag.valor.toFixed(2)}`);
    event.target.reset();
}

// LOGIN SIMPLES (cliente cadastrado ou admin fixo)
function handleLogin(event) {
    event.preventDefault();
    const email = document.getElementById('login-email').value;
    const senha = document.getElementById('login-senha').value;

    if (email === 'admin@studio.com' && senha === 'admin123') {
        alert('🔓 Login de administrador realizado!');
        window.location.href = 'admin.html';
        return;
    }

    const cliente = dbClientes.find(c => c.email === email && c.senha === senha);
    if (cliente) {
        alert(`💖 Bem-vinda, ${cliente.nome}!`);
        window.location.href = 'index.html';
    } else {
        alert('❌ E-mail ou senha incorretos.');
    }
}

// CARREGAR DADOS NO PAINEL ADMIN
function renderAdminDashboard() {
    const usersTbody = document.getElementById('users-table-body');
    const bookingsTbody = document.getElementById('db-table-body');

    if (usersTbody) {
        usersTbody.innerHTML = '';
        dbClientes.forEach(c => {
            usersTbody.innerHTML += `<tr><td><span class="badge-cli">Cliente</span></td><td>${c.nome}</td><td>${c.telef} | ${c.email}</td></tr>`;
        });
        dbProfissionais.forEach(p => {
            usersTbody.innerHTML += `<tr><td><span class="badge-prof">Profissional</span></td><td>${p.nome}</td><td>${p.especialidade} (${p.telef})</td></tr>`;
        });
        if (dbClientes.length === 0 && dbProfissionais.length === 0) {
            usersTbody.innerHTML = '<tr><td colspan="3">Nenhum registro ainda.</td></tr>';
        }
    }

    if (bookingsTbody) {
        bookingsTbody.innerHTML = '';
        dbAgendamentos.forEach(b => {
            const badge = b.status === 'Pago' ? 'badge-pago' : 'badge-pendente';
            bookingsTbody.innerHTML += `
                <tr>
                    <td>#${b.id}</td>
                    <td>${b.cliente}</td>
                    <td>${b.servico}</td>
                    <td>${b.data}</td>
                    <td>R$ ${Number(b.valor).toFixed(2)}</td>
                    <td><span class="${badge}">${b.status}</span></td>
                </tr>`;
        });
    }

    // Atualizar métricas
    const totalRev = dbAgendamentos.reduce((acc, curr) => acc + Number(curr.valor), 0);
    const elRev = document.getElementById('total-revenue');
    const elBookings = document.getElementById('total-bookings');
    const elOccupancy = document.getElementById('occupancy-rate');
    if (elRev) elRev.innerText = `R$ ${totalRev.toFixed(2).replace('.', ',')}`;
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

// Lógica de Validação de Senha em Tempo Real
document.addEventListener('DOMContentLoaded', () => {
    const senhaInput = document.getElementById('cli-senha');
    const confirmaSenhaInput = document.getElementById('cli-confirma-senha');
    const strengthBar = document.getElementById('strength-bar');
    const strengthText = document.getElementById('strength-text');
    const matchText = document.getElementById('match-text');

    if (senhaInput && strengthBar && strengthText) {
        senhaInput.addEventListener('input', () => {
            const val = senhaInput.value;
            let strength = 0;
            
            // Critérios de pontuação:
            if (val.length >= 8) strength += 1; // Pelo menos 8 caracteres
            if (val.match(/(?=.*[a-z])(?=.*[A-Z])/)) strength += 1; // Maiúscula e Minúscula
            if (val.match(/(?=.*\d)/)) strength += 1; // Número
            if (val.match(/(?=.*[^a-zA-Z\d])/)) strength += 1; // Caractere Especial

            // Reseta as classes
            strengthBar.className = 'password-strength-bar';
            strengthText.className = 'password-strength-text';

            // Aplica o feedback visual
            if (val.length === 0) {
                strengthText.textContent = '';
            } else if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
                strengthText.classList.add('text-weak');
                strengthText.textContent = 'Força: Fraca (Adicione números e símbolos)';
            } else if (strength === 3) {
                strengthBar.classList.add('strength-medium');
                strengthText.classList.add('text-medium');
                strengthText.textContent = 'Força: Média (Adicione mais variedade)';
            } else if (strength >= 4) {
                strengthBar.classList.add('strength-strong');
                strengthText.classList.add('text-strong');
                strengthText.textContent = 'Força: Forte';
            }
            
            // Revalida se as senhas batem ao alterar a primeira senha
            if(confirmaSenhaInput) checkMatch();
        });
    }

    if (confirmaSenhaInput && matchText) {
        confirmaSenhaInput.addEventListener('input', checkMatch);
    }

    function checkMatch() {
        if (confirmaSenhaInput.value.length === 0) {
            matchText.textContent = '';
            return;
        }
        if (senhaInput.value === confirmaSenhaInput.value) {
            matchText.textContent = 'As senhas coincidem!';
            matchText.className = 'password-match-text match-yes';
        } else {
            matchText.textContent = 'As senhas não coincidem.';
            matchText.className = 'password-match-text match-no';
        }
    }
});

/* =======================================================
   MÓDULO DE AUTENTICAÇÃO E SESSÃO (Simulação JWT/Sessão)
======================================================= */

// 1. Inicializar a base de dados fictícia de utilizadores se não existir
if (!localStorage.getItem('studio_users')) {
    const defaultUsers = [
        { 
            id: 1, 
            nome: 'Admin Studio', 
            email: 'admin@studio.com', 
            senha: 'admin123', 
            tipo: 'admin' 
        }
    ];
    localStorage.setItem('studio_users', JSON.stringify(defaultUsers));
}

// 2. Função de Registo de Cliente
function handleRegister(event) {
    event.preventDefault();

    const nome = document.getElementById('cli-nome').value;
    const email = document.getElementById('cli-email').value;
    const telef = document.getElementById('cli-telef').value;
    const senha = document.getElementById('cli-senha').value;
    const confSenha = document.getElementById('cli-confirma-senha').value;

    if (senha !== confSenha) {
        alert('As palavras-passe não coincidem. Por favor, verifique.');
        return;
    }

    let users = JSON.parse(localStorage.getItem('studio_users'));

    // Verificar se o e-mail já existe
    if (users.find(u => u.email === email)) {
        alert('Este e-mail já está registado. Por favor, faça login.');
        return;
    }

    const newUser = {
        id: Date.now(),
        nome: nome,
        email: email,
        telefone: telef,
        senha: senha,
        tipo: 'cliente' // Força o tipo para Cliente (RF02)
    };

    users.push(newUser);
    localStorage.setItem('studio_users', JSON.stringify(users));

    alert('Conta criada com sucesso! Já pode iniciar sessão.');
    window.location.href = 'login.html';
}

// 3. Função de Login
function handleLogin(event) {
    event.preventDefault();

    const email = document.getElementById('login-email').value;
    const senha = document.getElementById('login-senha').value;

    let users = JSON.parse(localStorage.getItem('studio_users'));
    const user = users.find(u => u.email === email && u.senha === senha);

    if (user) {
        // Criar a "sessão" simulando um JWT payload
        const sessionData = {
            token: 'jwt_simulated_' + Date.now(),
            userId: user.id,
            nome: user.nome,
            tipo: user.tipo
        };
        localStorage.setItem('studio_session', JSON.stringify(sessionData));

        alert(`Bem-vindo(a) de volta, ${user.nome.split(' ')[0]}!`);

        // Diferenciar a navegação conforme o Perfil
        if (user.tipo === 'admin') {
            window.location.href = 'admin.html';
        } else {
            window.location.href = 'index.html'; // Cliente vai para a Home ou Agendar
        }
    } else {
        alert('E-mail ou palavra-passe incorretos.');
    }
}

// 4. Função de Recuperação de Palavra-passe
function handleRecuperarSenha(event) {
    event.preventDefault();
    const email = document.getElementById('recuperar-email').value;
    
    // Simulação visual para o utilizador
    alert(`Se o e-mail '${email}' estiver registado, receberá as instruções em breve.`);
    window.location.href = 'login.html';
}

// 5. Função de Logout
function logout() {
    localStorage.removeItem('studio_session');
    window.location.href = 'login.html';
}

// 6. Diferenciar visualmente a Navegação (Executa em todas as páginas)
document.addEventListener('DOMContentLoaded', () => {
    const session = JSON.parse(localStorage.getItem('studio_session'));
    const navLinks = document.getElementById('site-navigation');

    if (session && navLinks) {
        // Encontrar as hiperligações existentes no menu
        const loginLink = navLinks.querySelector('a[href="login.html"]');
        const cadastroLink = navLinks.querySelector('a[href="cadastro.html"]');

        // Esconder o botão de registo, pois o utilizador já tem sessão iniciada
        if (cadastroLink) {
            cadastroLink.style.display = 'none';
        }

        // Alterar o botão "Login" para "Sair (Nome)"
        if (loginLink) {
            loginLink.innerHTML = `<i class="fa-solid fa-right-from-bracket"></i> Sair (${session.nome.split(' ')[0]})`;
            loginLink.href = "#";
            loginLink.classList.remove('active');
            loginLink.style.backgroundColor = 'transparent';
            loginLink.style.border = '1px solid var(--accent-marsala)';
            loginLink.style.color = 'var(--accent-marsala)';
            
            // Adicionar evento de logout
            loginLink.addEventListener('click', (e) => {
                e.preventDefault();
                logout();
            });
        }

        // Se for um Administrador, adicionar o atalho para o Painel de Gestão
        if (session.tipo === 'admin') {
            const adminLink = document.createElement('a');
            adminLink.href = 'admin.html';
            adminLink.innerHTML = '<i class="fa-solid fa-gear"></i> Painel Admin';
            adminLink.className = 'btn-admin-nav'; // Mantém o destaque do botão
            navLinks.insertBefore(adminLink, loginLink);
        }
    }
});