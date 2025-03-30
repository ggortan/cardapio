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

require_once '../includes/header.php';
?>

<style>
    .status-badge {
        min-width: 80px;
    }
    .pedido-card {
        transition: transform 0.2s ease;
    }
    .pedido-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .detalhe-hidden {
        display: none;
    }
</style>

<h1>Meus Pedidos</h1>

<?php if (empty($pedidos)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Você ainda não realizou nenhum pedido. 
        <a href="cardapio.php" class="alert-link">Faça seu primeiro pedido!</a>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 g-4 mb-4">
        <?php foreach ($pedidos as $pedido): ?>
            <div class="col">
                <div class="card pedido-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Pedido #<?php echo $pedido['id_pedido']; ?></h5>
                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></small>
                        </div>
                        <span class="badge status-badge 
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
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <p class="mb-1"><strong>Forma de Entrega:</strong> <?php echo ucfirst($pedido['forma_entrega']); ?></p>
                                <p class="mb-0">
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
                            </div>
                            <div class="text-end">
                                <h5 class="text-success mb-0"><?php echo formatCurrency($pedido['total']); ?></h5>
                            </div>
                        </div>
                        
                        <button class="btn btn-sm btn-outline-primary toggle-details" data-pedido="<?php echo $pedido['id_pedido']; ?>">
                            <i class="bi bi-list-ul"></i> Ver Itens
                        </button>
                        
                        <div class="itens-pedido detalhe-hidden mt-3" id="itens-<?php echo $pedido['id_pedido']; ?>">
                            <h6>Itens do Pedido</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Produto</th>
                                            <th>Qtd</th>
                                            <th>Preço</th>
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
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted"><i class="bi bi-clock-history"></i> Status atualizado com o progresso do pedido</small>
                            <?php if ($pedido['status'] == 'pendente'): ?>
                                <button class="btn btn-sm btn-outline-danger" disabled>
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Script para limpar o carrinho se o usuário acabou de finalizar um pedido -->
<script>
// Função para limpar completamente o carrinho após finalização do pedido
function resetCartAfterOrder() {
    try {
        // Limpar diretamente o localStorage
        if (typeof localStorage !== 'undefined') {
            localStorage.removeItem('dynamicCart');
        }
        
        // Limpar variáveis globais se existirem
        if (typeof DynamicCart !== 'undefined') {
            DynamicCart.items = [];
            DynamicCart.count = 0;
            DynamicCart.updateCartIcon();
        }
        
        console.log('Carrinho limpo com sucesso após finalização do pedido!');
    } catch (e) {
        console.error('Erro ao limpar carrinho:', e);
    }
}

// Executar imediatamente para garantir que o localStorage seja limpo
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se há uma mensagem de sucesso de pedido na página
    const flashMessages = document.querySelectorAll('.alert-success');
    let orderCompleted = false;
    
    // Verificar se alguma mensagem indica finalização de pedido
    flashMessages.forEach(message => {
        if (message.textContent.includes('Pedido realizado com sucesso')) {
            orderCompleted = true;
        }
    });
    
    // Se o pedido foi concluído ou se chegamos a esta página após um redirecionamento do carrinho
    if (orderCompleted || document.referrer.includes('carrinho.php')) {
        resetCartAfterOrder();
    }

    // Configurar os botões de toggle para os detalhes do pedido
    const toggleButtons = document.querySelectorAll('.toggle-details');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const pedidoId = this.getAttribute('data-pedido');
            const detalhesDiv = document.getElementById('itens-' + pedidoId);
            
            if (detalhesDiv.classList.contains('detalhe-hidden')) {
                detalhesDiv.classList.remove('detalhe-hidden');
                this.innerHTML = '<i class="bi bi-x-lg"></i> Ocultar Itens';
            } else {
                detalhesDiv.classList.add('detalhe-hidden');
                this.innerHTML = '<i class="bi bi-list-ul"></i> Ver Itens';
            }
        });
    });
});

// Limpar o carrinho imediatamente (para garantir)
resetCartAfterOrder();
</script>

<?php require_once '../includes/footer.php'; ?>