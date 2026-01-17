<?php
// includes/functions/functions_retiradas.php

/**
 * Funções para gerenciamento de retiradas
 */

function registrarRetirada($connection, $caixa_id, $usuario_id, $valor, $motivo)
{
    try {
        $query = "INSERT INTO retiradas (caixa_id, usuario_id, data_retirada, valor, motivo) 
                  VALUES (:caixa_id, :usuario_id, NOW(), :valor, :motivo)";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(':caixa_id', $caixa_id);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':valor', $valor);
        $stmt->bindParam(':motivo', $motivo);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erro ao registrar retirada: " . $e->getMessage());
        return false;
    }
}

function excluirRetirada($connection, $retirada_id, $usuario_id)
{
    try {
        $query = "DELETE FROM retiradas WHERE id = :id";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(':id', $retirada_id, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erro ao excluir retirada (ID: $retirada_id) por usuário (ID: $usuario_id): " . $e->getMessage());
        return false;
    }
}

function obterRetiradasDoCaixaAberto($connection, $caixa_id)
{
    try {
        $query = "SELECT r.*, u.nome as nome_usuario 
                  FROM retiradas r
                  JOIN usuarios u ON r.usuario_id = u.id
                  WHERE r.caixa_id = :caixa_id 
                  ORDER BY r.data_retirada DESC";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(':caixa_id', $caixa_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter retiradas: " . $e->getMessage());
        return [];
    }
}

// Função para calcular resumo do caixa
function calcularResumoCaixaAberto($connection, $caixa_id)
{
    $resumo = [
        'valor_inicial' => 0,
        'vendas_dinheiro' => 0,
        'vendas_a_receber' => 0,
        'total_retiradas' => 0,
        'saldo_disponivel' => 0,
        'total_vendas_concluidas' => 0
    ];

    try {
        // 1. Valor inicial
        $caixa = obterCaixaAberto($connection);
        $resumo['valor_inicial'] = $caixa['valor_inicial'] ?? 0;

        // 2. Vendas em Dinheiro (ID 1) - APENAS CONCLUÍDAS
        $query_vendas_dinheiro = "SELECT SUM(valor_total) as total FROM vendas 
                         WHERE caixa_id = :caixa_id AND forma_pagamento_id = 1 AND status = 'concluida'";
        $stmt_vendas_dinheiro = $connection->prepare($query_vendas_dinheiro);
        $stmt_vendas_dinheiro->bindParam(':caixa_id', $caixa_id);
        $stmt_vendas_dinheiro->execute();
        $total_vendas_dinheiro = $stmt_vendas_dinheiro->fetch(PDO::FETCH_ASSOC);
        $resumo['vendas_dinheiro'] = $total_vendas_dinheiro['total'] ?? 0;

        // 3. Vendas "A Receber" 
        $query_vendas_a_receber = "SELECT SUM(valor_total) as total FROM vendas 
                         WHERE caixa_id = :caixa_id AND (forma_pagamento_id = 5 OR forma_pagamento_id = 7) 
                         AND (status = '' OR status IS NULL OR status = 'a_receber')";
        $stmt_vendas_a_receber = $connection->prepare($query_vendas_a_receber);
        $stmt_vendas_a_receber->bindParam(':caixa_id', $caixa_id);
        $stmt_vendas_a_receber->execute();
        $total_vendas_a_receber = $stmt_vendas_a_receber->fetch(PDO::FETCH_ASSOC);
        $resumo['vendas_a_receber'] = $total_vendas_a_receber['total'] ?? 0;

        // 4. Total de Retiradas
        $query_retiradas = "SELECT SUM(valor) as total FROM retiradas WHERE caixa_id = :caixa_id";
        $stmt_retiradas = $connection->prepare($query_retiradas);
        $stmt_retiradas->bindParam(':caixa_id', $caixa_id);
        $stmt_retiradas->execute();
        $total_retiradas = $stmt_retiradas->fetch(PDO::FETCH_ASSOC);
        $resumo['total_retiradas'] = $total_retiradas['total'] ?? 0;

        // 5. Total de vendas concluídas
        $query_total_concluidas = "SELECT SUM(valor_total) as total FROM vendas 
                         WHERE caixa_id = :caixa_id AND status = 'concluida'";
        $stmt_total_concluidas = $connection->prepare($query_total_concluidas);
        $stmt_total_concluidas->bindParam(':caixa_id', $caixa_id);
        $stmt_total_concluidas->execute();
        $total_concluidas = $stmt_total_concluidas->fetch(PDO::FETCH_ASSOC);
        $resumo['total_vendas_concluidas'] = $total_concluidas['total'] ?? 0;

        // 6. Saldo disponível em dinheiro
        $resumo['saldo_disponivel'] = $resumo['valor_inicial'] + $resumo['vendas_dinheiro'] - $resumo['total_retiradas'];

        return $resumo;
    } catch (PDOException $e) {
        error_log("Erro ao calcular resumo do caixa: " . $e->getMessage());
        return $resumo;
    }
}

// Função para registrar entrada no caixa
function registrarEntradaCaixa($connection, $valor, $usuario_id, $descricao)
{
    try {
        $caixa_aberto = obterCaixaAberto($connection);
        if (!$caixa_aberto) {
            return false; // Não há caixa aberto
        }

        $query = "INSERT INTO movimentacoes_caixa (caixa_id, usuario_id, tipo, valor, descricao, data_movimentacao) 
                  VALUES (?, ?, 'entrada', ?, ?, NOW())";
        $stmt = $connection->prepare($query);
        return $stmt->execute([$caixa_aberto['id'], $usuario_id, $valor, $descricao]);
    } catch (PDOException $e) {
        error_log("Erro ao registrar entrada no caixa: " . $e->getMessage());
        return false;
    }
}