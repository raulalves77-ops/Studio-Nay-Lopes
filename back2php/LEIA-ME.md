# Studio Nay Lopes — PHP simples + MySQL

Requisitos: PHP 7.4 ou mais novo e MySQL/MariaDB (XAMPP ou WAMP resolvem).

## Instalar
1. Copie a pasta para `htdocs/studio-php` e coloque dentro dela a sua pasta `imagens/`.
2. No phpMyAdmin, aba **Importar**, envie o `banco.sql`.
3. Se precisar, ajuste usuário e senha do MySQL no começo do `conexao.php`.
4. No terminal, dentro da pasta: `php criar-admin.php seu@email.com` (cria o administrador).
5. Abra `http://localhost/studio-php/`.

## Arquivos
| Arquivo | O que faz |
|---|---|
| `conexao.php` | Conecta no banco, liga a sessão e define as funções `e()`, `moeda()`, `data_br()` e `exigir_login()` |
| `topo.php` / `rodape.php` | Cabeçalho (menu) e rodapé repetidos em todas as páginas |
| `index`, `servicos`, `profissionais`, `galeria`, `contato` | Páginas públicas (serviços e profissionais vêm do banco) |
| `cadastro.php` / `login.php` / `logout.php` | Criar conta, entrar e sair |
| `agendar.php` | Cliente escolhe serviço, profissional e horário |
| `meus-agendamentos.php` | Lista e cancela os agendamentos da cliente |
| `minha-conta.php` | Editar dados, ver confirmações, excluir conta |
| `admin.php` | Painel: métricas, agendamentos, serviços, profissionais e clientes |
| `criar-admin.php` | Cria o administrador (só pelo terminal) |
| `banco.sql` | Cria o banco, as tabelas e os dados iniciais |
| `ui.js` | Só interface: tema escuro, menu do celular, galeria |
