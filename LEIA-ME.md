# Studio Nay Lopes — PHP + MySQL

Sistema do studio: site público, cadastro de clientes, agendamento online e
painel de gestão para a profissional (agenda, caixa e administração).

Requisitos: **PHP 7.4+** (recomendado 8.1+) com a extensão PDO MySQL, e
**MySQL 5.7+/MariaDB 10.3+** (XAMPP ou WAMP resolvem).

---

## Perfis de acesso

| Tipo | O que pode fazer |
|---|---|
| **cliente** | Agendar, ver/cancelar os próprios agendamentos e editar a conta |
| **profissional** | Entrar no painel e ver **apenas a própria agenda** |
| **admin** (dono do studio) | Tudo: agenda de todas, caixa, serviços, profissionais e clientes |

O nome da profissional vem sempre da tabela `profissionais`, e o nome da
cliente da tabela `usuarios`. São cadastros independentes — não há mais risco
de um aparecer no lugar do outro.

---

## Instalação nova

1. Copie a pasta para `htdocs/studio-php` (dentro dela deve ficar `imagens/`).
2. No phpMyAdmin, aba **Importar**, envie o `banco.sql`.
3. Configure o acesso ao banco. As credenciais **não ficam no `conexao.php`**:
   crie um `config.php` ao lado dele (o modelo está no próprio arquivo, que
   já vem preenchido para o InfinityFree) ou defina as variáveis de ambiente
   `DB_HOST`, `DB_NAME`, `DB_USER` e `DB_PASS`. O `config.php` está no
   `.gitignore` e não deve ser compartilhado.
4. No terminal, dentro da pasta:

   ```
   php criar-admin.php "Nay Lopes" seu@email.com
   ```

   O comando cria a conta de administração **e vincula o perfil de
   profissional** já existente. É esse vínculo que faz a agenda funcionar.
5. Abra `http://localhost/studio-php/`.

## Rodando no seu computador (XAMPP)

Serve para testar tudo antes de subir para o servidor. Nada é enviado para a
internet e o banco usado é o do seu computador.

1. Abra o **XAMPP Control Panel** e clique em **Start** no MySQL.
2. Em `http://localhost/phpmyadmin`, aba **Importar**, envie o `banco.sql`
   (cria o banco `studio_nay_lopes` com os serviços iniciais).
3. Crie a conta do studio. Abra o **Prompt de Comando** na pasta do projeto e
   rode, em duas linhas:

   ```
   set "DB_HOST=127.0.0.1" & set "DB_NAME=studio_nay_lopes" & set "DB_USER=root" & set "DB_PASS="
   C:\xampp\php\php.exe criar-admin.php "Nay Lopes" seu@email.com
   ```

4. Dê dois cliques em `rodar-local.bat` e abra **http://127.0.0.1:8099**.

O `rodar-local.bat` já configura as variáveis `DB_*` para o MySQL local;
como as variáveis de ambiente têm precedência, o teste local não usa as
credenciais do servidor que ficam no `config.php`. Para parar o site, feche a
janela preta.

### Abrindo pelo Apache do XAMPP (http://localhost/...)

O Apache não recebe as variáveis `DB_*`, então quem aponta para o MySQL local é
o `config.local.php` (já incluído; fora do versionamento e inofensivo no
servidor, porque só é lido quando o acesso vem da própria máquina).

1. A pasta do projeto precisa estar dentro de `C:\xampp\htdocs`. Em vez de
   copiar (e ficar com duas versões do código), crie um atalho de pasta:

   ```
   mklink /J "C:\xampp\htdocs\studio-php" "C:\caminho\da\pasta\do\projeto"
   ```

   Assim o Apache serve sempre os arquivos reais do projeto.
2. Abra **http://localhost/studio-php/**.

Se a página mostrar "Não foi possível conectar ao banco de dados", confira no
`config.local.php` se o usuário é `root` e a senha está vazia (padrão do XAMPP)
e se o MySQL está iniciado no XAMPP Control Panel.

## Atualização do site que já está no ar

> Faça backup do banco antes (phpMyAdmin → Exportar).

1. Importe o `migracao.sql` (uma única vez). Ele preserva os dados e cria as
   novas colunas, o caixa, os bloqueios e a tabela `tentativas_login`.
2. Crie o `config.php` com as credenciais do banco (veja a instalação nova) e
   suba os arquivos novos.
3. Rode `php criar-admin.php "Nay Lopes" seu@email.com` para ligar a conta da
   profissional ao perfil existente (o comando não apaga dados; ele atualiza a
   conta e o vínculo).
4. Entre no painel e confira **Profissionais**, **Serviços** e **Caixa**.

---

## Painel (admin.php)

### Visão geral
Recebido, despesas e saldo do mês, atendimentos de hoje, ticket médio,
próximos atendimentos e, para o admin, repasses por profissional e os
serviços mais vendidos.

### Agenda
- Navegação por dia, filtros de status e de profissional.
- Confirmar, concluir (com forma de pagamento), marcar falta e cancelar.
- **Encaixe**: agendar uma cliente que chegou sem reserva.
- **Bloqueios**: feriado, folga ou compromisso — o site deixa de oferecer
  esses horários automaticamente.

### Caixa (só admin)
- Entradas, saídas, saldo e receita de serviços no mês, com navegação mensal.
- Lançamentos manuais (aluguel, produtos, materiais etc.).
- **Regra automática**: ao concluir um atendimento, a receita entra no caixa
  uma única vez. Se o atendimento for reaberto ou cancelado, o lançamento é
  removido. Lançamentos de agenda não podem ser excluídos à mão.

### Serviços (só admin)
Cadastro, preço, duração em minutos, descrição e ativação. Serviço inativo
some do site sem apagar o histórico.

### Profissionais (só admin)
Cadastro com conta de acesso, especialidade, telefone, comissão (%) e
situação. A comissão alimenta o cálculo de repasses do mês.

### Clientes (só admin)
Busca por nome/e-mail/telefone, total gasto, último atendimento, atalho para
WhatsApp e ativação/desativação do acesso.

---

## Regras de agendamento

- Atendimento de segunda a sábado, das 8h às 18h, em horários de 30 minutos.
- A tela de agendamento lista **somente os horários livres** do dia escolhido
  (o que já está reservado, bloqueado ou já passou não aparece).
- A duração do serviço é respeitada: um atendimento de 3h ocupa os horários
  seguintes.
- Cada profissional tem a própria agenda (dois serviços podem acontecer no
  mesmo horário com profissionais diferentes).
- Conta bloqueios do studio e da profissional.
- Preço e duração vêm do banco, nunca do formulário.
- Cancelamento pela cliente: no mínimo 2 horas de antecedência.

---

## Segurança

- **Credenciais fora do código**: o `conexao.php` lê as variáveis de ambiente
  `DB_*` e, se não existirem, o `config.php` (não versionado). Se este arquivo
  vazar, **troque a senha do banco no painel do host**.
- Senhas com `password_hash()` e rehash automático no login.
- Prepared statements em todas as consultas (PDO, sem emulação).
- Token CSRF em todos os formulários (comparado com `hash_equals`) e trocado
  depois do login/cadastro.
- Sessão com cookie `HttpOnly`/`SameSite=Lax`/`Secure` em HTTPS,
  `session.use_strict_mode` ligado, id renovado a cada 15 minutos e sessão
  encerrada após 30 minutos de inatividade.
- Limite de login guardado no banco (`tentativas_login`): 5 erros por e-mail
  ou 20 por IP em 15 minutos, com atraso entre as tentativas. Não adianta
  trocar de cookie para continuar tentando.
- Cabeçalhos de segurança em todas as páginas (`CSP`, `X-Frame-Options: DENY`
  contra clickjacking, `nosniff`, `Referrer-Policy`, `HSTS` em HTTPS) e erros
  do PHP só no log, nunca na tela.
- Toda saída de dados passa por `e()` (escape de HTML).
- Verificação de permissão por tipo e por profissional em cada ação do painel;
  reabrir atendimento concluído (o que desfaz uma receita) é só do admin.
- Os arquivos de `admin/` não abrem direto pelo navegador (`.htaccess` + guard
  `PAINEL_STUDIO` no PHP).
- **Depois de rodar o `criar-admin.php`, apague esse arquivo do servidor.**

---

## Arquivos

| Arquivo | O que faz |
|---|---|
| `conexao.php` | Conexão PDO, sessão segura, cabeçalhos, configuração de horário e funções de apoio |
| `config.php` | Credenciais do banco do servidor (não versionar; acesso direto bloqueado) |
| `config.local.php` | Credenciais do banco local do XAMPP, usadas só quando o acesso vem da própria máquina |
| `lib/agenda.php` | Horário de atendimento, conflitos, bloqueios e profissionais livres |
| `lib/caixa.php` | Lançamento automático de receitas e resumo mensal |
| `topo.php` / `rodape.php` | Cabeçalho (menu por perfil) e rodapé |
| `index`, `servicos`, `profissionais`, `galeria`, `contato` | Páginas públicas |
| `cadastro.php` / `login.php` / `logout.php` | Criar conta, entrar e sair |
| `agendar.php` | Cliente escolhe serviço, profissional e horário |
| `meus-agendamentos.php` | Próximos atendimentos, histórico e cancelamento |
| `minha-conta.php` | Dados, histórico e exclusão da conta |
| `admin.php` | Painel (carrega a seção escolhida) |
| `admin/acoes.php` | Tratamento de todos os formulários do painel |
| `admin/.htaccess` | Impede abrir os parciais do painel direto pelo navegador |
| `admin/*.php` | Seções: visão, agenda, caixa, serviços, profissionais, clientes |
| `criar-admin.php` | Cria a conta do studio e vincula o perfil (só terminal) |
| `rodar-local.bat` | Abre o site no seu computador com o PHP/MySQL do XAMPP (não vai para o servidor) |
| `banco.sql` | Instalação nova: banco, tabelas e dados iniciais |
| `migracao.sql` | Atualização do banco que já está no ar |
| `ui.js` | Só interface: tema escuro, menu do celular e galeria |
