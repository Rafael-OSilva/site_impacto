<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!-- DEBUG: Iniciando historico_vendas.php -->";
echo "<!-- PHP_SELF: " . $_SERVER['PHP_SELF'] . " -->";
echo "<!-- SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . " -->";
echo "<!-- REQUEST_URI: " . $_SERVER['REQUEST_URI'] . " -->";

require_once '../includes/functions.php';
verificarLogin();

$titulo = "Histórico de Vendas";
$connection = $db->getConnection();

// Filtros
$filtros = [
    'data_inicio' => $_GET['data_inicio'] ?? date('Y-m-01'),
    'data_fim' => $_GET['data_fim'] ?? date('Y-m-d'),
    'cliente_id' => $_GET['cliente_id'] ?? '',
    'forma_pagamento_id' => $_GET['forma_pagamento_id'] ?? '',
    'status' => $_GET['status'] ?? 'concluida'
];
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?> - Sistema Caixa</title>
    <link rel="stylesheet" href="../css/sistema.css">
    <style>
        .filtros-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .venda-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #28a745;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .venda-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            padding: 10px 0;
        }

        .venda-detalhes {
            display: none;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-top: 10px;
            border: 1px solid #e0e0e0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            padding: 10px;
            background: white;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background-color: #28a745;
            color: white;
        }

        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
        }

        .badge-info {
            background-color: #17a2b8;
            color: white;
        }

        .valor-total {
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            justify-content: flex-end;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .estatisticas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .estatistica-card {
            background: white;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
    </style>
</head>

<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1><?php echo $titulo; ?></h1>
        </div>

        <!-- Filtros -->
        <div class="filtros-container">
            <h3>🔍 Filtros</h3>
            <form method="GET" action="">
                <div class="filtros-grid">
                    <div class="form-group">
                        <label for="data_inicio">Data Início</label>
                        <input type="date" id="data_inicio" name="data_inicio"
                            class="form-control"
                            value="<?php echo $filtros['data_inicio']; ?>">
                    </div>

                    <div class="form-group">
                        <label for="data_fim">Data Fim</label>
                        <input type="date" id="data_fim" name="data_fim"
                            class="form-control"
                            value="<?php echo $filtros['data_fim']; ?>">
                    </div>

                    <div class="form-group">
                        <label for="cliente_id">Cliente</label>
                        <select id="cliente_id" name="cliente_id" class="form-control">
                            <option value="">Todos os Clientes</option>
                            <?php
                            $clientes = listarTodosClientes($connection);
                            foreach ($clientes as $cliente):
                                $selected = ($filtros['cliente_id'] == $cliente['id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $cliente['id']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($cliente['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="forma_pagamento_id">Forma de Pagamento</label>
                        <select id="forma_pagamento_id" name="forma_pagamento_id" class="form-control">
                            <option value="">Todas</option>
                            <?php
                            $formas = obterFormasPagamento($connection);
                            foreach ($formas as $forma):
                                $selected = ($filtros['forma_pagamento_id'] == $forma['id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $forma['id']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($forma['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="concluida" <?php echo $filtros['status'] == 'concluida' ? 'selected' : ''; ?>>Concluídas</option>
                            <option value="pendente" <?php echo $filtros['status'] == 'pendente' ? 'selected' : ''; ?>>Pendentes</option>
                            <option value="todas" <?php echo $filtros['status'] == 'todas' ? 'selected' : ''; ?>>Todas</option>
                        </select>
                    </div>
                </div>

                <div class="form-group text-right">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Aplicar Filtros
                    </button>
                    <a href="historico_vendas.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <?php
        try {
            // Obter estatísticas
            $estatisticas = obterEstatisticasVendas($connection, $filtros['data_inicio'], $filtros['data_fim']);

            if ($estatisticas && $estatisticas['total_vendas'] > 0):
        ?>
                <div class="estatisticas">
                    <div class="estatistica-card">
                        <div class="estatistica-valor"><?php echo $estatisticas['total_vendas']; ?></div>
                        <div class="estatistica-label">Total Vendas</div>
                    </div>

                    <div class="estatistica-card">
                        <div class="estatistica-valor">R$ <?php echo number_format($estatisticas['valor_total'], 2, ',', '.'); ?></div>
                        <div class="estatistica-label">Valor Total</div>
                    </div>

                    <div class="estatistica-card">
                        <div class="estatistica-valor">R$ <?php echo number_format($estatisticas['media_valor'], 2, ',', '.'); ?></div>
                        <div class="estatistica-label">Média</div>
                    </div>

                    <div class="estatistica-card">
                        <div class="estatistica-valor"><?php echo $estatisticas['clientes_atendidos']; ?></div>
                        <div class="estatistica-label">Clientes</div>
                    </div>
                </div>
            <?php endif; ?>

            <?php
            $vendas = listarVendasComFiltros($connection, $filtros);

            if (count($vendas) > 0):
                foreach ($vendas as $venda):
                    $dataVenda = date('d/m/Y H:i', strtotime($venda['data_venda']));
                    $valorTotal = number_format($venda['valor_total'], 2, ',', '.');
            ?>
                    <div class="venda-item">
                        <div class="venda-header" onclick="toggleDetalhes(<?php echo $venda['id']; ?>)">
                            <div>
                                <strong>Venda #<?php echo $venda['id']; ?></strong>
                                <div style="color: #666; font-size: 14px; margin-top: 5px;">
                                    <?php echo $dataVenda; ?>
                                    <?php if ($venda['cliente_nome']): ?>
                                        • <?php echo htmlspecialchars($venda['cliente_nome']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div class="valor-total">R$ <?php echo $valorTotal; ?></div>
                                <div style="margin-top: 5px;">
                                    <span class="badge badge-info"><?php echo $venda['forma_pagamento_nome']; ?></span>
                                    <?php if ($venda['status'] === 'concluida'): ?>
                                        <span class="badge badge-success">Concluída</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pendente</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div id="detalhes-<?php echo $venda['id']; ?>" class="venda-detalhes">
                            <div class="info-grid">
                                <div class="info-item">
                                    <strong>Cliente:</strong><br>
                                    <?php echo $venda['cliente_nome'] ? htmlspecialchars($venda['cliente_nome']) : 'Não informado'; ?>
                                </div>

                                <div class="info-item">
                                    <strong>Data/Hora:</strong><br>
                                    <?php echo $dataVenda; ?>
                                </div>

                                <div class="info-item">
                                    <strong>Valor Total:</strong><br>
                                    R$ <?php echo $valorTotal; ?>
                                </div>

                                <div class="info-item">
                                    <strong>Forma de Pagamento:</strong><br>
                                    <?php echo $venda['forma_pagamento_nome']; ?>
                                </div>

                                <div class="info-item">
                                    <strong>Operador:</strong><br>
                                    <?php echo $venda['usuario_nome']; ?>
                                </div>

                                <div class="info-item">
                                    <strong>Status:</strong><br>
                                    <?php if ($venda['status'] === 'concluida'): ?>
                                        <span class="badge badge-success">Concluída</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pendente</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($venda['descricao']): ?>
                                <div>
                                    <strong>Observações:</strong>
                                    <p style="margin-top: 5px; color: #666;"><?php echo nl2br(htmlspecialchars($venda['descricao'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="actions">
                                <?php
                                // Verificar se pode excluir
                                $caixa_aberto = obterCaixaAberto($connection);
                                $pode_excluir = ($caixa_aberto && $caixa_aberto['id'] == $venda['caixa_id']);

                                if ($pode_excluir):
                                ?>
                                    <button onclick="confirmarExclusao(<?php echo $venda['id']; ?>)"
                                        class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> Excluir Venda
                                    </button>
                                <?php else: ?>
                                    <span class="badge badge-warning">
                                        Não é possível excluir venda de caixa fechado
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php
                endforeach;
            else:
                ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Nenhuma venda encontrada com os filtros selecionados.
                </div>
        <?php
            endif;
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
        }
        ?>
    </main>

    <!-- Modal de Confirmação -->
    <div id="modalConfirmacao" class="modal">
        <div class="modal-content">
            <h3>Confirmar Exclusão</h3>
            <p>Tem certeza que deseja excluir esta venda?</p>
            <p><strong>Atenção:</strong> Esta ação não pode ser desfeita!</p>
            <div style="margin-top: 20px; text-align: right;">
                <button onclick="excluirVenda()" class="btn btn-danger">Sim, Excluir</button>
                <button onclick="fecharModal()" class="btn btn-secondary">Cancelar</button>
            </div>
            <input type="hidden" id="venda_excluir_id">
        </div>
    </div>

    <script>
        function toggleDetalhes(vendaId) {
            const detalhes = document.getElementById('detalhes-' + vendaId);
            if (detalhes.style.display === 'block') {
                detalhes.style.display = 'none';
            } else {
                detalhes.style.display = 'block';
            }
        }

        function confirmarExclusao(vendaId) {
            document.getElementById('venda_excluir_id').value = vendaId;
            document.getElementById('modalConfirmacao').style.display = 'block';
        }

        function fecharModal() {
            document.getElementById('modalConfirmacao').style.display = 'none';
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
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Erro: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao excluir venda');
                });
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            if (event.target == document.getElementById('modalConfirmacao')) {
                fecharModal();
            }
        };
    </script>
</body>

</html>