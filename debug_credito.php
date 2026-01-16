na verdade é esse... 
<?php
session_start();
// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

// Habilitar erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir configurações e funções
require_once '../config/database.php';
require_once '../includes/functions.php';

// Conectar ao banco de dados
try {
    $db = new Database();
    $connection = $db->getConnection();
} catch (Exception $e) {
    die("❌ Erro de conexão com o banco: " . $e->getMessage());
}

$titulo = "Crédito de Clientes";
$mensagem = '';
$erro = '';

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cadastro de cliente
    if (isset($_POST['acao']) && $_POST['acao'] === 'cadastrar') {
        try {
            // Coletar dados do formulário
            $dados = [
                'nome' => trim($_POST['nome'] ?? ''),
                'cpf' => trim($_POST['cpf'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'telefone' => trim($_POST['telefone'] ?? ''),
                'valor_credito' => $_POST['valor_credito'] ?? '0,00'
            ];

            // Log dos dados recebidos
            error_log("📋 Dados recebidos para cadastro: " . json_encode($dados));

            // Validações básicas
            if (empty($dados['nome'])) {
                throw new Exception("O nome do cliente é obrigatório.");
            }

            if (strlen($dados['nome']) < 3) {
                throw new Exception("O nome deve ter pelo menos 3 caracteres.");
            }

            // Validar CPF se fornecido
            if (!empty($dados['cpf']) && !validarCPF($dados['cpf'])) {
                throw new Exception("CPF inválido.");
            }

            // Cadastrar cliente
            $clienteId = cadastrarCliente($connection, $dados);

            if ($clienteId) {
                $mensagem = "✅ Cliente cadastrado com sucesso! ID: " . $clienteId;
                error_log("✅ Cliente cadastrado com sucesso! ID: " . $clienteId);
            } else {
                throw new Exception("Erro ao cadastrar cliente no banco de dados.");
            }
        } catch (Exception $e) {
            $erro = "❌ " . $e->getMessage();
            error_log("❌ Erro ao cadastrar cliente: " . $e->getMessage());
        }
    }
    
    // Atualização de crédito via AJAX
    if (isset($_POST['acao']) && $_POST['acao'] === 'atualizar_credito') {
        header('Content-Type: application/json');

        try {
            $cliente_id = intval($_POST['cliente_id'] ?? 0);
            $novo_valor = isset($_POST['novo_valor']) ?
                floatval(str_replace(',', '.', $_POST['novo_valor'])) : 0;
            $observacao = trim($_POST['observacao'] ?? '');

            // Validações
            if ($cliente_id <= 0) {
                throw new Exception("ID do cliente inválido.");
            }

            if ($novo_valor < 0) {
                throw new Exception("O valor do crédito não pode ser negativo.");
            }

            // Verificar se cliente existe
            $cliente = buscarClientePorId($connection, $cliente_id);
            if (!$cliente) {
                throw new Exception("Cliente não encontrado.");
            }

            // Atualizar crédito
            if (atualizarCreditoCliente($connection, $cliente_id, $novo_valor, $observacao)) {
                echo json_encode([
                    'success' => true,
                    'message' => '✅ Crédito atualizado com sucesso!',
                    'cliente_nome' => $cliente['nome'],
                    'novo_valor' => number_format($novo_valor, 2, ',', '.')
                ]);
            } else {
                throw new Exception("Erro ao atualizar crédito no banco de dados.");
            }
        } catch (Exception $e) {
            error_log("Erro atualizar crédito: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => '❌ ' . $e->getMessage()
            ]);
        }
        exit;
    }
    
    // EXCLUSÃO DE CLIENTE VIA AJAX
    if (isset($_POST['acao']) && $_POST['acao'] === 'excluir_cliente') {
        header('Content-Type: application/json');
        
        try {
            $cliente_id = intval($_POST['cliente_id'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? '');
            
            // Validações
            if ($cliente_id <= 0) {
                throw new Exception("ID do cliente inválido.");
            }
            
            if (empty($motivo)) {
                throw new Exception("Informe o motivo da exclusão.");
            }
            
            // Verificar se cliente existe
            $cliente = buscarClientePorId($connection, $cliente_id);
            if (!$cliente) {
                throw new Exception("Cliente não encontrado.");
            }
            
            // Excluir cliente
            if (excluirCliente($connection, $cliente_id, $_SESSION['usuario_id'], $motivo)) {
                echo json_encode([
                    'success' => true,
                    'message' => '✅ Cliente excluído com sucesso!',
                    'cliente_nome' => $cliente['nome']
                ]);
            } else {
                throw new Exception("Erro ao excluir cliente no banco de dados.");
            }
        } catch(Exception $e) {
            error_log("Erro ao excluir cliente: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => '❌ ' . $e->getMessage()
            ]);
        }
        exit;
    }
}

// Obter dados para exibição
try {
    $clientes_com_credito = listarClientesComCredito($connection);
    $todos_clientes = listarTodosClientes($connection);
} catch (Exception $e) {
    $erro = "❌ Erro ao carregar dados: " . $e->getMessage();
    $clientes_com_credito = [];
    $todos_clientes = [];
}

// Calcular estatísticas
$total_clientes = count($todos_clientes);
$clientes_com_credito_count = count($clientes_com_credito);
$total_credito = 0;

foreach ($clientes_com_credito as $cliente) {
    $total_credito += floatval($cliente['valor_credito']);
}

$is_module_page = true;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> - Caixa Impacto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Seus estilos CSS permanecem os mesmos */
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body {
            padding: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .table-responsive {
            overflow-x: auto;
            margin-top: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-success {
            background-color: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .badge-info {
            background-color: rgba(52, 152, 219, 0.1);
            color: #2980b9;
        }

        .valor-credito {
            font-weight: bold;
            color: #27ae60;
        }

        .valor-zero {
            color: #95a5a6;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-top: 4px solid #3498db;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #3498db;
            margin: 10px 0;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 500px;
        }

        .cliente-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid #f39c12;
        }

        .cliente-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            color: #27ae60;
            border: 1px solid rgba(46, 204, 113, 0.2);
        }

        .alert-error {
            background-color: rgba(231, 76, 60, 0.1);
            color: #c0392b;
            border: 1px solid rgba(231, 76, 60, 0.2);
        }
        
        .alert-warning {
            background-color: rgba(241, 196, 15, 0.1);
            color: #d35400;
            border: 1px solid rgba(241, 196, 15, 0.2);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-editar {
            background: #f39c12;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 0.85rem;
            margin-right: 5px;
        }

        .btn-editar:hover {
            background: #e67e22;
        }
        
        .btn-excluir {
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .btn-excluir:hover {
            background: #c0392b;
        }
        
        .btn-excluir:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }

        .page-title {
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        small {
            font-size: 0.85rem;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php include '../includes/header.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <main>
            <h1 class="page-title">
                <i class="fas fa-credit-card"></i> <?= htmlspecialchars($titulo) ?>
            </h1>

            <!-- Mensagens -->
            <?php if ($mensagem): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensagem) ?>
                </div>
            <?php endif; ?>

            <?php if ($erro): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?>
                </div>
            <?php endif; ?>

            <!-- Estatísticas -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-value"><?= $total_clientes ?></div>
                    <div class="stat-label">Total de Clientes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $clientes_com_credito_count ?></div>
                    <div class="stat-label">Clientes com Crédito</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">R$ <?= number_format($total_credito, 2, ',', '.') ?></div>
                    <div class="stat-label">Crédito Total</div>
                </div>
            </div>

            <!-- Formulário de Cadastro -->
            <div class="card">
                <div class="card-header">
                    <span>Cadastrar Novo Cliente</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="acao" value="cadastrar">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="nome">Nome Completo *</label>
                                <input type="text" id="nome" name="nome" required
                                    placeholder="Digite o nome do cliente">
                            </div>

                            <div class="form-group">
                                <label for="cpf">CPF</label>
                                <input type="text" id="cpf" name="cpf"
                                    placeholder="000.000.000-00" class="cpf-input">
                            </div>

                            <div class="form-group">
                                <label for="email">E-mail</label>
                                <input type="email" id="email" name="email"
                                    placeholder="cliente@email.com">
                            </div>

                            <div class="form-group">
                                <label for="telefone">Telefone</label>
                                <input type="text" id="telefone" name="telefone"
                                    placeholder="(11) 99999-9999" class="phone-input">
                            </div>

                            <div class="form-group">
                                <label for="valor_credito">Crédito Inicial (R$)</label>
                                <input type="text" id="valor_credito" name="valor_credito"
                                    placeholder="0,00" value="0,00">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Cadastrar Cliente
                        </button>
                    </form>
                </div>
            </div>

            <!-- Clientes com Crédito -->
            <div class="card">
                <div class="card-header">
                    <span>Clientes com Crédito Disponível</span>
                    <span class="badge badge-success"><?= $clientes_com_credito_count ?> cliente(s)</span>
                </div>
                <div class="card-body">
                    <?php if (count($clientes_com_credito) > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>CPF</th>
                                        <th>Telefone</th>
                                        <th>Crédito</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clientes_com_credito as $cliente): ?>
                                        <tr>
                                            <td><?= $cliente['id'] ?></td>
                                            <td><?= htmlspecialchars($cliente['nome']) ?></td>
                                            <td><?= $cliente['cpf'] ? htmlspecialchars($cliente['cpf']) : '-' ?></td>
                                            <td><?= $cliente['telefone'] ? htmlspecialchars($cliente['telefone']) : '-' ?></td>
                                            <td class="valor-credito">
                                                R$ <?= number_format($cliente['valor_credito'], 2, ',', '.') ?>
                                            </td>
                                            <td>
                                                <button class="btn-editar"
                                                    onclick="abrirModalCredito(
                                                    <?= $cliente['id'] ?>,
                                                    '<?= addslashes($cliente['nome']) ?>',
                                                    <?= $cliente['valor_credito'] ?>,
                                                    '<?= addslashes($cliente['cpf'] ?? '') ?>',
                                                    '<?= addslashes($cliente['telefone'] ?? '') ?>'
                                                )">
                                                    <i class="fas fa-edit"></i> Editar
                                                </button>
                                                <?php if ($cliente['valor_credito'] == 0): ?>
                                                <button class="btn-excluir" 
                                                        onclick="abrirModalExclusao(
                                                            <?= $cliente['id'] ?>,
                                                            '<?= addslashes($cliente['nome']) ?>'
                                                        )"
                                                        title="Excluir cliente">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-wallet"></i>
                            <h3>Nenhum cliente com crédito</h3>
                            <p>Cadastre um novo cliente com crédito ou adicione crédito a um cliente existente.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Todos os Clientes -->
            <div class="card">
                <div class="card-header">
                    <span>Todos os Clientes</span>
                    <span class="badge badge-info">Total: <?= $total_clientes ?> cliente(s)</span>
                </div>
                <div class="card-body">
                    <?php if (count($todos_clientes) > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>CPF</th>
                                        <th>Telefone</th>
                                        <th>Crédito</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($todos_clientes as $cliente): ?>
                                        <tr>
                                            <td><?= $cliente['id'] ?></td>
                                            <td><?= htmlspecialchars($cliente['nome']) ?></td>
                                            <td><?= $cliente['cpf'] ? htmlspecialchars($cliente['cpf']) : '-' ?></td>
                                            <td><?= $cliente['telefone'] ? htmlspecialchars($cliente['telefone']) : '-' ?></td>
                                            <td>
                                                <?php if ($cliente['valor_credito'] > 0): ?>
                                                    <span class="badge badge-success">
                                                        R$ <?= number_format($cliente['valor_credito'], 2, ',', '.') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="valor-zero">R$ 0,00</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn-editar"
                                                    onclick="abrirModalCredito(
                                                    <?= $cliente['id'] ?>,
                                                    '<?= addslashes($cliente['nome']) ?>',
                                                    <?= $cliente['valor_credito'] ?>,
                                                    '<?= addslashes($cliente['cpf'] ?? '') ?>',
                                                    '<?= addslashes($cliente['telefone'] ?? '') ?>'
                                                )">
                                                    <i class="fas fa-edit"></i> Crédito
                                                </button>
                                                <?php if ($cliente['valor_credito'] == 0): ?>
                                                <button class="btn-excluir" 
                                                        onclick="abrirModalExclusao(
                                                            <?= $cliente['id'] ?>,
                                                            '<?= addslashes($cliente['nome']) ?>'
                                                        )"
                                                        title="Excluir cliente">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-plus"></i>
                            <h3>Nenhum cliente cadastrado</h3>
                            <p>Use o formulário acima para cadastrar seu primeiro cliente.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <?php include '../includes/footer.php'; ?>
    </div>

    <!-- Modal de Edição de Crédito -->
    <div class="modal" id="modalCredito">
        <div class="modal-content">
            <h3><i class="fas fa-edit"></i> Editar Crédito do Cliente</h3>

            <div class="cliente-info" id="clienteInfo">
                <!-- Informações serão preenchidas via JavaScript -->
            </div>

            <form id="formCredito" onsubmit="atualizarCredito(event)">
                <input type="hidden" name="acao" value="atualizar_credito">
                <input type="hidden" id="cliente_id" name="cliente_id">

                <div class="form-group">
                    <label for="novo_valor">Novo Valor de Crédito (R$)</label>
                    <input type="text" id="novo_valor" name="novo_valor"
                        required placeholder="0,00">
                </div>

                <div class="form-group">
                    <label for="observacao">Observação (opcional)</label>
                    <textarea id="observacao" name="observacao"
                        rows="3"
                        placeholder="Motivo da alteração..."></textarea>
                </div>

                <div id="modalMensagem"></div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="fecharModalCredito()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnAtualizar">
                        <i class="fas fa-save"></i> Atualizar Crédito
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de Exclusão de Cliente -->
    <div class="modal" id="modalExcluir">
        <div class="modal-content">
            <h3><i class="fas fa-trash"></i> Excluir Cliente</h3>
            
            <div class="cliente-info" id="clienteInfoExcluir">
                <!-- Informações serão preenchidas via JavaScript -->
            </div>
            
            <form id="formExcluir" onsubmit="excluirCliente(event)">
                <input type="hidden" name="acao" value="excluir_cliente">
                <input type="hidden" id="cliente_id_excluir" name="cliente_id">
                
                <div class="form-group">
                    <label for="motivo">Motivo da Exclusão *</label>
                    <textarea id="motivo" name="motivo" 
                              rows="4" 
                              required
                              placeholder="Informe o motivo da exclusão..."></textarea>
                    <small>Esta informação será registrada no histórico.</small>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Atenção:</strong> Esta ação marcará o cliente como inativo. 
                    Clientes com crédito ou vendas pendentes não podem ser excluídos.
                </div>
                
                <div id="modalMensagemExcluir"></div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="fecharModalExclusao()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-danger" id="btnExcluir">
                        <i class="fas fa-trash"></i> Confirmar Exclusão
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Funções para o modal de crédito
        function abrirModalCredito(clienteId, clienteNome, creditoAtual, clienteCpf, clienteTelefone) {
            // Preencher informações do cliente
            const clienteInfo = document.getElementById('clienteInfo');
            clienteInfo.innerHTML = `
                <div class="cliente-info-row">
                    <span><strong>Cliente:</strong></span>
                    <span>${clienteNome}</span>
                </div>
                ${clienteCpf ? `
                <div class="cliente-info-row">
                    <span><strong>CPF:</strong></span>
                    <span>${clienteCpf}</span>
                </div>
                ` : ''}
                ${clienteTelefone ? `
                <div class="cliente-info-row">
                    <span><strong>Telefone:</strong></span>
                    <span>${clienteTelefone}</span>
                </div>
                ` : ''}
                <div class="cliente-info-row">
                    <span><strong>Crédito Atual:</strong></span>
                    <strong style="color: #27ae60;">R$ ${parseFloat(creditoAtual).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>
                </div>
            `;

            // Configurar formulário
            document.getElementById('cliente_id').value = clienteId;
            document.getElementById('novo_valor').value = parseFloat(creditoAtual).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            document.getElementById('novo_valor').focus();
            document.getElementById('modalMensagem').innerHTML = '';

            // Abrir modal
            document.getElementById('modalCredito').classList.add('active');
        }

        function fecharModalCredito() {
            document.getElementById('modalCredito').classList.remove('active');
        }
        
        // Funções para o modal de exclusão
        function abrirModalExclusao(clienteId, clienteNome) {
            // Preencher informações do cliente
            const clienteInfo = document.getElementById('clienteInfoExcluir');
            clienteInfo.innerHTML = `
                <div class="cliente-info-row">
                    <span><strong>Cliente a ser excluído:</strong></span>
                    <span><strong style="color: #e74c3c;">${clienteNome}</strong></span>
                </div>
                <div class="cliente-info-row">
                    <span><strong>ID:</strong></span>
                    <span>${clienteId}</span>
                </div>
                <div class="cliente-info-row">
                    <span><strong>Ação:</strong></span>
                    <span style="color: #e74c3c;">Cliente será marcado como inativo</span>
                </div>
            `;
            
            // Configurar formulário
            document.getElementById('cliente_id_excluir').value = clienteId;
            document.getElementById('motivo').value = '';
            document.getElementById('motivo').focus();
            document.getElementById('modalMensagemExcluir').innerHTML = '';
            
            // Abrir modal
            document.getElementById('modalExcluir').classList.add('active');
        }

        function fecharModalExclusao() {
            document.getElementById('modalExcluir').classList.remove('active');
        }

        // Atualizar crédito via AJAX
        function atualizarCredito(event) {
            event.preventDefault();

            const form = document.getElementById('formCredito');
            const formData = new FormData(form);
            const btnAtualizar = document.getElementById('btnAtualizar');

            // Validar valor
            let novoValorStr = document.getElementById('novo_valor').value;
            // Converter formato brasileiro para decimal
            novoValorStr = novoValorStr.replace(/\./g, '').replace(',', '.');
            const novoValor = parseFloat(novoValorStr);

            if (isNaN(novoValor) || novoValor < 0) {
                mostrarMensagem('error', '❌ Valor inválido. Use números positivos no formato 0,00');
                return;
            }

            // Desabilitar botão e mostrar loading
            btnAtualizar.disabled = true;
            btnAtualizar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';

            // Enviar requisição
            fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(async response => {
                    const data = await response.json();
                    return data;
                })
                .then(data => {
                    if (data.success) {
                        mostrarMensagem('success', data.message);

                        // Recarregar a página após 1.5 segundos
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        mostrarMensagem('error', data.message);
                        btnAtualizar.disabled = false;
                        btnAtualizar.innerHTML = '<i class="fas fa-save"></i> Atualizar Crédito';
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    mostrarMensagem('error', '❌ Erro ao atualizar crédito. Tente novamente.');
                    btnAtualizar.disabled = false;
                    btnAtualizar.innerHTML = '<i class="fas fa-save"></i> Atualizar Crédito';
                });
        }
        
        // Excluir cliente via AJAX
        function excluirCliente(event) {
            event.preventDefault();
            
            const form = document.getElementById('formExcluir');
            const formData = new FormData(form);
            const btnExcluir = document.getElementById('btnExcluir');
            
            // Validar motivo
            const motivo = document.getElementById('motivo').value.trim();
            if (motivo.length < 5) {
                mostrarMensagemExclusao('error', '❌ Informe um motivo com pelo menos 5 caracteres.');
                return;
            }
            
            // Desabilitar botão e mostrar loading
            btnExcluir.disabled = true;
            btnExcluir.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Excluindo...';
            
            // Enviar requisição
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const data = await response.json();
                return data;
            })
            .then(data => {
                if (data.success) {
                    mostrarMensagemExclusao('success', data.message);
                    
                    // Recarregar a página após 2 segundos
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    mostrarMensagemExclusao('error', data.message);
                    btnExcluir.disabled = false;
                    btnExcluir.innerHTML = '<i class="fas fa-trash"></i> Confirmar Exclusão';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                mostrarMensagemExclusao('error', '❌ Erro ao excluir cliente. Tente novamente.');
                btnExcluir.disabled = false;
                btnExcluir.innerHTML = '<i class="fas fa-trash"></i> Confirmar Exclusão';
            });