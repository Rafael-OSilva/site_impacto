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

// ADICIONAR AS FUNÇÕES QUE ESTÃO FALTANDO
if (!function_exists('obterResumoPagamentos')) {
    function obterResumoPagamentos($connection, $data_inicial, $data_final)
    {
        try {
            $data_final_ajustada = $data_final . ' 23:59:59';

            $sql = "SELECT 
                        fp.nome,
                        COUNT(v.id) as quantidade,
                        COALESCE(SUM(v.valor_total), 0) as total
                    FROM formas_pagamento fp
                    LEFT JOIN vendas v ON fp.id = v.forma_pagamento_id 
                        AND v.data_venda BETWEEN :data_inicial AND :data_final_ajustada
                        AND v.status = 'concluida'
                    WHERE fp.ativo = 1
                    GROUP BY fp.id, fp.nome
                    ORDER BY total DESC";

            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':data_inicial', $data_inicial);
            $stmt->bindParam(':data_final_ajustada', $data_final_ajustada);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao obter resumo de pagamentos: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('obterRetiradas')) {
    function obterRetiradas($connection, $data_inicial, $data_final)
    {
        try {
            $sql = "SELECT 
                        r.data_retirada,
                        r.valor,
                        r.motivo,
                        u.nome as usuario_nome
                    FROM retiradas r
                    INNER JOIN usuarios u ON r.usuario_id = u.id
                    WHERE DATE(r.data_retirada) BETWEEN :data_inicial AND :data_final
                    ORDER BY r.data_retirada DESC";

            $stmt = $connection->prepare($sql);
            $stmt->bindParam(':data_inicial', $data_inicial);
            $stmt->bindParam(':data_final', $data_final);
            $stmt->execute();
            $retiradas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular total
            $total_retiradas = 0;
            foreach ($retiradas as $retirada) {
                $total_retiradas += $retirada['valor'];
            }

            return [
                'detalhes' => $retiradas,
                'total' => $total_retiradas
            ];
        } catch (PDOException $e) {
            error_log("Erro ao obter retiradas: " . $e->getMessage());
            return [
                'detalhes' => [],
                'total' => 0
            ];
        }
    }
}

if (!function_exists('obterVendasPeriodo')) {
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
}

// Define o período padrão (hoje)
$data_inicial = date('Y-m-d');
$data_final = date('Y-m-d');

// Se o formulário foi enviado, usa as datas do formulário
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['data_inicial'])) {
    $data_inicial = $_GET['data_inicial'];
    $data_final = $_GET['data_final'];
}
// RELATÓRIO PDF DE VENDAS
if (isset($_GET['gerar_pdf'])) {
    require_once('../lib/tcpdf/tcpdf.php');

    // Busca os dados do relatório usando funções do functions.php
    $vendas = obterVendasPeriodo($connection, $data_inicial, $data_final);
    $resumo_pagamentos = obterResumoPagamentos($connection, $data_inicial, $data_final);

    // Calcular total geral
    $total_geral = 0;
    foreach ($resumo_pagamentos as $resumo) {
        $total_geral += $resumo['total'];
    }

    // Buscar saldos e retiradas usando funções do functions.php
    $saldo_inicial = obterSaldoInicial($connection, $data_inicial);
    $saldo_final = obterSaldoFinal($connection, $data_final);
    $retiradas = obterRetiradas($connection, $data_inicial, $data_final);

    // Configurar PDF em retrato
    $pdf = new TCPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);

    // Configurar informações do documento
    $pdf->SetCreator('Caixa Impacto');
    $pdf->SetAuthor('Sistema Caixa Impacto');
    $pdf->SetTitle('Relatório de Vendas');
    $pdf->SetSubject('Relatório do Sistema');

    // Configurar margens para retrato
    $pdf->SetMargins(15, 25, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);

    // Quebras de página automáticas
    $pdf->SetAutoPageBreak(TRUE, 25);

    // Adicionar uma página em RETRATO
    $pdf->AddPage();

    // Configurar fonte
    $pdf->SetFont('helvetica', 'B', 16);

    // Título do relatório
    $pdf->Cell(0, 10, 'RELATÓRIO DE VENDAS', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Período: ' . date('d/m/Y', strtotime($data_inicial)) . ' a ' . date('d/m/Y', strtotime($data_final)), 0, 1, 'C');

    // Espaço
    $pdf->Ln(5);

    // SEÇÃO DE SALDOS
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'SALDOS DO CAIXA', 0, 1);
    $pdf->SetFont('helvetica', '', 10);

    // Tabela de saldos
    $pdf->SetFillColor(240, 248, 255);
    $pdf->Cell(60, 8, 'Saldo Inicial', 1, 0, 'L', 1);
    $pdf->Cell(60, 8, 'Saldo Final', 1, 1, 'L', 1);

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(60, 8, 'R$ ' . number_format($saldo_inicial, 2, ',', '.'), 1, 0, 'L');
    $pdf->Cell(60, 8, 'R$ ' . number_format($saldo_final, 2, ',', '.'), 1, 1, 'L');

    $pdf->Ln(8);

    // Resumo financeiro
    if (!empty($resumo_pagamentos)) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'RESUMO FINANCEIRO', 0, 1);
        $pdf->SetFont('helvetica', '', 10);

        // Larguras das colunas para retrato
        $col_widths = array(80, 40, 40);

        // Cabeçalho da tabela de resumo
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.3);

        $pdf->Cell($col_widths[0], 7, 'FORMA DE PAGAMENTO', 1, 0, 'C', 1);
        $pdf->Cell($col_widths[1], 7, 'QUANTIDADE', 1, 0, 'C', 1);
        $pdf->Cell($col_widths[2], 7, 'VALOR TOTAL', 1, 1, 'C', 1);

        // Dados do resumo
        $pdf->SetFillColor(224, 235, 255);
        $pdf->SetTextColor(0);
        $fill = false;

        foreach ($resumo_pagamentos as $resumo) {
            $nome = $resumo['nome'] ?? 'N/A';
            $quantidade = $resumo['quantidade'] ?? 0;
            $total = $resumo['total'] ?? 0;

            $pdf->Cell($col_widths[0], 6, $nome, 'LR', 0, 'L', $fill);
            $pdf->Cell($col_widths[1], 6, $quantidade, 'LR', 0, 'C', $fill);
            $pdf->Cell($col_widths[2], 6, 'R$ ' . number_format($total, 2, ',', '.'), 'LR', 1, 'R', $fill);
            $fill = !$fill;
        }

        // Fechar a tabela
        $pdf->Cell(array_sum($col_widths), 0, '', 'T');
        $pdf->Ln(8);

        // Total geral
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'TOTAL GERAL: R$ ' . number_format($total_geral, 2, ',', '.'), 0, 1, 'R');
        $pdf->Ln(5);
    }

    // SEÇÃO DE RETIRADAS
    if (!empty($retiradas['detalhes'])) {
        // Verificar se precisa de nova página
        if ($pdf->GetY() > 180) {
            $pdf->AddPage();
        }

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'RELATÓRIO DE RETIRADAS', 0, 1);
        $pdf->SetFont('helvetica', '', 10);

        // Cabeçalho da tabela de retiradas
        $pdf->SetFillColor(139, 0, 0); // Vermelho escuro para retiradas
        $pdf->SetTextColor(255);

        $col_widths_retiradas = array(40, 50, 60, 30);

        $pdf->Cell($col_widths_retiradas[0], 7, 'DATA', 1, 0, 'C', 1);
        $pdf->Cell($col_widths_retiradas[1], 7, 'MOTIVO', 1, 0, 'C', 1);
        $pdf->Cell($col_widths_retiradas[2], 7, 'RESPONSÁVEL', 1, 0, 'C', 1);
        $pdf->Cell($col_widths_retiradas[3], 7, 'VALOR', 1, 1, 'C', 1);

        // Dados das retiradas
        $pdf->SetFillColor(255, 240, 240); // Cor diferente para retiradas
        $pdf->SetTextColor(0);
        $fill = false;

        foreach ($retiradas['detalhes'] as $retirada) {
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                // Recriar cabeçalho
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->SetFillColor(139, 0, 0);
                $pdf->SetTextColor(255);
                $pdf->Cell($col_widths_retiradas[0], 7, 'DATA', 1, 0, 'C', 1);
                $pdf->Cell($col_widths_retiradas[1], 7, 'MOTIVO', 1, 0, 'C', 1);
                $pdf->Cell($col_widths_retiradas[2], 7, 'RESPONSÁVEL', 1, 0, 'C', 1);
                $pdf->Cell($col_widths_retiradas[3], 7, 'VALOR', 1, 1, 'C', 1);
                $pdf->SetFont('helvetica', '', 10);
                $pdf->SetTextColor(0);
            }

            $data_retirada = $retirada['data_retirada'] ?? '';
            $motivo = $retirada['motivo'] ?? 'Não informado';
            $usuario = $retirada['usuario_nome'] ?? 'Não informado';
            $valor = $retirada['valor'] ?? 0;

            $data_formatada = $data_retirada ? date('d/m/Y', strtotime($data_retirada)) : 'N/A';

            $pdf->Cell($col_widths_retiradas[0], 6, $data_formatada, 'LR', 0, 'C', $fill);
            $pdf->Cell($col_widths_retiradas[1], 6, substr($motivo, 0, 25), 'LR', 0, 'L', $fill);
            $pdf->Cell($col_widths_retiradas[2], 6, substr($usuario, 0, 25), 'LR', 0, 'L', $fill);
            $pdf->Cell($col_widths_retiradas[3], 6, 'R$ ' . number_format($valor, 2, ',', '.'), 'LR', 1, 'R', $fill);

            $fill = !$fill;
        }

        // Fechar a tabela e mostrar total
        $pdf->Cell(array_sum($col_widths_retiradas), 0, '', 'T');
        $pdf->Ln(5);

        // Total das retiradas
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'TOTAL DE RETIRADAS: R$ ' . number_format($retiradas['total'], 2, ',', '.'), 0, 1, 'R');
        $pdf->Ln(5);
    }

    // Tabela de vendas detalhadas
    if (!empty($vendas)) {
        // Verificar se precisa de nova página
        if ($pdf->GetY() > 150) {
            $pdf->AddPage();
        }

        $quantidade_vendas = count($vendas);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'DETALHAMENTO DAS VENDAS (' . $quantidade_vendas . ' vendas)', 0, 1);
        $pdf->SetFont('helvetica', '', 8);

        // Larguras das colunas para retrato
        $col_widths = array(25, 25, 30, 35, 55);

        // Cabeçalho da tabela
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255);

        $pdf->Cell($col_widths[0], 7, 'DATA', 1, 0, 'C', 1);
        $pdf->Cell($col_widths[1], 7, 'VALOR', 1, 0, 'C', 1);
        $pdf->Cell($col_widths[2], 7, 'MÉTODO', 1, 0, 'C', 1);
        $pdf->Cell($col_widths[3], 7, 'OPERADOR', 1, 0, 'C', 1);
        $pdf->Cell($col_widths[4], 7, 'DESCRIÇÃO', 1, 1, 'C', 1);

        // Dados das vendas
        $pdf->SetFillColor(224, 235, 255);
        $pdf->SetTextColor(0);
        $fill = false;

        foreach ($vendas as $venda) {
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                // Recriar cabeçalho
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetFillColor(52, 73, 94);
                $pdf->SetTextColor(255);
                $pdf->Cell($col_widths[0], 7, 'DATA', 1, 0, 'C', 1);
                $pdf->Cell($col_widths[1], 7, 'VALOR', 1, 0, 'C', 1);
                $pdf->Cell($col_widths[2], 7, 'MÉTODO', 1, 0, 'C', 1);
                $pdf->Cell($col_widths[3], 7, 'OPERADOR', 1, 0, 'C', 1);
                $pdf->Cell($col_widths[4], 7, 'DESCRIÇÃO', 1, 1, 'C', 1);
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetTextColor(0);
            }

            $data_venda = $venda['data_venda'] ?? '';
            $valor_total = $venda['valor_total'] ?? 0;
            $forma_pagamento = $venda['forma_pagamento'] ?? 'N/A';
            $nome_usuario = $venda['usuario_nome'] ?? 'N/A';
            $descricao = $venda['descricao'] ?? '';

            $data_formatada = $data_venda ? date('d/m/Y H:i', strtotime($data_venda)) : 'N/A';

            $pdf->Cell($col_widths[0], 6, $data_formatada, 'LR', 0, 'C', $fill);
            $pdf->Cell($col_widths[1], 6, 'R$ ' . number_format($valor_total, 2, ',', '.'), 'LR', 0, 'R', $fill);
            $pdf->Cell($col_widths[2], 6, substr($forma_pagamento, 0, 12), 'LR', 0, 'L', $fill);
            $pdf->Cell($col_widths[3], 6, substr($nome_usuario, 0, 10), 'LR', 0, 'L', $fill);
            $pdf->Cell($col_widths[4], 6, substr($descricao, 0, 30), 'LR', 1, 'L', $fill);

            $fill = !$fill;
        }

        // Fechar a tabela
        $pdf->Cell(array_sum($col_widths), 0, '', 'T');
    } else {
        $pdf->SetFont('helvetica', 'I', 12);
        $pdf->Cell(0, 10, 'Nenhuma venda encontrada para o período selecionado', 0, 1, 'C');
    }

    // Rodapé
    $pdf->SetY(-25);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 10, 'Relatório gerado em: ' . date('d/m/Y H:i:s'), 0, 0, 'L');
    $pdf->Cell(0, 10, 'Página ' . $pdf->getAliasNumPage() . '/' . $pdf->getAliasNbPages(), 0, 0, 'R');

    // Gerar e enviar o PDF
    ob_clean();
    $pdf->Output('relatorio_vendas_' . date('Ymd_His') . '.pdf', 'I');
    exit;
}

// RELATÓRIO PDF DE CONTAS A RECEBER
if (isset($_GET['acao']) && $_GET['acao'] == 'gerar_pdf_contas_receber') {
    require_once('../lib/tcpdf/tcpdf.php');

    // Buscar contas a receber com filtros
    $filtros = [
        'cliente' => $_GET['cliente'] ?? '',
        'data' => $_GET['data'] ?? '',
        'valor' => $_GET['valor'] ?? ''
    ];

    $contas_receber = obterContasAReceber($connection, $filtros);
    $total_a_receber = 0;

    foreach ($contas_receber as $conta) {
        $total_a_receber += $conta['valor_total'];
    }

    // Criar PDF em retrato
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Caixa Impacto');
    $pdf->SetAuthor('Sistema Caixa Impacto');
    $pdf->SetTitle('Relatório de Contas a Receber');
    $pdf->AddPage();

    // Logo e Cabeçalho
    $html = '
    <div style="text-align: center;">
        <h1>Relatório de Contas a Receber</h1>
        <p><strong>Emitido em:</strong> ' . date('d/m/Y H:i') . '</p>
        <p><strong>Total de Contas:</strong> ' . count($contas_receber) . '</p>
        <p><strong>Valor Total a Receber:</strong> R$ ' . number_format($total_a_receber, 2, ',', '.') . '</p>
    </div>
    <br>
    ';

    // Mostrar filtros aplicados
    $filtros_texto = [];
    if (!empty($filtros['cliente'])) {
        $filtros_texto[] = "Cliente: " . $filtros['cliente'];
    }
    if (!empty($filtros['data'])) {
        $filtros_texto[] = "Data: " . date('d/m/Y', strtotime($filtros['data']));
    }
    if (!empty($filtros['valor'])) {
        $textos_valor = [
            'menor100' => 'Valor: Menor que R$ 100',
            '100a500' => 'Valor: R$ 100 a R$ 500',
            'maior500' => 'Valor: Maior que R$ 500'
        ];
        $filtros_texto[] = $textos_valor[$filtros['valor']] ?? 'Valor: Filtrado';
    }

    if (!empty($filtros_texto)) {
        $html .= '<p><strong>Filtros aplicados:</strong> ' . implode(' | ', $filtros_texto) . '</p>';
    }

    if (!empty($contas_receber)) {
        $html .= '
        <table border="1" cellpadding="4" style="font-size: 10px; width: 100%;">
            <tr style="background-color: #f8f9fa;">
                <th width="15%">Data Venda</th>
                <th width="10%">Nº Venda</th>
                <th width="15%">Valor</th>
                <th width="30%">Cliente</th>
                <th width="20%">Operador</th>
                <th width="10%">Status</th>
            </tr>
        ';

        foreach ($contas_receber as $conta) {
            $descricao = $conta['descricao'] ?? '';
            $nome_cliente = 'Cliente não informado';

            if (strpos($descricao, 'Cliente:') !== false) {
                $parts = explode('|', $descricao);
                $nome_cliente = trim(str_replace('Cliente:', '', $parts[0]));
            } else {
                $nome_cliente = $descricao;
            }

            $html .= '
            <tr>
                <td>' . date('d/m/Y H:i', strtotime($conta['data_venda'])) . '</td>
                <td>#' . $conta['id'] . '</td>
                <td>R$ ' . number_format($conta['valor_total'], 2, ',', '.') . '</td>
                <td>' . $nome_cliente . '</td>
                <td>' . $conta['operador'] . '</td>
                <td>PENDENTE</td>
            </tr>
            ';
        }

        $html .= '</table>';

        // Resumo final
        $html .= '
        <br>
        <div style="text-align: right; font-weight: bold;">
            <p>Total de contas: ' . count($contas_receber) . '</p>
            <p>Valor total: R$ ' . number_format($total_a_receber, 2, ',', '.') . '</p>
        </div>
        ';
    } else {
        $html .= '<p style="text-align: center; font-style: italic;">Nenhuma conta a receber encontrada.</p>';
    }

    $pdf->writeHTML($html, true, false, true, false, '');

    // Rodapé
    $pdf->SetY(-25);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 10, 'Relatório gerado em: ' . date('d/m/Y H:i:s'), 0, 0, 'L');
    $pdf->Cell(0, 10, 'Página ' . $pdf->getAliasNumPage() . '/' . $pdf->getAliasNbPages(), 0, 0, 'R');

    ob_clean();
    $pdf->Output('contas_receber_' . date('Y-m-d') . '.pdf', 'I');
    exit;
}

// BUSCA OS DADOS PARA EXIBIÇÃO NA TELA (CORRIGIDO)
$vendas = obterVendasPeriodo($connection, $data_inicial, $data_final);
$resumo_pagamentos = obterResumoPagamentos($connection, $data_inicial, $data_final);

// Calcular total geral para exibição
$total_geral = 0;
foreach ($resumo_pagamentos as $resumo) {
    $total_geral += $resumo['total'];
}

// Buscar saldos e retiradas para exibição na tela
$saldo_inicial = obterSaldoInicial($connection, $data_inicial);
$saldo_final = obterSaldoFinal($connection, $data_final);
$retiradas = obterRetiradas($connection, $data_inicial, $data_final);

// Variável para o status do caixa no header
$status_caixa = verificarStatusCaixa($connection);

// CRIAR VARIÁVEL $relatorio PARA COMPATIBILIDADE COM O CÓDIGO EXISTENTE
$relatorio = [
    'vendas' => $vendas,
    'resumo_pagamentos' => $resumo_pagamentos,
    'total_geral' => $total_geral,
    'quantidade_vendas' => count($vendas)
];
?>

<!-- A PARTE HTML PERMANECE EXATAMENTE COMO ESTAVA -->
<!-- (com as seções de saldos, retiradas e tudo mais) -->

<!-- NA PARTE HTML, ADICIONE ESTAS SEÇÕES NOVAS -->

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Caixa Impacto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* ESTILOS EXISTENTES... */
        .financial-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
            border-top: 4px solid var(--primary);
        }

        .summary-card.total {
            border-top-color: var(--warning);
        }

        .summary-icon {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--primary);
        }

        .summary-card.total .summary-icon {
            color: var(--warning);
        }

        .summary-value {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--dark);
            margin: 5px 0;
        }

        .summary-label {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .summary-count {
            font-size: 0.8rem;
            color: var(--secondary);
            margin-top: 5px;
        }

        .date-filters {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }

        @media (max-width: 768px) {
            .date-filters {
                grid-template-columns: 1fr;
            }

            .financial-summary {
                grid-template-columns: 1fr;
            }
        }

        .report-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th {
            background-color: #34495e;
            color: white;
            padding: 10px;
            text-align: left;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        tfoot th {
            background-color: #f8f9fa;
            color: #2c3e50;
            text-align: right;
        }

        .pdf-info {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 10px 15px;
            margin: 15px 0;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .nav-tabs {
            display: flex;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
        }

        .nav-tab {
            padding: 10px 20px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-bottom: none;
            margin-right: 5px;
            border-radius: 4px 4px 0 0;
            text-decoration: none;
            color: #495057;
        }

        .nav-tab.active {
            background: white;
            border-bottom: 2px solid white;
            margin-bottom: -2px;
            font-weight: bold;
            color: #007bff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* NOVOS ESTILOS PARA SALDOS E RETIRADAS */
        .saldos-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .saldo-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .saldo-inicial {
            border-top: 4px solid #28a745;
        }

        .saldo-final {
            border-top: 4px solid #dc3545;
        }

        .saldo-icon {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .saldo-inicial .saldo-icon {
            color: #28a745;
        }

        .saldo-final .saldo-icon {
            color: #dc3545;
        }

        .saldo-value {
            font-size: 1.4rem;
            font-weight: bold;
            margin: 5px 0;
        }

        .retiradas-section {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .retiradas-section h3 {
            margin-top: 0;
            color: #dc3545;
        }

        @media (max-width: 768px) {
            .saldos-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <?php include '../includes/header.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <main>
            <h1 class="page-title"><i class="fas fa-chart-bar"></i> Relatórios</h1>

            <div class="pdf-info">
                <i class="fas fa-info-circle"></i>
                <strong>Novo:</strong> Agora o relatório inclui Saldo Inicial, Saldo Final e Relatório de Retiradas com total.
            </div>

            <!-- Navegação entre abas -->
            <div class="nav-tabs">
                <a href="#vendas" class="nav-tab active" onclick="openTab(event, 'vendas')">
                    <i class="fas fa-shopping-cart"></i> Relatório de Vendas
                </a>
                <a href="#contas-receber" class="nav-tab" onclick="openTab(event, 'contas-receber')">
                    <i class="fas fa-money-bill-wave"></i> Contas a Receber
                </a>
            </div>

            <!-- Aba de Relatório de Vendas -->
            <div id="vendas" class="tab-content active">
                <div class="card">
                    <div class="card-header"><span>Filtros do Relatório de Vendas</span></div>
                    <div class="card-body">
                        <form method="GET">
                            <input type="hidden" name="tab" value="vendas">
                            <div class="date-filters">
                                <div class="form-group">
                                    <label for="data_inicial">Data Inicial</label>
                                    <input type="date" id="data_inicial" name="data_inicial" value="<?= htmlspecialchars($data_inicial) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="data_final">Data Final</label>
                                    <input type="date" id="data_final" name="data_final" value="<?= htmlspecialchars($data_final) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn-primary btn-block">Gerar Relatório</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (!empty($vendas) || !empty($retiradas['detalhes'])): ?>
                    <div class="card" id="report-results">
                        <div class="card-header">
                            <span>Relatório de Vendas</span>
                            <span>Período: <?= date('d/m/Y', strtotime($data_inicial)) ?> a <?= date('d/m/Y', strtotime($data_final)) ?></span>
                        </div>
                        <div class="card-body">

                            <!-- SEÇÃO DE SALDOS -->
                            <div class="saldos-section">
                                <div class="saldo-card saldo-inicial">
                                    <div class="saldo-icon">
                                        <i class="fas fa-wallet"></i>
                                    </div>
                                    <div class="saldo-label">Saldo Inicial</div>
                                    <div class="saldo-value"><?= formatarMoeda($saldo_inicial) ?></div>
                                </div>

                                <div class="saldo-card saldo-final">
                                    <div class="saldo-icon">
                                        <i class="fas fa-cash-register"></i>
                                    </div>
                                    <div class="saldo-label">Saldo Final</div>
                                    <div class="saldo-value"><?= formatarMoeda($saldo_final) ?></div>
                                </div>
                            </div>

                            <!-- Resumo financeiro -->
                            <div class="financial-summary">
                                <?php foreach ($resumo_pagamentos as $resumo): ?>
                                    <div class="summary-card">
                                        <div class="summary-icon">
                                            <?php
                                            $icones = [
                                                'Dinheiro' => 'fa-money-bill-wave',
                                                'Cartão Débito' => 'fa-credit-card',
                                                'Cartão Crédito' => 'fa-credit-card',
                                                'PIX' => 'fa-qrcode',
                                                'A Receber' => 'fa-clock'
                                            ];
                                            $icone = $icones[$resumo['nome']] ?? 'fa-money-bill-wave';
                                            ?>
                                            <i class="fas <?= $icone ?>"></i>
                                        </div>
                                        <div class="summary-value"><?= formatarMoeda($resumo['total'] ?? 0) ?></div>
                                        <div class="summary-label"><?= htmlspecialchars($resumo['nome'] ?? 'N/A') ?></div>
                                        <div class="summary-count"><?= ($resumo['quantidade'] ?? 0) ?> vendas</div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="summary-card total">
                                    <div class="summary-icon"><i class="fas fa-chart-line"></i></div>
                                    <div class="summary-value"><?= formatarMoeda($total_geral) ?></div>
                                    <div class="summary-label">Total Geral</div>
                                    <div class="summary-count"><?= count($vendas) ?> vendas</div>
                                </div>
                            </div>

                            <!-- SEÇÃO DE RETIRADAS -->
                            <?php if (!empty($retiradas['detalhes'])): ?>
                                <div class="retiradas-section">
                                    <h3><i class="fas fa-money-bill-wave"></i> Retiradas Realizadas</h3>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Data</th>
                                                <th>Motivo</th>
                                                <th>Responsável</th>
                                                <th>Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($retiradas['detalhes'] as $retirada): ?>
                                                <tr>
                                                    <td><?= date('d/m/Y', strtotime($retirada['data_retirada'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars($retirada['motivo'] ?? 'Não informado') ?></td>
                                                    <td><?= htmlspecialchars($retirada['usuario_nome'] ?? 'Não informado') ?></td>
                                                    <td><?= formatarMoeda($retirada['valor'] ?? 0) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="3" style="text-align: right;">Total de Retiradas:</th>
                                                <th><?= formatarMoeda($retiradas['total']) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <!-- Tabela de vendas detalhadas -->
                            <h3>Detalhamento das Vendas</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Data/Hora</th>
                                        <th>Valor</th>
                                        <th>Método</th>
                                        <th>Operador</th>
                                        <th>Descrição</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vendas as $venda): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($venda['data_venda'] ?? '')) ?></td>
                                            <td><?= formatarMoeda($venda['valor_total'] ?? 0) ?></td>
                                            <td><?= htmlspecialchars($venda['forma_pagamento'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($venda['usuario_nome'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($venda['descricao'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" style="text-align: right;">Total Geral:</th>
                                        <th><?= formatarMoeda($total_geral) ?></th>
                                    </tr>
                                </tfoot>
                            </table>

                            <div class="report-actions">
                                <a href="?<?= http_build_query([
                                                'data_inicial' => $data_inicial,
                                                'data_final' => $data_final,
                                                'gerar_pdf' => true
                                            ]) ?>" class="btn-danger" target="_blank">
                                    <i class="fas fa-file-pdf"></i> Gerar PDF Completo
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card" id="no-results">
                        <div class="card-body" style="text-align: center; padding: 40px; color: var(--gray);">
                            <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 15px;"></i>
                            <h3>Nenhum resultado encontrado</h3>
                            <p>Não foram encontradas vendas ou retiradas para o período selecionado.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Aba de Contas a Receber -->
            <div id="contas-receber" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <span>Relatório de Contas a Receber</span>
                    </div>
                    <div class="card-body">
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-file-pdf" style="font-size: 3rem; color: #dc3545; margin-bottom: 15px;"></i>
                            <h3>Relatório de Contas a Receber</h3>
                            <p>Gere um relatório PDF com todas as contas a receber pendentes.</p>
                            <p>O relatório inclui filtros por cliente, data e valor.</p>

                            <div style="margin-top: 20px;">
                                <a href="contas_receber.php" class="btn-primary" style="margin-right: 10px;">
                                    <i class="fas fa-list"></i> Ver Contas a Receber
                                </a>
                                <a href="relatorios.php?acao=gerar_pdf_contas_receber" class="btn-danger" target="_blank">
                                    <i class="fas fa-file-pdf"></i> Gerar PDF Completo
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <?php include '../includes/footer.php'; ?>
    </div>

    <script>
        function openTab(evt, tabName) {
            // Esconder todas as abas
            var tabcontent = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }

            // Remover classe active de todas as abas
            var tablinks = document.getElementsByClassName("nav-tab");
            for (var i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }

            // Mostrar a aba atual e adicionar classe active
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>

</html>