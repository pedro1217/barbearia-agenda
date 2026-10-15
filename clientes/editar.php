<?php

require_once __DIR__ . "/../includes/proteger.php";
require_once __DIR__ . "/../config/conexao.php";

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit("Cliente inválido.");
}

$stmt = $conexao->prepare(
    "SELECT id, nome, telefone, email FROM clientes WHERE id = :id"
);
$stmt->execute([":id" => $id]);

$cliente = $stmt->fetch();

if (!$cliente) {
    http_response_code(404);
    exit("Cliente não encontrado.");
}

$erro = "";
$nome = $cliente["nome"];
$telefone = $cliente["telefone"];
$email = $cliente["email"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = (string) ($_POST["csrf_token"] ?? "");
    $nome = trim((string) ($_POST["nome"] ?? ""));
    $telefone = trim((string) ($_POST["telefone"] ?? ""));
    $email = trim((string) ($_POST["email"] ?? ""));

    $telefoneNumeros = preg_replace("/\D/", "", $telefone);

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
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
            $stmt = $conexao->prepare(
                "UPDATE clientes
                 SET nome = :nome, telefone = :telefone, email = :email
                 WHERE id = :id"
            );

            $stmt->execute([
                ":nome" => $nome,
                ":telefone" => $telefoneNumeros,
                ":email" => $email === "" ? null : $email,
                ":id" => $id
            ]);

            $_SESSION["mensagem"] = "Cliente atualizado com sucesso!";

            header("Location: index.php");
            exit;
        } catch (PDOException $erroBanco) {
            error_log($erroBanco->getMessage());
            $erro = "Não foi possível atualizar o cliente.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar cliente | Barbearia Agenda</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <main class="container">
        <a href="index.php" class="link-voltar">← Voltar aos clientes</a>

        <h1>Editar cliente</h1>

        <?php if ($erro !== ""): ?>
            <div class="alerta" role="alert">
                <?= escapar($erro) ?>
            </div>
        <?php endif; ?>

        <section class="bloco">
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
                    required
                >

                <label for="telefone">Telefone com DDD</label>
                <input
                    type="tel"
                    id="telefone"
                    name="telefone"
                    value="<?= escapar($telefone) ?>"
                    maxlength="20"
                    required
                >

                <label for="email">E-mail (opcional)</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= escapar($email) ?>"
                    maxlength="150"
                >

                <button type="submit">Salvar alterações</button>
            </form>
        </section>
    </main>
</body>
</html>