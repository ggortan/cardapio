<?php
// admin/dashboard.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar permissão de administrador ou operador
if (!isLoggedIn() || ($_SESSION['tipo_usuario'] !== 'administrador' && $_SESSION['tipo_usuario'] !== 'operador')) {
    flashMessage('Acesso não autorizado', 'error');
    redirectTo('../login.php');
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Estatísticas gerais
    $stats = [
        'total_pedidos' => 0,
        'pedidos_pendentes' => 0,
        'total_produtos' => 0,
        'total_usuarios' => 0,
        'faturamento_total' => 0,
        'faturamento_hoje' => 0
    ];

    // Total de pedidos
    $stmt = $conn->query("SELECT COUNT(*) FROM Pedido");
    $stats['total_pedidos'] = $stmt->fetchColumn();

    // Pedidos pendentes
    $stmt = $conn->query("SELECT COUNT(*) FROM Pedido WHERE status IN ('pendente', 'preparando')");
    $stats['pedidos_pendentes'] = $stmt->fetchColumn();

    // Total de produtos
    $stmt = $conn->query("SELECT COUNT(*) FROM Produto");
    $stats['total_produtos'] = $stmt->fetchColumn();

    // Total de usuários
    $stmt = $conn->query("SELECT COUNT(*) FROM Usuario WHERE tipo_usuario = 'consumidor'");
    $stats['total_usuarios'] = $stmt->fetchColumn();

    // Faturamento total
    $stmt = $conn->query("SELECT COALESCE(SUM(total), 0) FROM Pedido WHERE status != 'cancelado'");
    $stats['faturamento_total'] = $stmt->fetchColumn();

    // Faturamento hoje
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(total), 0) 
        FROM Pedido 
        WHERE DATE(data_pedido) = CURDATE() AND status != 'cancelado'
    ");
    $stmt->execute();
    $stats['faturamento_hoje'] = $stmt->fetchColumn();

    // Últimos pedidos
    $stmt = $conn->query("
        SELECT p.id_pedido, u.nome, p.data_pedido, p.status, p.total
        FROM Pedido p
        JOIN Usuario u ON p.id_usuario = u.id_usuario
        ORDER BY p.data_pedido DESC
        LIMIT 5
    ");
    $ultimos_pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Produtos mais vendidos
    $stmt = $conn->query("
        SELECT p.nome, SUM(pi.quantidade) as total_vendido
        FROM Pedido_Item pi
        JOIN Produto p ON pi.id_produto = p.id_produto
        JOIN Pedido pe ON pi.id_pedido = pe.id_pedido
        WHERE pe.status != 'cancelado'
        GROUP BY pi.id_produto
        ORDER BY total_vendido DESC
        LIMIT 5
    ");
    $produtos_populares = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    flashMessage('Erro ao carregar dados: ' . $e->getMessage(), 'error');
}

// Incluir o cabeçalho
require_once '../includes/header.php';
?>

<style>
    .card-stats {
        transition: all 0.3s;
    }
    .card-stats:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .stats-icon {
        font-size: 2rem;
        color: rgba(0,0,0,0.15);
    }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <span class="text-muted"><?php echo date('d/m/Y'); ?></span>
    </div>
</div>

<!-- Stats cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2 card-stats">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Pedidos (Total)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_pedidos']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-receipt stats-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2 card-stats">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Faturamento (Total)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($stats['faturamento_total']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-currency-dollar stats-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2 card-stats">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Pedidos Pendentes</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pedidos_pendentes']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-hourglass-split stats-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2 card-stats">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Faturamento (Hoje)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($stats['faturamento_hoje']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-calendar-check stats-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Content Row -->
<div class="row">
    <!-- Últimos Pedidos -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Últimos Pedidos</h6>
                <a href="pedidos.php" class="btn btn-sm btn-primary">Ver Todos</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimos_pedidos as $pedido): ?>
                                <tr>
                                    <td><?php echo $pedido['id_pedido']; ?></td>
                                    <td><?php echo htmlspecialchars($pedido['nome']); ?></td>
                                    <td><?php echo date('d/m H:i', strtotime($pedido['data_pedido'])); ?></td>
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
                            <?php if (empty($ultimos_pedidos)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">Nenhum pedido encontrado</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Produtos Mais Vendidos -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Produtos Mais Vendidos</h6>
                <?php if ($_SESSION['tipo_usuario'] === 'administrador'): ?>
                <a href="produtos.php" class="btn btn-sm btn-primary">Ver Todos</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Total Vendido</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produtos_populares as $produto): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                                    <td><?php echo $produto['total_vendido']; ?> unidades</td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($produtos_populares)): ?>
                                <tr>
                                    <td colspan="2" class="text-center">Nenhum produto vendido</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>