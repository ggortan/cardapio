<?php
// admin/dashboard.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an administrator
if (!isLoggedIn() || $_SESSION['tipo_usuario'] !== 'administrador') {
    flashMessage('Você precisa fazer login como administrador', 'error');
    redirectTo('../login.php');
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Estatísticas gerais
    $stats = [];

    // Total de pedidos
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_pedidos,
            SUM(total) as receita_total,
            AVG(total) as ticket_medio
        FROM Pedido
    ");
    $stmt->execute();
    $stats['pedidos'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Pedidos por status
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) as quantidade 
        FROM Pedido 
        GROUP BY status
    ");
    $stmt->execute();
    $stats['pedidos_por_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Total de usuários
    $stmt = $conn->query("SELECT COUNT(*) as total_usuarios FROM Usuario");
    $stats['usuarios'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_usuarios'];

    // Total de produtos
    $stmt = $conn->query("SELECT COUNT(*) as total_produtos FROM Produto");
    $stats['produtos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_produtos'];

    // Pedidos recentes
    $stmt = $conn->prepare("
        SELECT 
            p.id_pedido, 
            p.data_pedido, 
            p.status, 
            p.total,
            u.nome as nome_usuario
        FROM Pedido p
        JOIN Usuario u ON p.id_usuario = u.id_usuario
        ORDER BY p.data_pedido DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $stats['pedidos_recentes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    flashMessage('Erro ao carregar estatísticas: ' . $e->getMessage(), 'error');
    $stats = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Administrativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="../public/cardapio.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-cart"></i> Fazer Pedido
                            </a>
                            <a href="../logout.php" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-box-arrow-right"></i> Sair
                            </a>
                        </div>
                    </div>
                </div>

                <?php displayFlashMessage(); ?>

                <div class="row">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Total de Pedidos</h5>
                                <p class="card-text h3"><?php echo $stats['pedidos']['total_pedidos'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Receita Total</h5>
                                <p class="card-text h3">
                                    <?php echo formatCurrency($stats['pedidos']['receita_total'] ?? 0); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Usuários Cadastrados</h5>
                                <p class="card-text h3"><?php echo $stats['usuarios'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Total de Produtos</h5>
                                <p class="card-text h3"><?php echo $stats['produtos'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">Pedidos Recentes</div>
                            <div class="card-body">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Pedido</th>
                                            <th>Cliente</th>
                                            <th>Data</th>
                                            <th>Status</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['pedidos_recentes'] ?? [] as $pedido): ?>
                                            <tr>
                                                <td>#<?php echo $pedido['id_pedido']; ?></td>
                                                <td><?php echo htmlspecialchars($pedido['nome_usuario']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></td>
                                                <td>
                                                    <span class="badge 
                                                        <?php 
                                                        echo match($pedido['status']) {
                                                            'pendente' => 'bg-warning',
                                                            'preparando' => 'bg-info',
                                                            'pronto' => 'bg-primary',
                                                            'enviado' => 'bg-secondary',
                                                            'entregue' => 'bg-success',
                                                            'cancelado' => 'bg-danger',
                                                            default => 'bg-light text-dark'
                                                        };
                                                        ?>">
                                                        <?php echo ucfirst($pedido['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatCurrency($pedido['total']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">Status dos Pedidos</div>
                            <div class="card-body">
                                <ul class="list-group">
                                    <?php foreach ($stats['pedidos_por_status'] ?? [] as $status): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo ucfirst($status['status']); ?>
                                            <span class="badge bg-primary rounded-pill">
                                                <?php echo $status['quantidade']; ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>