<?php
// public/meus-pedidos.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    flashMessage('Você precisa fazer login para ver seus pedidos', 'error');
    redirectTo('../login.php');
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Buscar pedidos do usuário com detalhes
    $stmt = $conn->prepare("
        SELECT 
            p.id_pedido, 
            p.data_pedido, 
            p.status, 
            p.total, 
            p.forma_entrega,
            pg.metodo AS metodo_pagamento,
            pg.status AS status_pagamento
        FROM Pedido p
        LEFT JOIN Pagamento pg ON p.id_pedido = pg.id_pedido
        WHERE p.id_usuario = :usuario_id
        ORDER BY p.data_pedido DESC
    ");
    $stmt->bindParam(':usuario_id', $_SESSION['usuario_id']);
    $stmt->execute();
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Função para buscar itens de cada pedido
    function buscarItensPedido($conn, $pedido_id) {
        $stmt = $conn->prepare("
            SELECT 
                p.nome, 
                pi.quantidade, 
                pi.preco_unitario,
                pi.subtotal
            FROM Pedido_Item pi
            JOIN Produto p ON pi.id_produto = p.id_produto
            WHERE pi.id_pedido = :pedido_id
        ");
        $stmt->bindParam(':pedido_id', $pedido_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    flashMessage('Erro ao carregar pedidos: ' . $e->getMessage(), 'error');
    $pedidos = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Meus Pedidos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Meus Pedidos</h1>
        
        <?php displayFlashMessage(); ?>

        <?php if (empty($pedidos)): ?>
            <div class="alert alert-info">
                Você ainda não realizou nenhum pedido. 
                <a href="cardapio.php">Faça seu primeiro pedido!</a>
            </div>
        <?php else: ?>
            <?php foreach ($pedidos as $pedido): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Pedido #<?php echo $pedido['id_pedido']; ?></strong>
                            <span class="ms-2 badge 
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
                        </div>
                        <small class="text-muted">
                            <?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5>Itens do Pedido</h5>
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Produto</th>
                                            <th>Quantidade</th>
                                            <th>Preço Unitário</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $itens = buscarItensPedido($conn, $pedido['id_pedido']);
                                        foreach ($itens as $item): 
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['nome']); ?></td>
                                                <td><?php echo $item['quantidade']; ?></td>
                                                <td><?php echo formatCurrency($item['preco_unitario']); ?></td>
                                                <td><?php echo formatCurrency($item['subtotal']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-4">
                                <h5>Detalhes</h5>
                                <p>
                                    <strong>Forma de Entrega:</strong> 
                                    <?php echo ucfirst($pedido['forma_entrega']); ?>
                                </p>
                                
                                <p>
                                    <strong>Pagamento:</strong> 
                                    <?php echo strtoupper($pedido['metodo_pagamento']); ?> 
                                    <span class="badge 
                                        <?php 
                                        echo match($pedido['status_pagamento']) {
                                            'pendente' => 'bg-warning',
                                            'aprovado' => 'bg-success',
                                            'recusado' => 'bg-danger',
                                            'estornado' => 'bg-secondary',
                                            default => 'bg-light text-dark'
                                        };
                                        ?>">
                                        <?php echo ucfirst($pedido['status_pagamento']); ?>
                                    </span>
                                </p>
                                <p class="h4 text-success">
                                    Total: <?php echo formatCurrency($pedido['total']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>