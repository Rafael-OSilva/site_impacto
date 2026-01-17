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
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions.php';

// Conectar ao banco de dados
try {
    $db = new Database();
    $connection = $db->getConnection();
} catch (Exception $e) {
    die("❌ Erro de conexão com o banco: " . $e->getMessage());
}

// INICIALIZAR VARIÁVEIS PARA EVITAR ERROS
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
            } else {
                throw new Exception("Erro ao cadastrar cliente no banco de dados.");
            }
        } catch (Exception $e) {
            $erro = "❌ " . $e->getMessage();
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
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

        .btn-editar {
            background: #f39c12;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-editar:hover {
            background: #e67e22;
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

        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        /* Estilos para a busca */
        .search-container {
            margin: 15px 0;
        }

        .search-box {
            position: relative;
            max-width: 400px;
        }

        .search-input {
            width: 100%;
            padding: 10px 40px 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
        }

        .no-results {
            text-align: center;
            padding: 30px;
            color: #7f8c8d;
            font-style: italic;
        }

        .search-stats {
            margin-top: 10px;
            font-size: 0.9rem;
            color: #666;
        }

        .highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 0.9rem;
            }
            
            th, td {
                padding: 8px 10px;
            }
            
            .btn-editar {
                padding: 4px 8px;
                font-size: 0.8rem;
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
                <i class="fas fa-credit-card"></i> <?= htmlspecialchars($titulo) ?>
            </h1>

            <!-- Mensagens -->
            <?php if (!empty($mensagem)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensagem) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($erro)): ?>
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
                    <!-- Campo de busca -->
                    <div class="search-container">
                        <div class="search-box">
                            <input type="text" 
                                   id="searchInput" 
                                   class="search-input" 
                                   placeholder="Buscar por nome ou CPF..."
                                   title="Digite nome ou CPF para filtrar">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                        <div class="search-stats">
                            <span id="searchResultsCount"><?= $clientes_com_credito_count ?> cliente(s) encontrado(s)</span>
                        </div>
                    </div>

                    <?php if (count($clientes_com_credito) > 0): ?>
                        <div class="table-responsive">
                            <table id="clientesTable">
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
                                <tbody id="clientesTableBody">
                                    <?php foreach ($clientes_com_credito as $cliente): ?>
                                        <tr class="cliente-row" 
                                            data-id="<?= $cliente['id'] ?>"
                                            data-nome="<?= htmlspecialchars(strtolower($cliente['nome'])) ?>"
                                            data-cpf="<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $cliente['cpf'])) ?>">
                                            <td><?= $cliente['id'] ?></td>
                                            <td class="cliente-nome"><?= htmlspecialchars($cliente['nome']) ?></td>
                                            <td class="cliente-cpf"><?= formatarCPF($cliente['cpf']) ?></td>
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
                                            <td><?= $cliente['cpf'] ? formatarCPF($cliente['cpf']) : '-' ?></td>
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
                    <button type="submit" class="btn btn-primary" id="btnAtualizar">
                        <i class="fas fa-save"></i> Excluir Cliente
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
            
            // Formatar CPF se existir
            let cpfFormatado = clienteCpf;
            if (clienteCpf && clienteCpf.length === 11) {
                cpfFormatado = clienteCpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            }
            
            clienteInfo.innerHTML = `
                <div class="cliente-info-row">
                    <span><strong>Cliente:</strong></span>
                    <span>${clienteNome}</span>
                </div>
                ${clienteCpf ? `
                <div class="cliente-info-row">
                    <span><strong>CPF:</strong></span>
                    <span>${cpfFormatado}</span>
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

        function mostrarMensagem(tipo, mensagem) {
            const mensagemDiv = document.getElementById('modalMensagem');
            mensagemDiv.innerHTML = `
                <div class="alert alert-${tipo === 'success' ? 'success' : 'error'}" style="margin: 10px 0;">
                    <i class="fas fa-${tipo === 'success' ? 'check' : 'exclamation'}-circle"></i> ${mensagem}
                </div>
            `;
        }

        // FUNÇÃO DE BUSCA EM TEMPO REAL
        function iniciarBusca() {
            const searchInput = document.getElementById('searchInput');
            const clientesTableBody = document.getElementById('clientesTableBody');
            const searchResultsCount = document.getElementById('searchResultsCount');
            
            if (!searchInput || !clientesTableBody) return;
            
            // Armazenar todas as linhas originais
            const todasLinhas = Array.from(clientesTableBody.querySelectorAll('.cliente-row'));
            
            searchInput.addEventListener('input', function() {
                const termo = this.value.trim().toLowerCase();
                let encontrados = 0;
                
                if (termo === '') {
                    // Mostrar todos os clientes
                    todasLinhas.forEach(linha => {
                        linha.style.display = '';
                        // Remover highlight
                        const nomeCell = linha.querySelector('.cliente-nome');
                        const cpfCell = linha.querySelector('.cliente-cpf');
                        if (nomeCell) nomeCell.innerHTML = nomeCell.textContent;
                        if (cpfCell) cpfCell.innerHTML = cpfCell.textContent;
                    });
                    encontrados = todasLinhas.length;
                } else {
                    // Filtrar clientes
                    todasLinhas.forEach(linha => {
                        const nome = linha.getAttribute('data-nome') || '';
                        const cpf = linha.getAttribute('data-cpf') || '';
                        
                        const correspondeNome = nome.includes(termo);
                        const correspondeCPF = cpf.includes(termo.replace(/\D/g, ''));
                        
                        if (correspondeNome || correspondeCPF) {
                            linha.style.display = '';
                            encontrados++;
                            
                            // Adicionar highlight ao texto encontrado
                            if (correspondeNome) {
                                const nomeCell = linha.querySelector('.cliente-nome');
                                if (nomeCell) {
                                    const textoOriginal = nomeCell.textContent;
                                    const regex = new RegExp(`(${termo})`, 'gi');
                                    nomeCell.innerHTML = textoOriginal.replace(regex, '<span class="highlight">$1</span>');
                                }
                            }
                            
                            if (correspondeCPF) {
                                const cpfCell = linha.querySelector('.cliente-cpf');
                                if (cpfCell) {
                                    const textoOriginal = cpfCell.textContent;
                                    const termoLimpo = termo.replace(/\D/g, '');
                                    const regex = new RegExp(`(${termoLimpo})`, 'gi');
                                    cpfCell.innerHTML = textoOriginal.replace(regex, '<span class="highlight">$1</span>');
                                }
                            }
                        } else {
                            linha.style.display = 'none';
                            // Remover highlight das células escondidas
                            const nomeCell = linha.querySelector('.cliente-nome');
                            const cpfCell = linha.querySelector('.cliente-cpf');
                            if (nomeCell) nomeCell.innerHTML = nomeCell.textContent;
                            if (cpfCell) cpfCell.innerHTML = cpfCell.textContent;
                        }
                    });
                }
                
                // Atualizar contador
                searchResultsCount.textContent = `${encontrados} cliente(s) encontrado(s)`;
                
                // Mostrar mensagem se nenhum resultado
                let noResultsRow = clientesTableBody.querySelector('.no-results-row');
                if (encontrados === 0 && termo !== '') {
                    if (!noResultsRow) {
                        noResultsRow = document.createElement('tr');
                        noResultsRow.className = 'no-results-row';
                        noResultsRow.innerHTML = `
                            <td colspan="6" class="no-results">
                                <i class="fas fa-search"></i>
                                <p>Nenhum cliente encontrado para "${termo}"</p>
                                <small>Tente buscar por nome ou CPF</small>
                            </td>
                        `;
                        clientesTableBody.appendChild(noResultsRow);
                    }
                } else if (noResultsRow) {
                    noResultsRow.remove();
                }
            });
            
            // Focar no campo de busca ao pressionar Ctrl+F
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            });
            
            // Limpar busca com ESC
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    this.value = '';
                    this.dispatchEvent(new Event('input'));
                }
            });
        }

        // Máscaras para CPF, telefone e valor
        document.addEventListener('DOMContentLoaded', function() {
            // Iniciar sistema de busca
            iniciarBusca();
            
            // Máscara para CPF
            const cpfInput = document.getElementById('cpf');
            if (cpfInput) {
                cpfInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 11) value = value.substring(0, 11);

                    if (value.length > 9) {
                        value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*/, '$1.$2.$3-$4');
                    } else if (value.length > 6) {
                        value = value.replace(/^(\d{3})(\d{3})(\d{1,3}).*/, '$1.$2.$3');
                    } else if (value.length > 3) {
                        value = value.replace(/^(\d{3})(\d{1,3}).*/, '$1.$2');
                    }
                    e.target.value = value;
                });
            }

            // Máscara para telefone
            const telefoneInput = document.getElementById('telefone');
            if (telefoneInput) {
                telefoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 11) value = value.substring(0, 11);

                    if (value.length > 10) {
                        value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
                    } else if (value.length > 6) {
                        value = value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
                    } else if (value.length > 2) {
                        value = value.replace(/^(\d{2})(\d{0,5}).*/, '($1) $2');
                    }
                    e.target.value = value;
                });
            }

            // Máscara para valor
            const valorInput = document.getElementById('valor_credito');
            const novoValorInput = document.getElementById('novo_valor');

            function formatarValor(input) {
                if (input) {
                    input.addEventListener('input', function(e) {
                        let value = e.target.value.replace(/\D/g, '');
                        value = (value / 100).toFixed(2);
                        value = value.replace('.', ',');
                        value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                        e.target.value = value;
                    });
                }
            }

            formatarValor(valorInput);
            formatarValor(novoValorInput);

            // Fechar modal ao clicar fora
            document.getElementById('modalCredito').addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModalCredito();
                }
            });

            // Fechar modal com ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    fecharModalCredito();
                }
            });
        });
    </script>
</body>

</html>