<?php
// includes/functions/functions_gerais.php

/**
 * Funções gerais do sistema
 */

// Função para verificar login
function verificarLogin()
{
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

// Função para verificar status do caixa
function verificarStatusCaixa($connection)
{
    try {
        $query = "SELECT status FROM caixa 
                  ORDER BY id DESC LIMIT 1";
        $stmt = $connection->prepare($query);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['status'] == 'aberto';
    } catch (PDOException $e) {
        return false;
    }
}

// Função para obter formas de pagamento
function obterFormasPagamento($connection)
{
    try {
        $query = "SELECT * FROM formas_pagamento WHERE ativo = 1 ORDER BY nome";
        $stmt = $connection->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter formas de pagamento: " . $e->getMessage());
        return [];
    }
}

// Função para obter o próximo número de venda
function obterProximoNumeroVenda($connection)
{
    try {
        $query = "SELECT MAX(id) as ultimo_id FROM vendas";
        $stmt = $connection->prepare($query);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['ultimo_id'] ?? 0) + 1;
    } catch (PDOException $e) {
        error_log("Erro ao obter próximo número de venda: " . $e->getMessage());
        return 1;
    }
}

// Função para buscar venda por ID
function buscarVendaPorId($connection, $venda_id)
{
    try {
        $query = "SELECT * FROM vendas WHERE id = ?";
        $stmt = $connection->prepare($query);
        $stmt->execute([$venda_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar venda por ID: " . $e->getMessage());
        return false;
    }
}

// Função para verificar se uma venda existe
function verificarVendaExiste($connection, $venda_id)
{
    try {
        $sql = "SELECT COUNT(*) as total FROM vendas WHERE id = ?";
        $stmt = $connection->prepare($sql);
        $stmt->execute([$venda_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($resultado && $resultado['total'] > 0);
        
    } catch (PDOException $e) {
        error_log("Erro ao verificar venda #$venda_id: " . $e->getMessage());
        return false;
    }
}

// Função para obter estatísticas do sistema
function obterEstatisticasSistema($connection)
{
    try {
        $estatisticas = [];

        // Total de vendas
        $query_vendas = "SELECT COUNT(*) as total FROM vendas WHERE status = 'concluida'";
        $stmt_vendas = $connection->prepare($query_vendas);
        $stmt_vendas->execute();
        $estatisticas['total_vendas'] = $stmt_vendas->fetchColumn();

        // Valor total vendido
        $query_valor = "SELECT COALESCE(SUM(valor_total), 0) as total FROM vendas WHERE status = 'concluida'";
        $stmt_valor = $connection->prepare($query_valor);
        $stmt_valor->execute();
        $estatisticas['valor_total_vendido'] = $stmt_valor->fetchColumn();

        // Contas a receber pendentes
        $query_contas = "SELECT COUNT(*) as total, COALESCE(SUM(valor_total), 0) as valor 
                         FROM vendas 
                         WHERE (forma_pagamento_id = 5 OR forma_pagamento_id = 7) 
                         AND (status = '' OR status IS NULL)";
        $stmt_contas = $connection->prepare($query_contas);
        $stmt_contas->execute();
        $result_contas = $stmt_contas->fetch(PDO::FETCH_ASSOC);
        $estatisticas['contas_pendentes'] = $result_contas['total'] ?? 0;
        $estatisticas['valor_contas_pendentes'] = $result_contas['valor'] ?? 0;

        // Total de retiradas
        $query_retiradas = "SELECT COUNT(*) as total, COALESCE(SUM(valor), 0) as valor FROM retiradas";
        $stmt_retiradas = $connection->prepare($query_retiradas);
        $stmt_retiradas->execute();
        $result_retiradas = $stmt_retiradas->fetch(PDO::FETCH_ASSOC);
        $estatisticas['total_retiradas'] = $result_retiradas['total'] ?? 0;
        $estatisticas['valor_total_retiradas'] = $result_retiradas['valor'] ?? 0;

        return $estatisticas;
    } catch (PDOException $e) {
        error_log("Erro ao obter estatísticas do sistema: " . $e->getMessage());
        return [];
    }
}

/**
 * Formatar CPF para exibição
 * @param string $cpf CPF sem formatação
 * @return string CPF formatado
 */
function formatarCPF($cpf)
{
    if (empty($cpf) || $cpf === '0' || $cpf === 0) {
        return 'Não informado';
    }
    
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . 
               substr($cpf, 3, 3) . '.' . 
               substr($cpf, 6, 3) . '-' . 
               substr($cpf, 9, 2);
    }
    
    return $cpf;
}

/**
 * Formatar valor monetário
 * @param float $valor Valor a ser formatado
 * @return string Valor formatado
 */
function formatarValor($valor)
{
    return 'R$ ' . number_format(floatval($valor), 2, ',', '.');
}

/**
 * Verificar se usuário é administrador
 * @return bool True se for admin
 */
function isAdmin()
{
    return isset($_SESSION['nivel_acesso']) && $_SESSION['nivel_acesso'] === 'admin';
}