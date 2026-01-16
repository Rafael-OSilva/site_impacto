<?php
require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    verificarLogin();
    $connection = $db->getConnection();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método não permitido");
    }

    $clienteId = $_POST['cliente_id'] ?? 0;
    $novoValor = $_POST['novo_credito'] ?? 0;
    $observacao = $_POST['observacao'] ?? '';

    if (!$clienteId || !is_numeric($novoValor)) {
        throw new Exception("Dados inválidos");
    }

    if (atualizarCreditoCliente($connection, $clienteId, $novoValor, $observacao)) {
        echo json_encode([
            'success' => true,
            'message' => 'Crédito atualizado com sucesso!'
        ]);
    } else {
        throw new Exception("Erro ao atualizar crédito");
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
