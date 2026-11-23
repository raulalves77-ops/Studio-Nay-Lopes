# Studio Nay Lopes — Segurança e Roteiro de Produção

Documento de referência do front-end. Descreve **o que foi implementado agora**, os
**limites reais** dessa implementação e o **passo a passo para sair de site estático
para aplicação de produção**.

---

## 1. O que é este site hoje

Site 100% estático: HTML, CSS e JavaScript, sem servidor. Os dados ficam no
`localStorage` do navegador de quem visita.

Isso significa que **qualquer proteção escrita em JavaScript é contornável**.
Basta abrir o DevTools, desativar o JavaScript ou editar o `localStorage` para
entrar em qualquer área. Não é falha da implementação — é uma consequência de não
existir backend.

O que foi feito agora resolve problemas reais de lógica e elimina vazamentos de
dados no uso normal. Para proteção de verdade, o passo é o item 4 adiante.

---

## 2. Vulnerabilidades corrigidas nesta versão

| # | Vulnerabilidade | Onde estava | Gravidade | Correção |
|---|---|---|---|---|
| 1 | **Senhas em texto puro** no `localStorage` | `script.js` (cadastro e perfil) | Crítica | Senha guardada só como hash PBKDF2-SHA256 + sal aleatório |
| 2 | **Senha do admin escrito no código-fonte** | `script.js` (`ADMIN_SENHA`) | Crítica | Virou hash; o código não carrega mais a senha |
| 3 | **IDOR — editar conta alheia** | `handleUpdatePerfil` | Crítica | A conta editada vem da sessão, nunca do campo de e-mail do formulário |
| 4 | **Vazamento de agendamentos** | `renderMeusAgendamentos` | Alta | Lista filtrada pela conta logada; sem campo de busca por nome |
| 5 | **Cancelar agendamento de outra pessoa** | `cancelarAgendamento` | Alta | Só o dono da reserva ou o admin cancela |
| 6 | **Vazamento de confirmações** | `renderConfirmacoes` | Alta | Exige sessão válida |
| 7 | **XSS no painel administrativo** | `renderTabelaServicos` | Alta | `onclick` inline substituído por `data-acao` + delegação de eventos |
| 8 | **Força bruta sem limite** | `handleLogin` | Média | 5 tentativas → bloqueio de 5 minutos |
| 9 | **Minha Conta e Meus Agendamentos sem porta** | páginas HTML | Média | Redirecionam para o login sem sessão |
| 10 | **Sessão sem expiração** | `localStorage` fixo | Média | Sessão com validade de 30 min em `sessionStorage` |
| 11 | **Formulários aninhados inválidos** | `login.html` | Média | Formulários tornados irmãos (bloqueava o login) |

Sobre o item 7, a ordem da falha era esta: o nome do serviço vinha digitado por quem
usa o painel e era interpolado em `onclick="editarServico('...')"`. A função
`escapar()` convertia `'` em `&#39;`, o navegador decodificava de volta para `'`
ao montar o atributo, e o JavaScript quebrava a string — executando o payload.

---

## 3. O que foi implementado

### Autenticação
- **Senhas com PBKDF2-SHA256**, 150.000 iterações, sal aleatório de 16 bytes por conta.
- **Comparação em tempo constante**, para o tempo de resposta não revelar o tamanho da senha.
- **Migração automática**: contas antigas com senha em texto puro são convertidas
  para hash no primeiro login bem-sucedido, sem perder o acesso.
- **Sessão com expiração de 30 minutos**, guardada em `sessionStorage`
  (fechar o navegador encerra a sessão).
- **Retorno ao ponto de origem**: o login guarda para onde a pessoa queria ir
  (`login.html?next=...`), validado contra uma lista de páginas internas para
  impedir open redirect.
- **Limite de tentativas**: 5 erros → 5 minutos de bloqueio, com aviso antes de chegar lá.
- **Admin sem senha no código**: no primeiro acesso a conta é criada a partir de
  uma senha de bootstrap e imediatamente convertida em hash.

### Controle de acesso
- `minha-conta.html`, `meus-agendamentos.html` e `admin.html` exigem sessão.
- Perfil de sessão distingue `cliente` de `admin`; as áreas não se misturam.
- Toda alteração de dados usa o identidade da sessão, nunca valor vindo do formulário.

### Robustez
- Delegação de eventos nas tabelas dinâmicas, eliminando `onclick` inline com
  dado de usuário.
- Senha mínima subiu de 6 para 8 caracteres, com validação no HTML e no JavaScript.
- `?v=2` nos `<script>` para evitar servir versão antiga em cache.

### Onde a proteção **não** alcança
Estes pontos permanecem abertos **por desenho**, e nenhum ajuste no front-end fecha:

- Desativar o JavaScript no DevTools ignora todos os guardas de acesso.
- Editar `sessionStorage` à mão cria uma sessão válida sem senha.
- As senhas em hash podem ser atacadas offline se alguém conseguir ler o
  `localStorage` (força bruta de dictionary contra PBKDF2).
- O `localStorage` é por navegador: não há sincronização entre dispositivos e
  "apagar dados do site" apaga tudo.

---

## 4. Passo a passo para virar um site profissional

### Fase 1 — Publicar com o que já existe (1 a 2 dias)

**1. Domínio e HTTPS.** Obrigatório, e não negociável. Sem HTTPS o navegador não
libera `crypto.subtle`, e o hash cai no modo reduzido — o script avisa isso no
console. Com HTTPS (gratuito na Cloudflare, Vercel ou Netlify) a criptografia
forte volta a funcionar sozinha.

**2. Trocar a senha de bootstrap do admin.** Assim que o site estiver no ar,
entre em `admin.html`, altere a credencial e remova `ADMIN_SENHA_BOOTSTRAP` de
`script.js`.

**3. Publicar o `admin.html` fora do domínio público.** Ele não deve estar
acessível por `seudominio.com/admin.html`. Configure no servidor um
bloqueio por caminho, ou mova o painel para um subdomínio separado com
autenticação no próprio servidor.

**4. Reduzir o peso das imagens.** Hoje `logo-studio-nay-lopes.png` tem 1,2 MB e
`promoção.mp4` tem 6,1 MB — juntos são quase 90% do peso da pasta. Converter o
logo para WebP (~100 KB) e o vídeo para H.264/~720p (~1 MB) corta o carregamento
em mais de 80%.

**5. Cabeçalhos de segurança.** Configure no servidor (exemplo para Netlify,
`_headers`):

```
/*
  X-Frame-Options: DENY
  X-Content-Type-Options: nosniff
  Referrer-Policy: strict-origin-when-cross-origin
  Permissions-Policy: geolocation=(), microphone=(), camera=()
  Strict-Transport-Security: max-age=31536000; includeSubDomains
  Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'
```

> O `script-src 'self'` é o que realmente blinda o XSS: mesmo que alguém injete
> uma tag `<script>`, ela não executa. Revogue `unsafe-inline` de `script-src`
> depois de migrar os `onclick` inline das páginas restantes para `addEventListener`.

**6. Formulário de contato com destino real.** Hoje o contato não envia nada.
Integre um serviço como Formspree ou-mailchimp para o lead chegar ao e-mail.

### Fase 2 — Backend e banco de dados (2 a 4 semanas)

Esta é a fase que transforma o projeto em aplicação. É aqui que a segurança
deixa de ser simbólica.

**7. Escolher a stack.** Para este caso, o caminho mais curto é
**Node.js + Express + PostgreSQL** (ou Supabase, que já entrega Postgres com
login e painel). Alternativa sem servidor: **Supabase** ou **Firebase**.

**8. Modelar o banco.** Tabelas mínimas:

```sql
clientes      (id, nome, email UNIQUE, telefone, senha_hash, criado_em)
profissionais (id, nome, especialidade, telefone, ativo)
servicos      (id, nome UNIQUE, valor, variacao, ativo)
agendamentos  (id, cliente_id FK, servico_id FK, profissional_id FK,
               data_hora, status, valor, criado_em)
```

**9. Mover a autenticação para o servidor.** O hash da senha passa a ser feito
com **bcrypt** ou **Argon2id** — nunca mais com PBKDF2 no navegador. Guarde o
resultado no banco, em coluna `senha_hash`. Esta é a mudança que realmente
fecha o acesso indevido.

**10. Trocar a sessão por cookie `HttpOnly`.** Hoje a sessão vive no
`sessionStorage`, legível por JavaScript. Com backend:

```js
res.cookie('sessao', token, {
  httpOnly: true,   // JavaScript não lê — derruba o roubo via XSS
  secure: true,     // só trafega por HTTPS
  sameSite: 'lax',  // bloqueia envio em requisição de outro site
  maxAge: 30 * 60 * 1000
});
```

**11. Aplicar rate limiting no servidor.** Express + `express-rate-limit`:
5 tentativas por IP a cada 15 minutos no login. No front-end isso é apenas
dissuasivo; o bloqueio que vale é o do servidor.

**12. Validar tudo no servidor.** O JavaScript do cliente é apenas conveniência.
Regras de preço, status e horário devem ser revalidadas na API.

**13. Enviar e-mail de verdade.** Confirmação de agendamento e recuperação de
senha com token de uso único e validade curta, via Resend, SendGrid ou Nodemailer.
Some com a tela de "senha provisória" mostrada no `alert`.

**14. Audit trail.** Registrar em log quem criou, alterou ou cancelou cada
agendamento. Em sistema com dados de clientes e agenda, isso é exigência.

**15. Protection de dados pessoais (LGPD).** O site coleta nome, e-mail e
telefone. Isso exige: política de privacidade publicada, consentimento explícito
no cadastro, botão para o cliente pedir exclusão dos dados e controle sobre
quem acessa a agenda.

### Fase 3 — Qualidade e operação (contínuo)

**16. Migrar o resto dos `onclick` inline.** Ainda há handlers inline em
`cadastro.html`, `login.html` e nas páginas públicas. Removidos, libera-se o
`unsafe-inline` do CSP.

**17. Testes automatizados.** Playwright ou Cypress cobrindo: login válido e
inválido, guarda das páginas protegidas, cancelamento por dono e por terceiros.
As correções desta versão foram verificadas manualmente; automatizar evita regressão.

**18. Monitoramento.** Sentry ou similar para capturar erro de JavaScript em
produção — hoje um erro silencioso só apareceria para o visitante.

**19. Backup e plano de recuperação.** Vale para o banco da Fase 2: backup
automático diário com teste de restauração.

**20. Otimização de carregamento.** Converter imagens para WebP, adicionar
`loading="lazy"` nas imagens abaixo da dobra e imprimir `width`/`height` para
evitar layout shift.

**21. Acessibilidade e SEO.** Gerar `sitemap.xml` e `robots.txt`, adicionar
dados estruturados `LocalBusiness` do Google, revisar contraste e navegação por
teclado em todas as páginas.

---

## 5. Como testar o que foi implementado

1. Abra `minha-conta.html` sem estar logado → deve ir para `login.html`.
2. Cadastre-se em `cadastro.html` → você entra logado e a senha fica só como hash.
3. Entre em `admin.html` como `admin@studionaylopes.com` / `admin123` → painel abre.
4. Erre a senha 5 vezes → bloqueio de 5 minutos.
5. Em "Meus Agendamentos", aparecem só os horários do seu e-mail.
6. DevTools → Application → Local Storage: em `dbClientes` existe `credencial`
   com `hash` e `salt`. **Não existe mais o campo `senha` com texto puro.**

Para zerar tudo durante o desenvolvimento: console → `localStorage.clear()` e
`sessionStorage.clear()`.

---

## 6. Aviso final

Enquanto não houver backend, trate a área logada como **organização de fluxo,
não como segurança**. Os dados vivem no navegador de cada visitante, não em um
servidor: se você limpar o histórico do site, perde tudo, e o painel
administrativo mostra apenas os agendamentos feitos naquele navegador.

A Fase 2 é o que muda isso. Até lá, o site está adequado para demonstração,
portfólio e coursework — que é o contexto em que ele foi construído.
