<?php

require_once __DIR__ . "/../includes/proteger.php";
require_once __DIR__ . "/../config/conexao.php";

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

$erro = "";
$nome = "";
$preco = "";
$duracao = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = (string) ($_POST["csrf_token"] ?? "");
    $nome = trim((string) ($_POST["nome"] ?? ""));
    $preco = trim((string) ($_POST["preco"] ?? ""));
    $duracao = trim((string) ($_POST["duracao"] ?? ""));

    // Aceita tanto 35,50 quanto 35.50, sem separador de milhar.
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
                "INSERT INTO servicos (nome, preco, duracao_minutos)
                 VALUES (:nome, :preco, :duracao)"
            );

            $stmt->execute([
                ":nome" => $nome,
                ":preco" => $precoNormalizado,
                ":duracao" => $duracaoValidada
            ]);

            $_SESSION["mensagem_servicos"] =
                "Serviço cadastrado com sucesso!";

            header("Location: index.php");
            exit;
        } catch (PDOException $erroBanco) {
            error_log($erroBanco->getMessage());
            $erro = "Não foi possível cadastrar o serviço.";
        }
    }
}

$mensagem = $_SESSION["mensagem_servicos"] ?? "";
unset($_SESSION["mensagem_servicos"]);

$stmt = $conexao->query(
    "SELECT id, nome, preco, duracao_minutos, ativo
     FROM servicos
     ORDER BY nome, id"
);

$servicos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Serviços | Barbearia Agenda</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <main class="container">
        <a href="../index.php" class="link-voltar">← Voltar ao painel</a>

        <h1>Serviços</h1>
        <p class="subtitulo">
            Organize os serviços, os preços e o tempo de atendimento.
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
            <h2>Novo serviço</h2>

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
                    placeholder="Ex.: Corte de cabelo"
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
                    placeholder="Ex.: 35,00"
                    value="<?= escapar($preco) ?>"
                    maxlength="11"
                    required
                >

                <label for="duracao">Duração em minutos</label>
                <input
                    type="number"
                    id="duracao"
                    name="duracao"
                    placeholder="Ex.: 30"
                    value="<?= escapar($duracao) ?>"
                    min="1"
                    max="480"
                    step="1"
                    required
                >

                <button type="submit">Cadastrar serviço</button>
            </form>
        </section>

        <section class="bloco">
            <h2>Serviços cadastrados</h2>

            <?php if (empty($servicos)): ?>
                <p>Nenhum serviço cadastrado ainda.</p>
            <?php else: ?>
                <div class="tabela-responsiva">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Serviço</th>
                                <th scope="col">Preço</th>
                                <th scope="col">Duração</th>
                                <th scope="col">Status</th>
                                <th scope="col">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($servicos as $servico): ?>
                                <tr>
                                    <td><?= escapar($servico["nome"]) ?></td>

                                    <td>
                                        R$ <?= number_format(
                                            (float) $servico["preco"],
                                            2,
                                            ",",
                                            "."
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= (int) $servico["duracao_minutos"] ?> min
                                    </td>

                                    <td>
                                        <?= $servico["ativo"] ? "Ativo" : "Inativo" ?>
                                    </td>
                                    <td>
                                        <a
                                          href="editar.php?id=<?= (int) $servico["id"] ?>"
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