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
                        <a href="../logout.php" class="btn btn-sm btn-outline-danger">Sair</a>
                    </div>
                </div>

                <?php displayFlashMessage(); ?>

                <!-- Rest of the dashboard content remains the same -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Total de Pedidos</h5>
                                <p class="card-text h3"><?php echo $stats['pedidos']['total_pedidos'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    <!-- Rest of the cards and content remain the same -->
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>