<?php
// includes/functions/functions_vendas.php

/**
 * Funções relacionadas a vendas
 */

// Função para calcular vendas do dia (ATUALIZADA)
function calcularVendasHoje($connection, $data)
{
    try {
        $query = "SELECT COUNT(*) as quantidade, SUM(valor_total) as total 
                  FROM vendas 
                  WHERE DATE(data_venda) = :data 
                  AND status = 'concluida'";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(':data', $data);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['quantidade' => 0, 'total' => 0];
    }
}

// Função para obter últimas vendas (ATUALIZADA)
function obterUltimasVendas($connection, $limite = 5)
{
    try {
        $query = "SELECT v.*, fp.nome as forma_pagamento 
                  FROM vendas v
                  LEFT JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                  WHERE (v.status = 'concluida' OR v.status = '' OR v.status IS NULL) 
                  ORDER BY data_venda DESC 
                  LIMIT :limite";
        $stmt = $connection->prepare($query);
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// FUNÇÃO ATUALIZADA: Registrar uma venda
function registrarVenda($connection, $caixa_id, $usuario_id, $valor_total, $forma_pagamento_id, $descricao, $status = 'concluida')
{
    try {
        // Se for "A Receber" (ID 5 ou 7), deixar status vazio
        if ($forma_pagamento_id == 5 || $forma_pagamento_id == 7) {
            $status = ''; // Status vazio para vendas a receber
        }

        $sql = "INSERT INTO vendas (caixa_id, usuario_id, data_venda, valor_total, forma_pagamento_id, descricao, status, visivel_contas_receber) 
                VALUES (?, ?, NOW(), ?, ?, ?, ?, 1)";

        $stmt = $connection->prepare($sql);
        $stmt->execute([$caixa_id, $usuario_id, $valor_total, $forma_pagamento_id, $descricao, $status]);

        return $connection->lastInsertId();
    } catch (PDOException $e) {
        error_log("Erro ao registrar venda: " . $e->getMessage());
        return false;
    }
}

// Função para obter vendas do período
function obterVendasPeriodo($connection, $data_inicial, $data_final)
{
    try {
        $data_final_ajustada = $data_final . ' 23:59:59';

        $sql = "SELECT 
                    v.data_venda,
                    v.valor_total,
                    fp.nome as forma_pagamento,
                    u.nome as usuario_nome,
                    v.descricao
                FROM vendas v
                INNER JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                INNER JOIN usuarios u ON v.usuario_id = u.id
                WHERE v.data_venda BETWEEN :data_inicial AND :data_final_ajustada
                AND v.status = 'concluida'
                ORDER BY v.data_venda DESC";

        $stmt = $connection->prepare($sql);
        $stmt->bindParam(':data_inicial', $data_inicial);
        $stmt->bindParam(':data_final_ajustada', $data_final_ajustada);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter vendas do período: " . $e->getMessage());
        return [];
    }
}

// Obter detalhes completos de uma venda específica
function obterDetalhesVenda($connection, $venda_id) {
    try {
        $sql = "SELECT 
                    v.id,
                    v.caixa_id,
                    v.usuario_id,
                    v.data_venda,
                    v.valor_total,
                    v.forma_pagamento_id,
                    v.descricao,
                    v.status,
                    v.visivel_contas_receber,
                    v.data_recebimento,
                    c.status as caixa_status,
                    c.data_abertura,
                    c.data_fechamento,
                    u.nome as usuario_nome,
                    fp.nome as forma_pagamento_nome
                FROM vendas v
                JOIN caixa c ON v.caixa_id = c.id
                JOIN usuarios u ON v.usuario_id = u.id
                JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                WHERE v.id = ?";
        
        $stmt = $connection->prepare($sql);
        $stmt->execute([$venda_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        error_log("Erro ao buscar detalhes da venda #$venda_id: " . $e->getMessage());
        return false;
    }
}

// Excluir venda (apenas se caixa estiver aberto)
function excluirVenda($connection, $venda_id) {
    try {
        // Verificar se a venda existe
        $sql_check = "SELECT caixa_id FROM vendas WHERE id = ?";
        $stmt_check = $connection->prepare($sql_check);
        $stmt_check->execute([$venda_id]);
        $venda = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$venda) {
            throw new Exception("Venda não encontrada");
        }
        
        // Verificar se o caixa está aberto
        $caixa_aberto = obterCaixaAberto($connection);
        if (!$caixa_aberto) {
            throw new Exception("Não é possível excluir venda: caixa fechado");
        }
        
        // Verificar se a venda pertence ao caixa aberto
        if ($venda['caixa_id'] != $caixa_aberto['id']) {
            throw new Exception("Não é possível excluir venda de outro caixa");
        }
        
        // Iniciar transação
        $connection->beginTransaction();
        
        // Excluir a venda
        $sql_delete = "DELETE FROM vendas WHERE id = ?";
        $stmt_delete = $connection->prepare($sql_delete);
        $stmt_delete->execute([$venda_id]);
        
        // Verificar se foi excluído
        if ($stmt_delete->rowCount() === 0) {
            $connection->rollBack();
            throw new Exception("Erro ao excluir venda");
        }
        
        $connection->commit();
        return true;
        
    } catch(PDOException $e) {
        if (isset($connection) && $connection->inTransaction()) {
            $connection->rollBack();
        }
        error_log("Erro ao excluir venda #$venda_id: " . $e->getMessage());
        throw new Exception("Erro ao excluir venda: " . $e->getMessage());
    } catch(Exception $e) {
        if (isset($connection) && $connection->inTransaction()) {
            $connection->rollBack();
        }
        error_log("Erro ao excluir venda #$venda_id: " . $e->getMessage());
        throw new Exception($e->getMessage());
    }
}

// Listar vendas com filtros avançados
function listarVendasComFiltros($connection, $filtros = []) {
    try {
        $sql = "SELECT v.*, 
                       c.data_abertura,
                       c.data_fechamento,
                       u.nome as usuario_nome,
                       fp.nome as forma_pagamento_nome,
                       cl.nome as cliente_nome
                FROM vendas v
                JOIN caixa c ON v.caixa_id = c.id
                JOIN usuarios u ON v.usuario_id = u.id
                JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                LEFT JOIN clientes cl ON v.cliente_id = cl.id
                WHERE 1=1";
        
        $params = [];
        
        // Filtro por data inicial
        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND DATE(v.data_venda) >= :data_inicio";
            $params[':data_inicio'] = $filtros['data_inicio'];
        }
        
        // Filtro por data final
        if (!empty($filtros['data_fim'])) {
            $sql .= " AND DATE(v.data_venda) <= :data_fim";
            $params[':data_fim'] = $filtros['data_fim'];
        }
        
        // Filtro por cliente
        if (!empty($filtros['cliente_id'])) {
            $sql .= " AND v.cliente_id = :cliente_id";
            $params[':cliente_id'] = $filtros['cliente_id'];
        }
        
        // Filtro por forma de pagamento
        if (!empty($filtros['forma_pagamento_id'])) {
            $sql .= " AND v.forma_pagamento_id = :forma_pagamento_id";
            $params[':forma_pagamento_id'] = $filtros['forma_pagamento_id'];
        }
        
        // Filtro por status
        if (!empty($filtros['status'])) {
            if ($filtros['status'] === 'concluida') {
                $sql .= " AND v.status = 'concluida'";
            } elseif ($filtros['status'] === 'pendente') {
                $sql .= " AND (v.status = '' OR v.status IS NULL)";
            } elseif ($filtros['status'] === 'todas') {
                // Mostrar todas
            }
        } else {
            // Padrão: mostrar apenas concluídas
            $sql .= " AND v.status = 'concluida'";
        }
        
        // Filtro por valor mínimo
        if (!empty($filtros['valor_min'])) {
            $sql .= " AND v.valor_total >= :valor_min";
            $params[':valor_min'] = $filtros['valor_min'];
        }
        
        // Filtro por valor máximo
        if (!empty($filtros['valor_max'])) {
            $sql .= " AND v.valor_total <= :valor_max";
            $params[':valor_max'] = $filtros['valor_max'];
        }
        
        $sql .= " ORDER BY v.data_venda DESC";
        
        // Limite opcional
        if (!empty($filtros['limite'])) {
            $sql .= " LIMIT :limite";
            $params[':limite'] = (int)$filtros['limite'];
        }
        
        $stmt = $connection->prepare($sql);
        
        // Bind dos parâmetros
        foreach ($params as $key => $value) {
            if ($key === ':limite') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        error_log("Erro ao listar vendas com filtros: " . $e->getMessage());
        return [];
    }
}

// Registrar venda com cliente e crédito (se aplicável)
function registrarVendaComCliente($connection, $caixa_id, $usuario_id, $valor_total, $forma_pagamento_id, $descricao, $cliente_id = null, $usar_credito = false) {
    try {
        $status = 'concluida';
        
        // Verificar se é "A Receber"
        if ($forma_pagamento_id == 5 || $forma_pagamento_id == 7) {
            $status = ''; // Status vazio para vendas a receber
        }
        
        // Se usar crédito do cliente
        if ($usar_credito && $cliente_id) {
            $cliente = buscarClientePorId($connection, $cliente_id);
            if ($cliente && $cliente['valor_credito'] >= $valor_total) {
                // Deduzir do crédito
                $novo_credito = $cliente['valor_credito'] - $valor_total;
                atualizarCreditoCliente(
                    $connection, 
                    $cliente_id, 
                    $novo_credito, 
                    "Compra realizada no valor de R$ " . number_format($valor_total, 2, ',', '.'),
                    'compra'
                );
                $forma_pagamento_id = 8; // ID para pagamento com crédito
            } else {
                throw new Exception("Crédito insuficiente ou cliente não encontrado");
            }
        }
        
        // Registrar a venda
        $sql = "INSERT INTO vendas (caixa_id, usuario_id, data_venda, valor_total, forma_pagamento_id, descricao, status, cliente_id) 
                VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)";

        $stmt = $connection->prepare($sql);
        $stmt->execute([$caixa_id, $usuario_id, $valor_total, $forma_pagamento_id, $descricao, $status, $cliente_id]);

        $venda_id = $connection->lastInsertId();
        
        return $venda_id;
        
    } catch(PDOException $e) {
        error_log("Erro ao registrar venda com cliente: " . $e->getMessage());
        return false;
    }
}

// Buscar vendas por cliente
function buscarVendasPorCliente($connection, $cliente_id) {
    try {
        $sql = "SELECT v.*, 
                       fp.nome as forma_pagamento_nome,
                       u.nome as usuario_nome,
                       c.data_abertura
                FROM vendas v
                JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                JOIN usuarios u ON v.usuario_id = u.id
                JOIN caixa ca ON v.caixa_id = ca.id
                WHERE v.cliente_id = :cliente_id
                ORDER BY v.data_venda DESC";
        
        $stmt = $connection->prepare($sql);
        $stmt->execute([':cliente_id' => $cliente_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        error_log("Erro ao buscar vendas por cliente: " . $e->getMessage());
        return [];
    }
}

// Obter estatísticas de vendas por período
function obterEstatisticasVendas($connection, $data_inicio, $data_fim) {
    try {
        $data_fim_ajustada = $data_fim . ' 23:59:59';
        
        $sql = "SELECT 
                    COUNT(*) as total_vendas,
                    SUM(v.valor_total) as valor_total,
                    AVG(v.valor_total) as media_valor,
                    MIN(v.valor_total) as menor_venda,
                    MAX(v.valor_total) as maior_venda,
                    COUNT(DISTINCT v.usuario_id) as usuarios_ativos,
                    COUNT(DISTINCT v.cliente_id) as clientes_atendidos
                FROM vendas v
                WHERE v.data_venda BETWEEN :data_inicio AND :data_fim_ajustada
                AND v.status = 'concluida'";
        
        $stmt = $connection->prepare($sql);
        $stmt->bindParam(':data_inicio', $data_inicio);
        $stmt->bindParam(':data_fim_ajustada', $data_fim_ajustada);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        error_log("Erro ao obter estatísticas de vendas: " . $e->getMessage());
        return [];
    }
}

// Buscar vendas com filtros
function buscarVendasComFiltros($connection, $filtros = [])
{
    try {
        $where_conditions = ["1=1"];
        $params = [];

        // Filtro por data
        if (!empty($filtros['data_inicial']) && !empty($filtros['data_final'])) {
            $where_conditions[] = "v.data_venda BETWEEN ? AND ?";
            $params[] = $filtros['data_inicial'];
            $params[] = $filtros['data_final'] . ' 23:59:59';
        }

        // Filtro por forma de pagamento
        if (!empty($filtros['forma_pagamento_id'])) {
            $where_conditions[] = "v.forma_pagamento_id = ?";
            $params[] = $filtros['forma_pagamento_id'];
        }

        // Filtro por usuário
        if (!empty($filtros['usuario_id'])) {
            $where_conditions[] = "v.usuario_id = ?";
            $params[] = $filtros['usuario_id'];
        }

        // Filtro por status
        if (!empty($filtros['status'])) {
            if ($filtros['status'] == 'concluida') {
                $where_conditions[] = "v.status = 'concluida'";
            } elseif ($filtros['status'] == 'a_receber') {
                $where_conditions[] = "(v.status = '' OR v.status IS NULL)";
            }
        }

        $where_sql = implode(' AND ', $where_conditions);

        $query = "SELECT v.*, fp.nome as forma_pagamento, u.nome as usuario_nome
                  FROM vendas v
                  INNER JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                  INNER JOIN usuarios u ON v.usuario_id = u.id
                  WHERE $where_sql
                  ORDER BY v.data_venda DESC";

        $stmt = $connection->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar vendas com filtros: " . $e->getMessage());
        return [];
    }
}