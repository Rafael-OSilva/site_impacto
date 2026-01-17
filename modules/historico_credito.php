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

// Título da página
$titulo = "Histórico de Crédito";

// Inicializar variáveis
$mensagem = '';
$erro = '';
$historico = [];
$cliente_selecionado = null;

// Filtros
$filtros = [
    'cliente_id' => $_GET['cliente_id'] ?? '',
    'cpf' => $_GET['cpf'] ?? '',
    'data_inicio' => $_GET['data_inicio'] ?? date('Y-m-01'),
    'data_fim' => $_GET['data_fim'] ?? date('Y-m-d'),
    'tipo_operacao' => $_GET['tipo_operacao'] ?? ''
];

// Função para buscar histórico de crédito
function buscarHistoricoCredito($connection, $filtros = []) {
    try {
        $sql = "SELECT 
                    hc.*,
                    c.nome as cliente_nome,
                    c.cpf as cliente_cpf,
                    u.nome as usuario_nome
                FROM historico_credito hc
                JOIN clientes c ON hc.cliente_id = c.id
                JOIN usuarios u ON hc.usuario_id = u.id
                WHERE 1=1";
        
        $params = [];
        
        // Filtro por cliente ID
        if (!empty($filtros['cliente_id'])) {
            $sql .= " AND hc.cliente_id = ?";
            $params[] = $filtros['cliente_id'];
        }
        
        // Filtro por CPF
        if (!empty($filtros['cpf'])) {
            $cpf_limpo = preg_replace('/[^0-9]/', '', $filtros['cpf']);
            $sql .= " AND c.cpf LIKE ?";
            $params[] = "%$cpf_limpo%";
        }
        
        // Filtro por data início
        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND DATE(hc.data_alteracao) >= ?";
            $params[] = $filtros['data_inicio'];
        }
        
        // Filtro por data fim
        if (!empty($filtros['data_fim'])) {
            $sql .= " AND DATE(hc.data_alteracao) <= ?";
            $params[] = $filtros['data_fim'];
        }
        
        // Filtro por tipo de operação
        if (!empty($filtros['tipo_operacao'])) {
            $sql .= " AND hc.tipo_operacao = ?";
            $params[] = $filtros['tipo_operacao'];
        }
        
        $sql .= " ORDER BY hc.data_alteracao DESC";
        
        $stmt = $connection->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erro ao buscar histórico de crédito: " . $e->getMessage());
        return [];
    }
}

// Função para criar o histórico de crédito se a tabela não existir
function criarTabelaHistoricoCredito($connection) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS historico_credito (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cliente_id INT NOT NULL,
            usuario_id INT NOT NULL,
            data_alteracao DATETIME NOT NULL,
            valor_anterior DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            valor_novo DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tipo_operacao VARCHAR(20) NOT NULL,
            observacao TEXT,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
            INDEX idx_cliente_id (cliente_id),
            INDEX idx_data_alteracao (data_alteracao),
            INDEX idx_tipo_operacao (tipo_operacao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $connection->exec($sql);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao criar tabela histórico_credito: " . $e->getMessage());
        return false;
    }
}

// Verificar se a tabela existe, se não, criar
criarTabelaHistoricoCredito($connection);

// Buscar histórico com filtros
$historico = buscarHistoricoCredito($connection, $filtros);

// Buscar cliente selecionado
if (!empty($filtros['cliente_id'])) {
    $cliente_selecionado = buscarClientePorId($connection, $filtros['cliente_id']);
}

// Buscar todos os clientes para o select
$todos_clientes = listarTodosClientes($connection);

// Calcular estatísticas
$total_movimentacoes = count($historico);
$valor_total_movimentado = 0;
$credito_adicionado = 0;
$credito_utilizado = 0;

foreach ($historico as $mov) {
    $diferenca = $mov['valor_novo'] - $mov['valor_anterior'];
    $valor_total_movimentado += abs($diferenca);
    
    if ($diferenca > 0) {
        $credito_adicionado += $diferenca;
    } else {
        $credito_utilizado += abs($diferenca);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> - Sistema Caixa</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .filtros-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .historico-item {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }

        .historico-item.adicao {
            border-left-color: #28a745;
        }

        .historico-item.uso {
            border-left-color: #dc3545;
        }

        .historico-item.ajuste {
            border-left-color: #ffc107;
        }

        .historico-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-adicao {
            background-color: #28a745;
            color: white;
        }

        .badge-uso {
            background-color: #dc3545;
            color: white;
        }

        .badge-ajuste {
            background-color: #ffc107;
            color: #212529;
        }

        .valor-diferenca {
            font-size: 18px;
            font-weight: bold;
        }

        .valor-positivo {
            color: #28a745;
        }

        .valor-negativo {
            color: #dc3545;
        }

        .cliente-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid #2196f3;
        }

        .estatisticas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .estatistica-card {
            background: white;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .estatistica-valor {
            font-size: 22px;
            font-weight: bold;
            color: #007bff;
            margin: 10px 0;
        }

        .estatistica-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }

        .tipo-operacao {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .tipo-adicao {
            background-color: #d4edda;
            color: #155724;
        }

        .tipo-uso {
            background-color: #f8d7da;
            color: #721c24;
        }

        .tipo-ajuste {
            background-color: #fff3cd;
            color: #856404;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/header.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <main>
            <h1 class="page-title">
                <i class="fas fa-history"></i> <?= htmlspecialchars($titulo) ?>
            </h1>

            <!-- Informação sobre a funcionalidade -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Como funciona:</strong> O histórico mostra todas as alterações no crédito dos clientes, incluindo adições, usos e ajustes manuais.
            </div>

            <!-- Filtros -->
            <div class="filtros-container">
                <h3><i class="fas fa-filter"></i> Filtros</h3>
                <form method="GET" action="">
                    <div class="filtros-grid">
                        <div class="form-group">
                            <label for="cliente_id">Cliente</label>
                            <select id="cliente_id" name="cliente_id" class="form-control">
                                <option value="">Todos os Clientes</option>
                                <?php foreach ($todos_clientes as $cliente): ?>
                                    <option value="<?= $cliente['id'] ?>" 
                                        <?= ($filtros['cliente_id'] == $cliente['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cliente['nome']) ?>
                                        <?= $cliente['cpf'] ? " (CPF: " . $cliente['cpf'] . ")" : "" ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="cpf">Buscar por CPF</label>
                            <input type="text" id="cpf" name="cpf" class="form-control"
                                value="<?= htmlspecialchars($filtros['cpf']) ?>"
                                placeholder="Digite o CPF do cliente">
                        </div>

                        <div class="form-group">
                            <label for="data_inicio">Data Início</label>
                            <input type="date" id="data_inicio" name="data_inicio" class="form-control"
                                value="<?= htmlspecialchars($filtros['data_inicio']) ?>">
                        </div>

                        <div class="form-group">
                            <label for="data_fim">Data Fim</label>
                            <input type="date" id="data_fim" name="data_fim" class="form-control"
                                value="<?= htmlspecialchars($filtros['data_fim']) ?>">
                        </div>

                        <div class="form-group">
                            <label for="tipo_operacao">Tipo de Operação</label>
                            <select id="tipo_operacao" name="tipo_operacao" class="form-control">
                                <option value="">Todos</option>
                                <option value="adicao" <?= ($filtros['tipo_operacao'] == 'adicao') ? 'selected' : '' ?>>Adição de Crédito</option>
                                <option value="uso" <?= ($filtros['tipo_operacao'] == 'uso') ? 'selected' : '' ?>>Uso de Crédito</option>
                                <option value="ajuste" <?= ($filtros['tipo_operacao'] == 'ajuste') ? 'selected' : '' ?>>Ajuste Manual</option>
                                <option value="compra" <?= ($filtros['tipo_operacao'] == 'compra') ? 'selected' : '' ?>>Compra com Crédito</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group text-right">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Aplicar Filtros
                        </button>
                        <a href="historico_credito.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Limpar Filtros
                        </a>
                    </div>
                </form>
            </div>

            <!-- Informações do cliente selecionado -->
            <?php if ($cliente_selecionado): ?>
                <div class="cliente-info">
                    <h4><i class="fas fa-user"></i> Cliente Selecionado</h4>
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>Nome:</strong><br>
                            <?= htmlspecialchars($cliente_selecionado['nome']) ?>
                        </div>
                        <div class="info-item">
                            <strong>CPF:</strong><br>
                            <?= $cliente_selecionado['cpf'] ?: 'Não informado' ?>
                        </div>
                        <div class="info-item">
                            <strong>Telefone:</strong><br>
                            <?= $cliente_selecionado['telefone'] ?: 'Não informado' ?>
                        </div>
                        <div class="info-item">
                            <strong>Crédito Atual:</strong><br>
                            <span style="font-weight: bold; color: #28a745;">
                                R$ <?= number_format($cliente_selecionado['valor_credito'], 2, ',', '.') ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Estatísticas -->
            <?php if ($total_movimentacoes > 0): ?>
                <div class="estatisticas">
                    <div class="estatistica-card">
                        <div class="estatistica-valor"><?= $total_movimentacoes ?></div>
                        <div class="estatistica-label">Movimentações</div>
                    </div>

                    <div class="estatistica-card">
                        <div class="estatistica-valor">
                            R$ <?= number_format($valor_total_movimentado, 2, ',', '.') ?>
                        </div>
                        <div class="estatistica-label">Total Movimentado</div>
                    </div>

                    <div class="estatistica-card">
                        <div class="estatistica-valor">
                            R$ <?= number_format($credito_adicionado, 2, ',', '.') ?>
                        </div>
                        <div class="estatistica-label">Crédito Adicionado</div>
                    </div>

                    <div class="estatistica-card">
                        <div class="estatistica-valor">
                            R$ <?= number_format($credito_utilizado, 2, ',', '.') ?>
                        </div>
                        <div class="estatistica-label">Crédito Utilizado</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Histórico -->
            <?php if (count($historico) > 0): ?>
                <h3><i class="fas fa-list"></i> Histórico de Movimentações</h3>
                
                <?php foreach ($historico as $mov): 
                    // Determinar tipo de operação e classes CSS
                    $diferenca = $mov['valor_novo'] - $mov['valor_anterior'];
                    $classe_item = '';
                    $classe_badge = '';
                    $tipo_texto = '';
                    
                    switch ($mov['tipo_operacao']) {
                        case 'adicao':
                            $classe_item = 'adicao';
                            $classe_badge = 'badge-adicao';
                            $tipo_texto = 'Adição de Crédito';
                            break;
                        case 'uso':
                            $classe_item = 'uso';
                            $classe_badge = 'badge-uso';
                            $tipo_texto = 'Uso de Crédito';
                            break;
                        case 'compra':
                            $classe_item = 'uso';
                            $classe_badge = 'badge-uso';
                            $tipo_texto = 'Compra com Crédito';
                            break;
                        default:
                            $classe_item = 'ajuste';
                            $classe_badge = 'badge-ajuste';
                            $tipo_texto = 'Ajuste Manual';
                    }
                ?>
                    <div class="historico-item <?= $classe_item ?>">
                        <div class="historico-header">
                            <div>
                                <strong><?= $mov['cliente_nome'] ?></strong>
                                <div style="color: #666; font-size: 14px; margin-top: 5px;">
                                    <i class="far fa-clock"></i> 
                                    <?= date('d/m/Y H:i', strtotime($mov['data_alteracao'])) ?>
                                    • CPF: <?= $mov['cliente_cpf'] ?: 'Não informado' ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span class="badge <?= $classe_badge ?>">
                                    <?= $tipo_texto ?>
                                </span>
                                <div class="valor-diferenca <?= $diferenca >= 0 ? 'valor-positivo' : 'valor-negativo' ?>">
                                    <?= $diferenca >= 0 ? '+' : '' ?>R$ <?= number_format($diferenca, 2, ',', '.') ?>
                                </div>
                            </div>
                        </div>

                        <div class="info-grid">
                            <div class="info-item">
                                <strong>Saldo Anterior:</strong><br>
                                R$ <?= number_format($mov['valor_anterior'], 2, ',', '.') ?>
                            </div>

                            <div class="info-item">
                                <strong>Novo Saldo:</strong><br>
                                R$ <?= number_format($mov['valor_novo'], 2, ',', '.') ?>
                            </div>

                            <div class="info-item">
                                <strong>Operador:</strong><br>
                                <?= $mov['usuario_nome'] ?>
                            </div>

                            <div class="info-item">
                                <strong>Data:</strong><br>
                                <?= date('d/m/Y H:i', strtotime($mov['data_alteracao'])) ?>
                            </div>
                        </div>

                        <?php if (!empty($mov['observacao'])): ?>
                            <div style="margin-top: 15px;">
                                <strong><i class="fas fa-sticky-note"></i> Observação:</strong>
                                <p style="margin-top: 5px; color: #666; background: #f8f9fa; padding: 10px; border-radius: 4px;">
                                    <?= nl2br(htmlspecialchars($mov['observacao'])) ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h3>Nenhuma movimentação encontrada</h3>
                    <p>Não foram encontradas movimentações de crédito com os filtros selecionados.</p>
                </div>
            <?php endif; ?>
        </main>

        <?php include '../includes/footer.php'; ?>
    </div>

    <script>
        // Máscara para CPF
        document.getElementById('cpf').addEventListener('input', function(e) {
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

        // Auto-completar cliente ao digitar CPF
        document.getElementById('cpf').addEventListener('blur', function(e) {
            const cpf = this.value.replace(/\D/g, '');
            if (cpf.length >= 11) {
                // Aqui você poderia implementar uma busca AJAX para preencher automaticamente
                console.log('Buscar cliente com CPF:', cpf);
            }
        });

        // Validar datas
        document.querySelector('form').addEventListener('submit', function(e) {
            const dataInicio = document.getElementById('data_inicio').value;
            const dataFim = document.getElementById('data_fim').value;
            
            if (dataInicio && dataFim && dataInicio > dataFim) {
                e.preventDefault();
                alert('A data inicial não pode ser maior que a data final!');
                return false;
            }
        });
    </script>
</body>
</html>