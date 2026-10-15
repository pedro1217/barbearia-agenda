<?php

require_once __DIR__ . "/../includes/proteger.php";
require_once __DIR__ . "/../config/conexao.php";

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

$erro = $_SESSION["erro_clientes"] ?? "";
unset($_SESSION["erro_clientes"]);
$nome = "";
$telefone = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = (string) ($_POST["csrf_token"] ?? "");
    $nome = trim((string) ($_POST["nome"] ?? ""));
    $telefone = trim((string) ($_POST["telefone"] ?? ""));
    $email = trim((string) ($_POST["email"] ?? ""));

    $telefoneNumeros = preg_replace("/\D/", "", $telefone);
    $tokenSessao = (string) ($_SESSION["csrf_token"] ?? "");

    if (
        $token === "" ||
        $tokenSessao === "" ||
        !hash_equals($tokenSessao, $token)
    ) {
        $erro = "Solicitação inválida. Recarregue a página.";
    } elseif ($nome === "" || mb_strlen($nome) > 100) {
        $erro = "Informe um nome com até 100 caracteres.";
    } elseif (
        strlen($telefoneNumeros) < 10 ||
        strlen($telefoneNumeros) > 11
    ) {
        $erro = "Informe um telefone com DDD, com 10 ou 11 números.";
    } elseif (
        $email !== "" &&
        (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150)
    ) {
        $erro = "Informe um e-mail válido com até 150 caracteres.";
    } else {
        try {
            $sql = "INSERT INTO clientes (nome, telefone, email)
                    VALUES (:nome, :telefone, :email)";

            $stmt = $conexao->prepare($sql);

            $stmt->execute([
                ":nome" => $nome,
                ":telefone" => $telefoneNumeros,
                ":email" => $email === "" ? null : $email
            ]);

            $_SESSION["mensagem"] = "Cliente cadastrado com sucesso!";

            header("Location: index.php");
            exit;
        } catch (PDOException $erroBanco) {
            error_log($erroBanco->getMessage());
            $erro = "Não foi possível cadastrar o cliente. Tente novamente.";
        }
    }
}

$mensagem = $_SESSION["mensagem"] ?? "";
unset($_SESSION["mensagem"]);

$stmt = $conexao->query(
    "SELECT id, nome, telefone, email
     FROM clientes
     ORDER BY nome, id"
);

$clientes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clientes | Barbearia Agenda</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

    <main class="container">
        <a href="../index.php" class="link-voltar">← Voltar ao painel</a>

        <h1>Clientes</h1>
        <p class="subtitulo">
            Cadastre e consulte os clientes da barbearia.
        </p>

        <?php if ($erro !== ""): ?>
            <div class="alerta" role="alert">
                <?= escapar($erro) ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagem !== ""): ?>
            <div class="sucesso" role="status">
                <?= escapar($mensagem) ?>
            </div>
        <?php endif; ?>

        <section class="bloco">
            <h2>Novo cliente</h2>

            <form method="POST">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escapar($_SESSION["csrf_token"]) ?>"
                >

                <label for="nome">Nome completo</label>
                <input
                    type="text"
                    id="nome"
                    name="nome"
                    value="<?= escapar($nome) ?>"
                    maxlength="100"
                    autocomplete="name"
                    required
                >

                <label for="telefone">Telefone com DDD</label>
                <input
                    type="tel"
                    id="telefone"
                    name="telefone"
                    value="<?= escapar($telefone) ?>"
                    placeholder="(38) 99999-9999"
                    maxlength="20"
                    autocomplete="tel-national"
                    required
                >

                <label for="email">E-mail (opcional)</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= escapar($email) ?>"
                    maxlength="150"
                    autocomplete="email"
                >

                <button type="submit">Cadastrar cliente</button>
            </form>
        </section>

        <section class="bloco">
            <h2>Clientes cadastrados</h2>

            <?php if (empty($clientes)): ?>
                <p>Nenhum cliente cadastrado ainda.</p>
            <?php else: ?>
                <div class="tabela-responsiva">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Nome</th>
                                <th scope="col">Telefone</th>
                                <th scope="col">E-mail</th>
                                <th scope="col">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
    <?php foreach ($clientes as $cliente): ?>
        <tr>
            <td><?= escapar($cliente["nome"]) ?></td>
            <td><?= escapar($cliente["telefone"]) ?></td>
            <td><?= escapar($cliente["email"] ?? "—") ?></td>

            <td>
                <div class="acoes">
                    <a
                        href="editar.php?id=<?= (int) $cliente["id"] ?>"
                        class="acao-editar"
                    >
                        Editar
                    </a>

                    <form
                        action="excluir.php"
                        method="POST"
                        onsubmit="return confirm('Deseja excluir este cliente? Esta ação não pode ser desfeita.');"
                    >
                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $cliente["id"] ?>"
                        >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= escapar($_SESSION["csrf_token"]) ?>"
                        >

                        <button type="submit" class="acao-excluir">
                            Excluir
                        </button>
                    </form>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

</body>
</html>