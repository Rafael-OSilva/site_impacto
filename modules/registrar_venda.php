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

// Obter formas de pagamento
$formas_pagamento = obterFormasPagamento($connection);

// Inicializar variáveis
$mensagem = '';
$erro = '';

// Processar o formulário de venda
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $caixa_aberto) {
    $valor_total = floatval($_POST['valor_total']);
    $forma_pagamento_id = intval($_POST['forma_pagamento_id']);
    $descricao = trim($_POST['descricao'] ?? '');
    $nome_cliente = trim($_POST['nome_cliente'] ?? '');

    // Debug: Log dos dados recebidos
    error_log("Dados do formulário:");
    error_log("Valor total: $valor_total");
    error_log("Forma pagamento ID: $forma_pagamento_id");
    error_log("Descrição: $descricao");
    error_log("Nome cliente: $nome_cliente");

    if ($valor_total <= 0) {
        $erro = "Por favor, insira um valor válido para a venda.";
    } elseif (!$forma_pagamento_id) {
        $erro = "Por favor, selecione uma forma de pagamento.";
    } else {
        // Verificar se é venda "A Receber"
        $stmt_forma = $connection->prepare("SELECT nome FROM formas_pagamento WHERE id = ?");
        $stmt_forma->execute([$forma_pagamento_id]);
        $forma_pagamento = $stmt_forma->fetch();

        if (!$forma_pagamento) {
            $erro = "Forma de pagamento inválida.";
        } else {
            $status_venda = 'concluida';
            $descricao_final = $descricao;

            if ($forma_pagamento['nome'] == 'A Receber') {
                $status_venda = 'a_receber';

                if (empty($nome_cliente)) {
                    $erro = "Para vendas 'A Receber', é obrigatório informar o nome do cliente.";
                } else {
                    // Incluir nome do cliente na descrição
                    $descricao_final = "Cliente: " . $nome_cliente;
                    if (!empty($descricao)) {
                        $descricao_final .= " | " . $descricao;
                    }
                }
            }

            // Se não houve erro, registrar a venda
            if (empty($erro)) {
                $usuario_id = $_SESSION['usuario_id'];
                $caixa_id = $caixa_info['id'];

                error_log("Tentando registrar venda:");
                error_log("Caixa ID: $caixa_id");
                error_log("Usuário ID: $usuario_id");
                error_log("Status: $status_venda");

                $venda_id = registrarVenda($connection, $caixa_id, $usuario_id, $valor_total, $forma_pagamento_id, $descricao_final, $status_venda);

                if ($venda_id) {
                    
                    $mensagem = "Venda registrada com sucesso! Nº #$venda_id";
                    if ($status_venda == 'a_receber') {
                        $mensagem .= " (Pagamento a receber - Cliente: " . htmlspecialchars($nome_cliente) . ")";
                    }

                    // Limpar o formulário após o sucesso
                    $_POST = array();
                } else {
                    $erro = "Erro ao registrar a venda. Por favor, tente novamente.";
                    error_log("Falha ao registrar venda no banco de dados");
                }
            }
        }
    }
}

// Obter nome do usuário da sessão
$nome_usuario = $_SESSION['usuario_nome'] ?? 'Operador';

// Gerar número de venda sequencial
$numero_venda = obterProximoNumeroVenda($connection);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Venda - Caixa Impacto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .payment-option {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .payment-option:hover {
            border-color: var(--secondary);
            background-color: rgba(52, 152, 219, 0.05);
        }

        .payment-option.selected {
            border-color: var(--success);
            background-color: rgba(46, 204, 113, 0.1);
        }

        .payment-option.a-receber.selected {
            border-color: var(--warning);
            background-color: rgba(241, 196, 15, 0.1);
        }

        .cliente-field {
            display: none;
        }

        .cliente-field.visible {
            display: block;
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <?php include '../includes/header.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <main>
            <h1 class="page-title">
                <i class="fas fa-shopping-cart"></i> Registrar Venda
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

            <?php if (!$caixa_aberto): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    O caixa está fechado. Você precisa <a href="abrir_caixa.php">abrir o caixa</a> antes de registrar vendas.
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <span>Nova Venda</span>
                        <span>#<?= $numero_venda ?></span>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="sale-form">
                            <div class="form-group">
                                <label for="valor_total">
                                    <i class="fas fa-money-bill-wave"></i> Valor da Venda (R$)
                                </label>
                                <input type="number" id="valor_total" name="valor_total" step="0.01" min="0.01"
                                    placeholder="0,00" required value="<?= isset($_POST['valor_total']) ? htmlspecialchars($_POST['valor_total']) : '' ?>">
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-credit-card"></i> Forma de Pagamento
                                </label>
                                <div class="payment-methods">
                                    <?php foreach ($formas_pagamento as $forma): ?>
                                        <?php $isAReceber = ($forma['nome'] == 'A Receber'); ?>
                                        <div class="payment-option <?= $isAReceber ? 'a-receber' : '' ?>"
                                            data-method="<?= $forma['id'] ?>"
                                            data-name="<?= htmlspecialchars($forma['nome']) ?>"
                                            data-a-receber="<?= $isAReceber ? 'true' : 'false' ?>">
                                            <div class="payment-icon">
                                                <?php
                                                $icones = [
                                                    'Dinheiro' => 'fa-money-bill-wave',
                                                    'Cartão Débito' => 'fa-credit-card',
                                                    'Cartão Crédito' => 'fa-credit-card',
                                                    'PIX' => 'fa-qrcode',
                                                    'A Receber' => 'fa-clock'
                                                ];
                                                $icone = $icones[$forma['nome']] ?? 'fa-money-bill-wave';
                                                ?>
                                                <i class="fas <?= $icone ?>"></i>
                                            </div>
                                            <div class="payment-name"><?= htmlspecialchars($forma['nome']) ?></div>
                                            <?php if ($isAReceber): ?>
                                                <div style="background-color: var(--warning); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; margin-top: 5px;">
                                                    PAGAMENTO FUTURO
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" id="forma_pagamento_id" name="forma_pagamento_id" required
                                    value="<?= isset($_POST['forma_pagamento_id']) ? htmlspecialchars($_POST['forma_pagamento_id']) : '' ?>">
                            </div>

                            <!-- Campo do Cliente (aparece apenas para "A Receber") -->
                            <div class="form-group cliente-field" id="cliente-field">
                                <label for="nome_cliente">
                                    <i class="fas fa-user"></i> Nome do Cliente *
                                </label>
                                <input type="text" id="nome_cliente" name="nome_cliente"
                                    placeholder="Digite o nome do cliente..."
                                    value="<?= isset($_POST['nome_cliente']) ? htmlspecialchars($_POST['nome_cliente']) : '' ?>">
                                <small style="color: #666;">Obrigatório para vendas a receber</small>
                            </div>

                            <div class="form-group">
                                <label for="descricao">
                                    <i class="fas fa-sticky-note"></i> Descrição dos Produtos/Serviços (Opcional)
                                </label>
                                <textarea id="descricao" name="descricao" rows="3"
                                    placeholder="Descreva os produtos ou serviços vendados..."><?= isset($_POST['descricao']) ? htmlspecialchars($_POST['descricao']) : '' ?></textarea>
                            </div>

                            <button type="submit" class="btn-success btn-block">
                                <i class="fas fa-check"></i> Registrar Venda
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <?php include '../includes/footer.php'; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentOptions = document.querySelectorAll('.payment-option');
            const paymentMethodInput = document.getElementById('forma_pagamento_id');
            const clienteField = document.getElementById('cliente-field');
            const nomeClienteInput = document.getElementById('nome_cliente');

            // Selecionar método de pagamento
            paymentOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remover seleção anterior
                    paymentOptions.forEach(opt => opt.classList.remove('selected'));

                    // Selecionar nova opção
                    this.classList.add('selected');
                    const methodId = this.dataset.method;
                    const isAReceber = this.dataset.aReceber === 'true';

                    paymentMethodInput.value = methodId;

                    // Mostrar/ocultar campo do cliente
                    if (isAReceber) {
                        clienteField.classList.add('visible');
                        nomeClienteInput.required = true;
                    } else {
                        clienteField.classList.remove('visible');
                        nomeClienteInput.required = false;
                    }
                });
            });

            // Verificar se já há um método selecionado (em caso de erro no formulário)
            <?php if (isset($_POST['forma_pagamento_id']) && $_POST['forma_pagamento_id']): ?>
                const selectedMethod = document.querySelector(`.payment-option[data-method="<?= $_POST['forma_pagamento_id'] ?>"]`);
                if (selectedMethod) {
                    selectedMethod.classList.add('selected');
                    const isAReceber = selectedMethod.dataset.aReceber === 'true';

                    if (isAReceber) {
                        clienteField.classList.add('visible');
                        nomeClienteInput.required = true;
                    }
                }
            <?php endif; ?>
        });
    </script>
</body>

</html>