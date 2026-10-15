# 💈 Barbearia Agenda — Sistema de Gerenciamento

Sistema web desenvolvido em PHP e MySQL para auxiliar no gerenciamento de uma barbearia, reunindo informações de clientes, profissionais, serviços e agendamentos.

O projeto foi desenvolvido para praticar conceitos de desenvolvimento web, manipulação de bancos de dados, autenticação de usuários e organização de aplicações PHP.

## 🚀 Funcionalidades

- Autenticação de usuários com login e logout.
- Cadastro, listagem, edição e exclusão de clientes.
- Gerenciamento de profissionais.
- Gerenciamento de serviços.
- Consulta de agendamentos e atualização de status.
- Painel principal de gerenciamento.
- Validação de formulários e tratamento de erros.

## 🛠️ Tecnologias utilizadas

- **PHP:** desenvolvimento back-end.
- **MySQL:** armazenamento dos dados.
- **PDO:** comunicação com o banco de dados.
- **HTML5:** estrutura das páginas.
- **CSS3:** estilização da interface.
- **XAMPP:** ambiente de desenvolvimento local.

## 🔐 Segurança

O sistema utiliza recursos como:

- Senhas armazenadas com `password_hash()`.
- Autenticação com `password_verify()`.
- Sessões PHP para controle de acesso.
- Tokens CSRF nos formulários analisados.
- Consultas preparadas com PDO.
- Escape de dados exibidos em HTML.
- Configuração local do banco de dados separada do código publicado.

## 📁 Estrutura do projeto

- `agendamentos/` — gerenciamento de agendamentos.
- `assets/css/` — arquivos de estilo.
- `clientes/` — cadastro e gerenciamento de clientes.
- `config/` — configurações do banco de dados.
- `database/` — estrutura SQL do banco.
- `includes/` — componentes reutilizáveis e proteção de páginas.
- `profissionais/` — gerenciamento de profissionais.
- `servicos/` — gerenciamento de serviços.
- `criar_admin.php` — criação inicial do usuário administrador pelo terminal.
- `login.php` — autenticação.
- `index.php` — painel principal.

## ⚙️ Como executar o projeto

**Pré-requisitos:**

- PHP 8 ou superior.
- MySQL ou MariaDB.
- XAMPP ou ambiente equivalente.
- Extensões PHP PDO MySQL e mbstring.

**Instalação:**

1. Baixe ou clone este repositório.
2. Coloque a pasta `barbearia-agenda` dentro de `C:\xampp\htdocs\`.
3. Inicie Apache e MySQL pelo XAMPP.
4. Acesse o phpMyAdmin e crie um banco chamado `barbearia_agenda`.
5. Importe o arquivo `database/barbearia_agenda.sql`.
6. Copie `config/config.example.php` para `config/config.local.php`.
7. Configure os dados de conexão com seu banco local.
8. Abra o terminal na raiz do projeto e execute `php criar_admin.php` para cadastrar o administrador inicial.
9. Acesse `http://localhost/barbearia-agenda/login.php` pelo navegador.

**Observação:** o arquivo `config.local.php` contém as configurações locais e não deve ser enviado ao GitHub.

## 📸 Capturas de tela

As imagens do sistema serão adicionadas nesta seção para apresentar o login, o painel e as funcionalidades principais.

## 👨‍💻 Desenvolvedor

Projeto desenvolvido por **pedro1217**, estudante de Desenvolvimento de Sistemas, como parte do aprendizado e aperfeiçoamento em desenvolvimento web.

**GitHub:** https://github.com/pedro1217

---

⭐ Projeto desenvolvido para fins de estudo e composição de portfólio profissional.