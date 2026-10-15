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
    exit("Serviço inválido.");
}

$stmt = $conexao->prepare(
    "SELECT id, nome, preco, duracao_minutos, ativo
     FROM servicos
     WHERE id = :id"
);

$stmt->execute([":id" => $id]);
$servico = $stmt->fetch();

if (!$servico) {
    http_response_code(404);
    exit("Serviço não encontrado.");
}

$erro = "";
$nome = $servico["nome"];
$preco = str_replace(".", ",", $servico["preco"]);
$duracao = (string) $servico["duracao_minutos"];
$ativo = (int) $servico["ativo"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = (string) ($_POST["csrf_token"] ?? "");
    $nome = trim((string) ($_POST["nome"] ?? ""));
    $preco = trim((string) ($_POST["preco"] ?? ""));
    $duracao = trim((string) ($_POST["duracao"] ?? ""));
    $ativo = isset($_POST["ativo"]) ? 1 : 0;

    $precoNormalizado = str_replace(",", ".", $preco);

    $duracaoValidada = filter_var(
        $duracao,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1, "max_range" => 480]]
    );

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $erro = "Solicitação inválida. Recarregue a página.";
    } elseif ($nome === "" || mb_strlen($nome) > 100) {
        $erro = "Informe um nome com até 100 caracteres.";
    } elseif (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $precoNormalizado)) {
        $erro = "Informe um preço válido, como 35,00, sem separador de milhar.";
    } elseif ($duracaoValidada === false) {
        $erro = "A duração precisa ser um número inteiro entre 1 e 480 minutos.";
    } else {
        try {
            $stmt = $conexao->prepare(
                "UPDATE servicos
                 SET nome = :nome,
                     preco = :preco,
                     duracao_minutos = :duracao,
                     ativo = :ativo
                 WHERE id = :id"
            );

            $stmt->execute([
                ":nome" => $nome,
                ":preco" => $precoNormalizado,
                ":duracao" => $duracaoValidada,
                ":ativo" => $ativo,
                ":id" => $id
            ]);

            $_SESSION["mensagem_servicos"] =
                "Serviço atualizado com sucesso!";

            header("Location: index.php");
            exit;
        } catch (PDOException $erroBanco) {
            error_log($erroBanco->getMessage());
            $erro = "Não foi possível atualizar o serviço.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar serviço | Barbearia Agenda</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <main class="container">
        <a href="index.php" class="link-voltar">← Voltar aos serviços</a>

        <h1>Editar serviço</h1>

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

                <label for="nome">Nome do serviço</label>
                <input
                    type="text"
                    id="nome"
                    name="nome"
                    value="<?= escapar($nome) ?>"
                    maxlength="100"
                    required
                >

                <label for="preco">Preço (R$)</label>
                <input
                    type="text"
                    id="preco"
                    name="preco"
                    inputmode="decimal"
                    value="<?= escapar($preco) ?>"
                    maxlength="11"
                    required
                >

                <label for="duracao">Duração em minutos</label>
                <input
                    type="number"
                    id="duracao"
                    name="duracao"
                    value="<?= escapar($duracao) ?>"
                    min="1"
                    max="480"
                    step="1"
                    required
                >

                <label class="campo-checkbox" for="ativo">
                    <input
                        type="checkbox"
                        id="ativo"
                        name="ativo"
                        value="1"
                        <?= $ativo === 1 ? "checked" : "" ?>
                    >
                    Serviço ativo
                </label>

                <p class="subtitulo">
                    Desmarque para desativar o serviço.
                    Você poderá ativá-lo novamente depois.
                </p>

                <button type="submit">Salvar alterações</button>
            </form>
        </section>
    </main>
</body>
</html>