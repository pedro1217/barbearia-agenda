<?php

require_once __DIR__ . "/../includes/proteger.php";
require_once __DIR__ . "/../config/conexao.php";

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

$erro = "";
$nome = "";
$telefone = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = (string) ($_POST["csrf_token"] ?? "");
    $nome = trim((string) ($_POST["nome"] ?? ""));
    $telefone = trim((string) ($_POST["telefone"] ?? ""));

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
                "INSERT INTO profissionais (nome, telefone)
                 VALUES (:nome, :telefone)"
            );

            $stmt->execute([
                ":nome" => $nome,
                ":telefone" => $telefoneNumeros === ""
                    ? null
                    : $telefoneNumeros
            ]);

            $_SESSION["mensagem_profissionais"] =
                "Profissional cadastrado com sucesso!";

            header("Location: index.php");
            exit;
        } catch (PDOException $erroBanco) {
            error_log($erroBanco->getMessage());
            $erro = "Não foi possível cadastrar o profissional.";
        }
    }
}

$mensagem = $_SESSION["mensagem_profissionais"] ?? "";
unset($_SESSION["mensagem_profissionais"]);

$stmt = $conexao->query(
    "SELECT id, nome, telefone, ativo
     FROM profissionais
     ORDER BY nome, id"
);

$profissionais = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profissionais | Barbearia Agenda</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <main class="container">
        <a href="../index.php" class="link-voltar">← Voltar ao painel</a>

        <h1>Profissionais</h1>
        <p class="subtitulo">
            Cadastre os barbeiros responsáveis pelos atendimentos.
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
            <h2>Novo profissional</h2>

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
                    placeholder="Nome do profissional"
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
                    placeholder="(38) 99999-9999"
                    value="<?= escapar($telefone) ?>"
                    maxlength="20"
                    autocomplete="tel-national"
                >

                <button type="submit">Cadastrar profissional</button>
            </form>
        </section>

        <section class="bloco">
            <h2>Profissionais cadastrados</h2>

            <?php if (empty($profissionais)): ?>
                <p>Nenhum profissional cadastrado ainda.</p>
            <?php else: ?>
                <div class="tabela-responsiva">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Nome</th>
                                <th scope="col">Telefone</th>
                                <th scope="col">Status</th>
                                <th scope="col">ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profissionais as $profissional): ?>
                                <tr>
                                    <td>
                                        <?= escapar($profissional["nome"]) ?>
                                    </td>

                                    <td>
                                        <?= escapar($profissional["telefone"] ?? "—") ?>
                                    </td>

                                    <td>
                                        <?= $profissional["ativo"] ? "Ativo" : "Inativo" ?>
                                    </td>
                                    <td>
    <a
        href="editar.php?id=<?= (int) $profissional["id"] ?>"
        class="acao-editar"
    >
        Editar
    </a>
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