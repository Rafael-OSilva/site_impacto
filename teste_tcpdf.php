<?php
require_once 'lib/tcpdf/tcpdf.php';

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Teste TCPDF - Funcionando!', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'Data: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
$pdf->Output('teste_tcpdf.pdf', 'I');
?>