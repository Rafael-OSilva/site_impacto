<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Não autorizado. Faça login novamente.'
    ]);
    exit;
}

// Conectar ao banco de dados
try {
    $db = new Database();
    $connection = $db->getConnection();
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro na conexão com o banco de dados: ' . $e->getMessage()
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use POST.'
    ]);
    exit;
}

$venda_id = $_POST['id'] ?? 0;

if (!$venda_id || !is_numeric($venda_id) || $venda_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID da venda inválido ou não informado.'
    ]);
    exit;
}

try {
    // Usar a função excluirVenda
    excluirVenda($connection, $venda_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Venda excluída com sucesso!'
    ]);
    
} catch(Exception $e) {
    // Capturar erros específicos
    $error_message = $e->getMessage();
    
    // Log do erro completo para debugging
    error_log("Erro API excluir_venda #$venda_id: " . $error_message);
    error_log("Usuário ID: " . ($_SESSION['usuario_id'] ?? 'não definido'));
    error_log("Dados POST: " . print_r($_POST, true));
    
    // Mensagem amigável para o usuário
    echo json_encode([
        'success' => false,
        'message' => $error_message
    ]);
}
?>