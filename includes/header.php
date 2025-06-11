<?php
// includes/header.php
// Verifica se a sessão já está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define funções úteis para o cabeçalho
$isAdmin = isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'administrador';
$isOperator = isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'operador';
$isLoggedIn = isset($_SESSION['usuario_id']);

// Define o caminho base para links, dependendo da localização do arquivo que inclui o cabeçalho
$basePath = '';
if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) {
    $basePath = '../';
} else if (strpos($_SERVER['SCRIPT_NAME'], '/public/') !== false) {
    $basePath = '../';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GastroHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        main {
            flex: 1;
        }
        .navbar-brand {
            font-weight: bold;
            color: #198754 !important;
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="<?php echo $basePath; ?><?php echo ($isAdmin || $isOperator) ? 'admin/dashboard.php' : 'public/cardapio.php'; ?>">
                    <i class="bi bi-book"></i> GastroHub
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <?php if ($isLoggedIn): ?>
                        <?php if ($isAdmin || $isOperator): ?>
                            <!-- Menu Administrador/Operador -->
                            <ul class="navbar-nav">
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $basePath; ?>admin/dashboard.php">
                                        <i class="bi bi-speedometer2"></i> Dashboard
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $basePath; ?>admin/pedidos.php">
                                        <i class="bi bi-list-check"></i> Pedidos
                                    </a>
                                </li>
                                <?php if ($isAdmin): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $basePath; ?>admin/produtos.php">
                                        <i class="bi bi-box"></i> Produtos
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $basePath; ?>admin/categorias.php">
                                        <i class="bi bi-tag"></i> Categorias
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $basePath; ?>admin/usuarios.php">
                                        <i class="bi bi-people"></i> Usuários
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        <?php else: ?>
                            <!-- Menu Cliente -->
                            <ul class="navbar-nav">
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $basePath; ?>public/cardapio.php">
                                        <i class="bi bi-grid"></i> Cardápio
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $basePath; ?>public/meus-pedidos.php">
                                        <i class="bi bi-receipt"></i> Meus Pedidos
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo $basePath; ?>perfil.php">
                                        <i class="bi bi-person"></i> Meu Perfil
                                    </a>
                                </li>
                            </ul>
                        <?php endif; ?>
                        
                        <!-- Área da direita - Usuário logado -->
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['nome_usuario']); ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item" href="<?php echo $basePath; ?>perfil.php">Meu Perfil</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="<?php echo $basePath; ?>logout.php">Sair</a></li>
                                </ul>
                            </li>
                            <?php if (!$isAdmin && !$isOperator): ?>
                            <li class="nav-item">
                                <a href="<?php echo $basePath; ?>public/carrinho.php" class="btn btn-outline-success ms-2">
                                    <i class="bi bi-cart"></i> Carrinho
                                    <?php if (isset($_SESSION['carrinho']) && !empty($_SESSION['carrinho'])): ?>
                                        <span class="badge bg-danger rounded-pill"><?php echo count($_SESSION['carrinho']); ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    <?php else: ?>
                        <!-- Área da direita - Usuário não logado -->
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $basePath; ?>login.php">Entrar</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $basePath; ?>cadastro.php">Cadastrar</a>
                            </li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <main class="container py-4">
        <!-- Exibir mensagens flash -->
        <?php displayFlashMessage(); ?>
