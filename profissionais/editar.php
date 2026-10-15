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
    exit("Profissional inválido.");
}

$stmt = $conexao->prepare(
    "SELECT id, nome, telefone, ativo
     FROM profissionais
     WHERE id = :id"
);

$stmt->execute([":id" => $id]);
$profissional = $stmt->fetch();

if (!$profissional) {
    http_response_code(404);
    exit("Profissional não encontrado.");
}

$erro = "";
$nome = $profissional["nome"];
$telefone = $profissional["telefone"] ?? "";
$ativo = (int) $profissional["ativo"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = (string) ($_POST["csrf_token"] ?? "");
    $nome = trim((string) ($_POST["nome"] ?? ""));
    $telefone = trim((string) ($_POST["telefone"] ?? ""));
    $ativo = isset($_POST["ativo"]) ? 1 : 0;

    $telefoneNumeros = preg_replace("/\D/", "", $telefone);

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $erro = "Solicitação inválida. Recarregue a página.";
    } elseif ($nome === "" || mb_strlen($nome) > 100) {
        $erro = "Informe um nome com até 100 caracteres.";
    } elseif (
        $telefone !== "" &&
        (
            strlen($telefoneNumeros) < 10 ||
            strlen($telefoneNumeros) > 11
        )
    ) {
        $erro = "Informe um telefone com DDD, com 10 ou 11 números.";
    } else {
        try {
            $stmt = $conexao->prepare(
                "UPDATE profissionais
                 SET nome = :nome,
                     telefone = :telefone,
                     ativo = :ativo
                 WHERE id = :id"
            );

            $stmt->execute([
                ":nome" => $nome,
                ":telefone" => $telefoneNumeros === ""
                    ? null
                    : $telefoneNumeros,
                ":ativo" => $ativo,
                ":id" => $id
            ]);

            $_SESSION["mensagem_profissionais"] =
                "Profissional atualizado com sucesso!";

            header("Location: index.php");
            exit;
        } catch (PDOException $erroBanco) {
            error_log($erroBanco->getMessage());
            $erro = "Não foi possível atualizar o profissional.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar profissional | Barbearia Agenda</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <main class="container">
        <a href="index.php" class="link-voltar">
            ← Voltar aos profissionais
        </a>

        <h1>Editar profissional</h1>

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
                    autocomplete="name"
                    required
                >

                <label for="telefone">Telefone com DDD (opcional)</label>
                <input
                    type="tel"
                    id="telefone"
                    name="telefone"
                    value="<?= escapar($telefone) ?>"
                    maxlength="20"
                    autocomplete="tel-national"
                >

                <label class="campo-checkbox" for="ativo">
                    <input
                        type="checkbox"
                        id="ativo"
                        name="ativo"
                        value="1"
                        <?= $ativo === 1 ? "checked" : "" ?>
                    >
                    Profissional ativo
                </label>

                <p class="subtitulo">
                    Desmarque para desativar o profissional.
                    Os agendamentos existentes serão preservados.
                </p>

                <button type="submit">Salvar alterações</button>
            </form>
        </section>
    </main>
</body>
</html>