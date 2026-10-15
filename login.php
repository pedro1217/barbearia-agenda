<?php

session_start();

require_once __DIR__ . "/config/conexao.php";

if (isset($_SESSION["usuario_id"])) {
    header("Location: index.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$erro = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim((string) ($_POST["email"] ?? ""));
    $senha = (string) ($_POST["senha"] ?? "");
    $token = (string) ($_POST["csrf_token"] ?? "");

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $erro = "Recarregue a página e tente novamente.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === "") {
        $erro = "Informe um e-mail válido e sua senha.";
    } else {
        $stmt = $conexao->prepare(
            "SELECT id, nome, senha FROM usuarios WHERE email = :email"
        );

        $stmt->execute([":email" => $email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario["senha"])) {
            session_regenerate_id(true);

            $_SESSION["usuario_id"] = $usuario["id"];
            $_SESSION["usuario_nome"] = $usuario["nome"];
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            header("Location: index.php");
            exit;
        }

        $erro = "E-mail ou senha incorretos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Barbearia Agenda</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pagina-login">

    <main class="cartao-login">
        <span class="marca">BARBEARIA AGENDA</span>

        <h1>Bem-vindo de volta</h1>
        <p class="subtitulo">Entre para gerenciar seus atendimentos.</p>

        <?php if ($erro !== ""): ?>
            <div class="alerta" role="alert">
                <?= htmlspecialchars($erro, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>"
            >

            <label for="email">E-mail</label>
            <input
                type="email"
                id="email"
                name="email"
                placeholder="voce@exemplo.com"
                value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
                maxlength="150"
                autocomplete="username"
                required
            >

            <label for="senha">Senha</label>
            <input
                type="password"
                id="senha"
                name="senha"
                placeholder="Digite sua senha"
                autocomplete="current-password"
                required
            >

            <button type="submit">Entrar no sistema</button>
        </form>
    </main>

</body>
</html>