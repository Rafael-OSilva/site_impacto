<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_module_page = (strpos($_SERVER['PHP_SELF'], 'modules/') !== false);
?>
<aside class="sidebar">
    <div class="menu-item <?= ($current_page == 'index.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? '../index.php' : 'index.php' ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
    </div>
    <div class="menu-item <?= ($current_page == 'abrir_caixa.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? 'abrir_caixa.php' : 'modules/abrir_caixa.php' ?>">
            <i class="fas fa-door-open"></i> Abrir Caixa
        </a>
    </div>
    <div class="menu-item <?= ($current_page == 'registrar_venda.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? 'registrar_venda.php' : 'modules/registrar_venda.php' ?>">
            <i class="fas fa-shopping-cart"></i> Registrar Venda
        </a>
    </div>
    <div class="menu-item <?= ($current_page == 'contas_receber.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? 'contas_receber.php' : 'modules/contas_receber.php' ?>">
            <i class="fas fa-money-bill-wave"></i> Contas a Receber
        </a>
    </div>
    <div class="menu-item <?= ($current_page == 'retiradas.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? 'retiradas.php' : 'modules/retiradas.php' ?>">
            <i class="fas fa-money-bill-wave"></i> Retiradas
        </a>
    </div>
    <div class="menu-item <?= ($current_page == 'fechar_caixa.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? 'fechar_caixa.php' : 'modules/fechar_caixa.php' ?>">
            <i class="fas fa-door-closed"></i> Fechar Caixa
        </a>
    </div>
    <div class="menu-item <?= ($current_page == 'relatorios.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? 'relatorios.php' : 'modules/relatorios.php' ?>">
            <i class="fas fa-chart-bar"></i> Relatórios
        </a>
    </div>
    
    <!-- CORREÇÃO AQUI: Link para Crédito Cliente -->
    <div class="menu-item <?= ($current_page == 'credito_cliente.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? 'credito_cliente.php' : 'modules/credito_cliente.php' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-credit-card-fill" viewBox="0 0 16 16">
                <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v1H0zm0 3v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7zm3 2h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1a1 1 0 0 1 1-1"/>
            </svg> Crédito Cliente
        </a>
    </div>
    
    <!-- CORREÇÃO AQUI: Link para Histórico de Vendas -->
    <div class="menu-item <?= ($current_page == 'historico_credito.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? 'historico_credito.php' : 'modules/historico_credito.php' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock-history" viewBox="0 0 16 16">
                <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022zm2.004.45a7 7 0 0 0-.985-.299l.219-.976c.383.086.76.2 1.126.342zm1.37.71a7 7 0 0 0-.439-.27l.493-.87a8 8 0 0 1 .979.654l-.615.789a7 7 0 0 0-.418-.302zm1.834 1.79a7 7 0 0 0-.653-.796l.724-.69c.27.285.52.59.747.91zm.744 1.352a7 7 0 0 0-.214-.468l.893-.45a8 8 0 0 1 .45 1.088l-.95.313a7 7 0 0 0-.179-.483m.53 2.507a7 7 0 0 0-.1-1.025l.985-.17c.067.386.106.78.116 1.17zM7 1h2a0 0 0 0 1 0V0a8 8 0 0 1 2.734.355l-.256.967a7 7 0 0 0-2.478-.322"/>
                <path d="M8 14a7 7 0 1 0 0-14 7 7 0 0 0 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                <path d="M8 13.5a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11m0 .5A6 6 0 1 0 8 2a6 6 0 0 0 0 12"/>
            </svg> Histórico de Vendas
        </a>
    </div>
</aside>