<?php
session_start();
// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

// Definir o diretório base
define('BASE_PATH', dirname(__DIR__));

// Incluir configurações e funções com caminho absoluto
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/functions.php';

// Conectar ao banco de dados
$db = new Database();
$connection = $db->getConnection();

// Verificar se o caixa já está aberto
$status_caixa = verificarStatusCaixa($connection);
$caixa_aberto = verificarStatusCaixa($connection);

// Processar o formulário de abertura de caixa
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $valor_inicial = floatval($_POST['valor_inicial']);
    $observacao = $_POST['observacao'] ?? '';
    
    if ($valor_inicial <= 0) {
        $erro = "Por favor, insira um valor válido para abrir o caixa.";
    } elseif ($caixa_aberto) {
        $erro = "Já existe um caixa aberto. Feche o caixa atual antes de abrir outro.";
    } else {
        // Abrir o caixa
        $usuario_id = $_SESSION['usuario_id'];
        if (abrirCaixa($connection, $usuario_id, $valor_inicial, $observacao)) {
            $mensagem = "Caixa aberto com sucesso!";
            $caixa_aberto = true;
            
            // Redirecionar após 2 segundos
            header("refresh:2;url=../index.php");
        } else {
            $erro = "Erro ao abrir o caixa. Por favor, tente novamente.";
        }
    }
}

// Obter nome do usuário da sessão
$nome_usuario = $_SESSION['usuario_nome'] ?? 'Operador';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abrir Caixa - Caixa Impacto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <div class="container">
        <?php include '../includes/header.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <main>
            <h1 class="page-title">
                <i class="fas fa-door-open"></i> Abrir Caixa
            </h1>

            <?php if ($mensagem): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $mensagem ?>
                </div>
            <?php endif; ?>

            <?php if ($erro): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $erro ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <span>Abertura de Caixa</span>
                    <span class="caixa-status <?= $caixa_aberto ? 'status-aberto' : 'status-fechado' ?>">
                        <?= $caixa_aberto ? 'Caixa Aberto' : 'Caixa Fechado' ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="info-box">
                        <div class="info-title">Informação</div>
                        <div class="info-text">
                            Insira o valor inicial em dinheiro para abrir o caixa.
                            Este valor será utilizado como base para o fechamento no final do dia.
                        </div>
                    </div>

                    <?php if (!$caixa_aberto): ?>
                        <form method="POST" id="abrir-caixa-form">
                            <div class="form-group">
                                <label for="valor-inicial">
                                    <i class="fas fa-money-bill-wave"></i> Valor Inicial (R$)
                                </label>
                                <input type="number" id="valor-inicial" name="valor_inicial" step="0.01" min="0" placeholder="0,00" required>
                            </div>

                            <div class="form-group">
                                <label for="observacao">
                                    <i class="fas fa-sticky-note"></i> Observação (Opcional)
                                </label>
                                <textarea id="observacao" name="observacao" rows="3" placeholder="Alguma observação sobre a abertura do caixa..."></textarea>
                            </div>

                            <button type="submit" class="btn-success btn-block">
                                <i class="fas fa-check"></i> Confirmar Abertura do Caixa
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            O caixa já está aberto. Você pode <a href="../index.php">voltar ao dashboard</a>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <?php include '../includes/footer.php'; ?>
    </div>

    <script src="../js/scripts.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('abrir-caixa-form');
            const valorInput = document.getElementById('valor-inicial');

            // Formatar o valor monetário enquanto digita
            if (valorInput) {
                valorInput.addEventListener('blur', function () {
                    if (this.value) {
                        this.value = parseFloat(this.value).toFixed(2);
                    }
                });

                // Validação do formulário
                form.addEventListener('submit', function (e) {
                    const valorInicial = parseFloat(valorInput.value);

                    if (!valorInicial || valorInicial <= 0) {
                        e.preventDefault();
                        alert('Por favor, insira um valor válido para abrir o caixa.');
                        return;
                    }
                });
            }
        });
    </script>
</body>
</html>