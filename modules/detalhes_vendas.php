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

// Obter ID da venda
$venda_id = $_GET['id'] ?? 0;

if (!$venda_id || !is_numeric($venda_id)) {
    header('Location: ../index.php?error=id_invalido');
    exit;
}

// Verificar se a venda existe
$sql_check = "SELECT COUNT(*) as total FROM vendas WHERE id = ?";
$stmt_check = $connection->prepare($sql_check);
$stmt_check->execute([$venda_id]);
$result_check = $stmt_check->fetch(PDO::FETCH_ASSOC);

if (!$result_check || $result_check['total'] == 0) {
    header('Location: ../index.php?error=venda_nao_encontrada');
    exit;
}

// Obter detalhes da venda
$sql = "SELECT v.*, 
               c.status as caixa_status,
               u.nome as usuario_nome,
               fp.nome as forma_pagamento_nome
        FROM vendas v
        JOIN caixa c ON v.caixa_id = c.id
        JOIN usuarios u ON v.usuario_id = u.id
        JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
        WHERE v.id = ?";
        
$stmt = $connection->prepare($sql);
$stmt->execute([$venda_id]);
$venda = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venda) {
    header('Location: ../index.php?error=erro_busca_venda');
    exit;
}

// Verificar se pode excluir
$caixa_aberto = obterCaixaAberto($connection);
$pode_excluir = ($caixa_aberto && $caixa_aberto['id'] == $venda['caixa_id']);

$is_module_page = true;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Venda #<?= htmlspecialchars($venda_id) ?> - Caixa Impacto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css"> <!-- ALTERADO: sistema.css -> style.css -->
    <style>
        /* Estilos específicos para esta página */
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--secondary);
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            background: var(--light);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--secondary);
        }
        
        .info-item h4 {
            margin: 0 0 10px 0;
            color: var(--dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-item p {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--secondary);
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: rgba(46, 204, 113, 0.2);
            color: var(--success);
        }
        
        .badge-warning {
            background-color: rgba(243, 156, 18, 0.2);
            color: var(--warning);
        }
        
        .badge-danger {
            background-color: rgba(231, 76, 60, 0.2);
            color: var(--danger);
        }
        
        .badge-info {
            background-color: rgba(52, 152, 219, 0.2);
            color: var(--secondary);
        }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 8px 16px;
            background-color: var(--secondary);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-action:hover {
            background-color: #2980b9;
            text-decoration: none;
            color: white;
        }
        
        .btn-danger {
            background-color: var(--danger);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .observacoes-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid var(--warning);
        }
        
        .observacoes-container h4 {
            margin: 0 0 10px 0;
            color: var(--dark);
            font-size: 1rem;
        }
        
        .observacoes-container p {
            margin: 0;
            color: #666;
            line-height: 1.5;
        }
        
        .valor-destaque {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--success);
        }
        
        /* Modal de confirmação */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            width: 90%;
            max-width: 500px;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        /* Status do caixa */
        .caixa-status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .caixa-aberto {
            background-color: rgba(46, 204, 113, 0.2);
            color: var(--success);
        }
        
        .caixa-fechado {
            background-color: rgba(231, 76, 60, 0.2);
            color: var(--danger);
        }
        
        /* Layout específico */
        .main-content {
            padding: 20px;
            background: #f5f7fa;
            min-height: calc(100vh - 100px);
        }
        
        .page-header {
            margin-bottom: 20px;
            color: var(--dark);
            border-bottom: 2px solid var(--secondary);
            padding-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 15px 20px;
            background-color: var(--light);
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Alertas */
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
            color: var(--success);
            border: 1px solid rgba(46, 204, 113, 0.2);
        }
        
        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }
        
        .alert-info {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(52, 152, 219, 0.2);
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <?php include '../includes/header.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <h1>
                    <i class="fas fa-receipt"></i> Detalhes da Venda #<?= htmlspecialchars($venda_id) ?>
                </h1>
                <a href="<?= $is_module_page ? '../index.php' : 'index.php' ?>" class="back-link">
                    <i class="fas fa-arrow-left"></i> Voltar para Dashboard
                </a>
            </div>
            
            <?php if (!$venda): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Erro ao carregar os detalhes da venda.
                </div>
            <?php else: ?>
            
            <div class="card">
                <div class="card-header">
                    <span>Informações da Venda</span>
                    <span class="caixa-status-badge <?= $venda['caixa_status'] == 'aberto' ? 'caixa-aberto' : 'caixa-fechado' ?>">
                        <i class="fas fa-cash-register"></i> 
                        Caixa <?= $venda['caixa_status'] == 'aberto' ? 'Aberto' : 'Fechado' ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <h4><i class="far fa-calendar"></i> Data e Hora</h4>
                            <p>
                                <?php 
                                if (!empty($venda['data_venda'])) {
                                    echo date('d/m/Y H:i', strtotime($venda['data_venda']));
                                } else {
                                    echo 'Não informado';
                                }
                                ?>
                            </p>
                        </div>
                        
                        <div class="info-item">
                            <h4><i class="fas fa-dollar-sign"></i> Valor Total</h4>
                            <p class="valor-destaque">
                                R$ 
                                <?php 
                                if (!empty($venda['valor_total'])) {
                                    echo number_format($venda['valor_total'], 2, ',', '.');
                                } else {
                                    echo '0,00';
                                }
                                ?>
                            </p>
                        </div>
                        
                        <div class="info-item">
                            <h4><i class="fas fa-credit-card"></i> Forma de Pagamento</h4>
                            <p><?= !empty($venda['forma_pagamento_nome']) ? htmlspecialchars($venda['forma_pagamento_nome']) : 'Não informada' ?></p>
                        </div>
                        
                        <div class="info-item">
                            <h4><i class="fas fa-check-circle"></i> Status</h4>
                            <p>
                                <?php 
                                $status = $venda['status'] ?? '';
                                if ($status === 'concluida'): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check"></i> Concluída
                                    </span>
                                <?php elseif (empty($status)): ?>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-clock"></i> A Receber
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-danger">
                                        <i class="fas fa-times"></i> <?= htmlspecialchars(ucfirst($status)) ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="info-item">
                            <h4><i class="fas fa-user"></i> Cliente</h4>
                            <p>
                                <?php 
                                if (!empty($venda['descricao']) && strpos($venda['descricao'], 'Cliente:') !== false): 
                                    $parts = explode('|', $venda['descricao']);
                                    $cliente_nome = trim(str_replace('Cliente:', '', $parts[0]));
                                    echo htmlspecialchars($cliente_nome);
                                else:
                                    echo '<span style="color: var(--gray);">Consumidor</span>';
                                endif; 
                                ?>
                            </p>
                        </div>
                        
                        <div class="info-item">
                            <h4><i class="fas fa-user-tie"></i> Operador</h4>
                            <p><?= !empty($venda['usuario_nome']) ? htmlspecialchars($venda['usuario_nome']) : 'Não informado' ?></p>
                        </div>
                        
                        <div class="info-item">
                            <h4><i class="fas fa-cash-register"></i> Caixa</h4>
                            <p>
                                #<?= !empty($venda['caixa_id']) ? htmlspecialchars($venda['caixa_id']) : '0' ?>
                            </p>
                        </div>
                        
                        <?php if (!empty($venda['data_recebimento'])): ?>
                        <div class="info-item">
                            <h4><i class="fas fa-calendar-check"></i> Recebido em</h4>
                            <p><?= date('d/m/Y H:i', strtotime($venda['data_recebimento'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($venda['descricao']) && trim($venda['descricao']) != ''): ?>
                    <div class="observacoes-container">
                        <h4><i class="fas fa-sticky-note"></i> Observações</h4>
                        <p><?= nl2br(htmlspecialchars($venda['descricao'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="actions">
                        <?php if ($pode_excluir): ?>
                        <button onclick="confirmarExclusao(<?= htmlspecialchars($venda_id) ?>)" 
                                class="btn-action btn-danger">
                            <i class="fas fa-trash"></i> Excluir Venda
                        </button>
                        <?php else: ?>
                        <span class="badge badge-warning">
                            <i class="fas fa-lock"></i> Não é possível excluir venda de caixa fechado
                        </span>
                        <?php endif; ?>
                        
                        <button onclick="window.print()" class="btn-action">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                        
                        <a href="<?= $is_module_page ? 'historico_vendas.php' : '../modules/historico_vendas.php' ?>" 
                           class="btn-action">
                            <i class="fas fa-history"></i> Histórico Completo
                        </a>
                        
                        <a href="javascript:history.back()" class="btn-action">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </main>

        <?php include '../includes/footer.php'; ?>
    </div>

    <!-- Modal de Confirmação -->
    <div class="modal" id="modalConfirmacao">
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Confirmar Exclusão</h3>
            <p>Tem certeza que deseja excluir a venda #<strong id="venda_numero"><?= htmlspecialchars($venda_id) ?></strong>?</p>
            <p><strong>Atenção:</strong> Esta ação não pode ser desfeita e removerá permanentemente o registro desta venda.</p>
            
            <div class="venda-info" style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span>Valor:</span>
                    <strong style="color: var(--danger);">
                        R$ <?= !empty($venda['valor_total']) ? number_format($venda['valor_total'], 2, ',', '.') : '0,00' ?>
                    </strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Data:</span>
                    <span><?= !empty($venda['data_venda']) ? date('d/m/Y H:i', strtotime($venda['data_venda'])) : 'Não informada' ?></span>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-action" onclick="fecharModal()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button onclick="excluirVenda()" class="btn-action btn-danger">
                    <i class="fas fa-trash"></i> Sim, Excluir
                </button>
            </div>
            <input type="hidden" id="venda_excluir_id" value="<?= htmlspecialchars($venda_id) ?>">
        </div>
    </div>

    <script>
        function confirmarExclusao(vendaId) {
            document.getElementById('venda_excluir_id').value = vendaId;
            document.getElementById('modalConfirmacao').classList.add('active');
        }
        
        function fecharModal() {
            document.getElementById('modalConfirmacao').classList.remove('active');
        }
        
        function excluirVenda() {
            const vendaId = document.getElementById('venda_excluir_id').value;
            
            fetch('../api/excluir_venda.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + vendaId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    window.location.href = '<?= $is_module_page ? "../index.php" : "index.php" ?>';
                } else {
                    alert('❌ Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('❌ Erro ao excluir venda. Tente novamente.');
            });
        }
        
        // Fechar modal ao clicar fora ou pressionar ESC
        window.addEventListener('click', function(event) {
            if (event.target == document.getElementById('modalConfirmacao')) {
                fecharModal();
            }
        });
        
        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                fecharModal();
            }
        });
    </script>
</body>
</html>