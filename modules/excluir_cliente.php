<?php
// modules/excluir_cliente.php

require_once '../config/database.php';
require_once '../includes/functions/functions_clientes.php';

// Verificar autenticação e permissões
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}

// Verificar se usuário é admin
if ($_SESSION['nivel_acesso'] !== 'admin') {
    $_SESSION['mensagem'] = [
        'tipo' => 'error',
        'texto' => 'Apenas administradores podem excluir clientes.'
    ];
    header('Location: clientes.php');
    exit;
}

// Obter ID do cliente
$cliente_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($cliente_id <= 0) {
    $_SESSION['mensagem'] = [
        'tipo' => 'error',
        'texto' => 'ID do cliente inválido.'
    ];
    header('Location: clientes.php');
    exit;
}

// Obter conexão
$connection = $db->getConnection();

// Verificar cliente
$cliente = buscarClientePorId($connection, $cliente_id);
if (!$cliente || $cliente['ativo'] == 0) {
    $_SESSION['mensagem'] = [
        'tipo' => 'error',
        'texto' => 'Cliente não encontrado ou já está inativo.'
    ];
    header('Location: clientes.php');
    exit;
}

// Verificar possibilidade de exclusão
$verificacao = verificarPossibilidadeExclusao($connection, $cliente_id);
$pode_excluir = $verificacao['pode_excluir'];

// Processar exclusão se formulário enviado
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_exclusao'])) {
    $motivo = trim($_POST['motivo']);
    $usuario_id = $_SESSION['usuario_id'];
    
    if (empty($motivo)) {
        $mensagem = "Por favor, informe o motivo da exclusão.";
        $tipo_mensagem = 'error';
    } else {
        $resultado = excluirClienteCompleto($connection, $cliente_id, $usuario_id, $motivo);
        
        if ($resultado['success']) {
            $_SESSION['mensagem'] = [
                'tipo' => 'success',
                'texto' => $resultado['message']
            ];
            header('Location: clientes.php');
            exit;
        } else {
            $mensagem = $resultado['message'];
            $tipo_mensagem = 'error';
        }
    }
}

// Incluir cabeçalho
$titulo_pagina = "Excluir Cliente";
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h1><i class="fas fa-user-minus"></i> Excluir Cliente</h1>
            <hr>
            
            <?php if ($mensagem): ?>
                <div class="alert alert-<?php echo $tipo_mensagem == 'error' ? 'danger' : 'success'; ?>">
                    <?php echo htmlspecialchars($mensagem); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Confirmação de Exclusão</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-warning"></i> Atenção!</h5>
                        <p class="mb-0">Esta ação marcará o cliente como inativo e não poderá ser revertida facilmente. 
                        O histórico de vendas será mantido, mas o cliente será removido das listagens ativas.</p>
                    </div>
                    
                    <div class="mb-4">
                        <h5>Informações do Cliente</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">ID:</th>
                                <td><?php echo $cliente['id']; ?></td>
                            </tr>
                            <tr>
                                <th>Nome:</th>
                                <td><?php echo htmlspecialchars($cliente['nome']); ?></td>
                            </tr>
                            <tr>
                                <th>CPF:</th>
                                <td><?php echo formatarCPF($cliente['cpf']); ?></td>
                            </tr>
                            <tr>
                                <th>Saldo de Crédito:</th>
                                <td class="<?php echo $cliente['valor_credito'] > 0 ? 'text-danger font-weight-bold' : 'text-success'; ?>">
                                    R$ <?php echo number_format($cliente['valor_credito'], 2, ',', '.'); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Data de Cadastro:</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($cliente['data_cadastro'])); ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <?php if (!$pode_excluir): ?>
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-ban"></i> Não é possível excluir este cliente</h5>
                            <ul class="mb-0">
                                <?php foreach ($verificacao['motivos'] as $motivo): ?>
                                    <li><?php echo htmlspecialchars($motivo); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <?php if (isset($verificacao['detalhes']['contas_pendentes'])): ?>
                                <div class="mt-3">
                                    <strong>Contas pendentes:</strong> 
                                    <?php echo $verificacao['detalhes']['contas_pendentes']['total']; ?> 
                                    conta(s) totalizando R$ 
                                    <?php echo number_format($verificacao['detalhes']['contas_pendentes']['total_valor'], 2, ',', '.'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="clientes.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Voltar
                            </a>
                            
                            <?php if ($cliente['valor_credito'] > 0): ?>
                                <a href="credito_cliente.php?id=<?php echo $cliente_id; ?>&action=ajustar" 
                                   class="btn btn-warning">
                                    <i class="fas fa-coins"></i> Ajustar Crédito
                                </a>
                            <?php endif; ?>
                        </div>
                        
                    <?php else: ?>
                        <?php if (isset($verificacao['info'])): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <?php echo htmlspecialchars($verificacao['info']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="motivo"><strong>Motivo da Exclusão *</strong></label>
                                <textarea name="motivo" id="motivo" class="form-control" 
                                          rows="4" required 
                                          placeholder="Descreva o motivo da exclusão deste cliente (obrigatório)..."><?php echo isset($_POST['motivo']) ? htmlspecialchars($_POST['motivo']) : ''; ?></textarea>
                                <small class="form-text text-muted">
                                    Esta informação será registrada no histórico para auditoria.
                                </small>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input type="checkbox" class="form-check-input" id="confirmacao" required>
                                <label class="form-check-label text-danger" for="confirmacao">
                                    <strong>Confirmo que compreendo que esta ação marcará o cliente como inativo e não pode ser desfeita facilmente.</strong>
                                </label>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="clientes.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                                
                                <button type="submit" name="confirmar_exclusao" class="btn btn-danger" id="btn-excluir" disabled>
                                    <i class="fas fa-trash"></i> Confirmar Exclusão
                                </button>
                            </div>
                        </form>
                        
                        <script>
                        // Habilitar botão apenas quando checkbox marcado
                        document.getElementById('confirmacao').addEventListener('change', function() {
                            document.getElementById('btn-excluir').disabled = !this.checked;
                        });
                        
                        // Confirmação adicional
                        document.querySelector('form').addEventListener('submit', function(e) {
                            if (!confirm('Tem certeza absoluta que deseja excluir este cliente?\n\nEsta ação NÃO PODE ser desfeita!')) {
                                e.preventDefault();
                            }
                        });
                        </script>
                        
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>