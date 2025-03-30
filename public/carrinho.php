<?php
// public/carrinho.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    flashMessage('Você precisa fazer login para acessar o carrinho', 'error');
    redirectTo('../login.php');
}

// Processar finalização do pedido
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalizar_pedido'])) {
    $forma_entrega = filter_input(INPUT_POST, 'forma_entrega', FILTER_SANITIZE_STRING);
    $observacoes = filter_input(INPUT_POST, 'observacoes', FILTER_SANITIZE_STRING);
    $metodo_pagamento = filter_input(INPUT_POST, 'metodo_pagamento', FILTER_SANITIZE_STRING);
    $endereco_id = $forma_entrega == 'delivery' ? 
        filter_input(INPUT_POST, 'endereco_id', FILTER_VALIDATE_INT) : 
        null;

    // Verificar se o carrinho não está vazio
    if (empty($_SESSION['carrinho'])) {
        flashMessage('Seu carrinho está vazio', 'error');
        redirectTo('cardapio.php');
    }

    // Validação para delivery
    if ($forma_entrega == 'delivery' && !$endereco_id) {
        flashMessage('Selecione um endereço de entrega', 'error');
        redirectTo('carrinho.php');
    }

    try {
        $database = new Database();
        $conn = $database->getConnection();
        $conn->beginTransaction();

        // Calcular total do pedido
        $total = 0;
        foreach ($_SESSION['carrinho'] as $item) {
            $total += $item['preco'] * $item['quantidade'];
        }

        // Inserir pedido
        $stmt = $conn->prepare("
            INSERT INTO Pedido (
                id_usuario, status, forma_entrega, 
                id_endereco, total, observacoes
            ) VALUES (
                :usuario_id, 'pendente', :forma_entrega, 
                :endereco_id, :total, :observacoes
            )
        ");
        $stmt->bindParam(':usuario_id', $_SESSION['usuario_id']);
        $stmt->bindParam(':forma_entrega', $forma_entrega);
        $stmt->bindParam(':endereco_id', $endereco_id);
        $stmt->bindParam(':total', $total);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->execute();
        $pedido_id = $conn->lastInsertId();

        // Inserir itens do pedido
        $stmt_item = $conn->prepare("
            INSERT INTO Pedido_Item (
                id_pedido, id_produto, quantidade, 
                preco_unitario, subtotal
            ) VALUES (
                :pedido_id, :produto_id, :quantidade, 
                :preco_unitario, :subtotal
            )
        ");

        foreach ($_SESSION['carrinho'] as $item) {
            $stmt_item->bindValue(':pedido_id', $pedido_id);
            $stmt_item->bindValue(':produto_id', $item['id_produto']);
            $stmt_item->bindValue(':quantidade', $item['quantidade']);
            $stmt_item->bindValue(':preco_unitario', $item['preco']);
            $subtotal = $item['preco'] * $item['quantidade'];
            $stmt_item->bindValue(':subtotal', $subtotal);
            $stmt_item->execute();
        }

        // Inserir pagamento
        $stmt_pagamento = $conn->prepare("
            INSERT INTO Pagamento (
                id_pedido, metodo, status, valor_pago
            ) VALUES (
                :pedido_id, :metodo, 'pendente', :valor
            )
        ");
        $stmt_pagamento->bindParam(':pedido_id', $pedido_id);
        $stmt_pagamento->bindParam(':metodo', $metodo_pagamento);
        $stmt_pagamento->bindParam(':valor', $total);
        $stmt_pagamento->execute();

        $conn->commit();

        // Limpar carrinho - aqui resetamos completamente o carrinho
        if (isset($_SESSION['carrinho'])) {
            unset($_SESSION['carrinho']);
        }

        flashMessage('Pedido realizado com sucesso! Número do pedido: #' . $pedido_id, 'success');
        redirectTo('meus-pedidos.php');
    } catch (Exception $e) {
        $conn->rollBack();
        flashMessage('Erro ao finalizar pedido: ' . $e->getMessage(), 'error');
    }
}

// Processar remoção de item
if (isset($_GET['remover']) && is_numeric($_GET['remover'])) {
    $idRemover = (int)$_GET['remover'];
    
    if (isset($_SESSION['carrinho'])) {
        foreach ($_SESSION['carrinho'] as $indice => $item) {
            if ($item['id_produto'] == $idRemover) {
                unset($_SESSION['carrinho'][$indice]);
                // Reindexar o array
                $_SESSION['carrinho'] = array_values($_SESSION['carrinho']);
                flashMessage('Produto removido do carrinho', 'success');
                break;
            }
        }
    }
    redirectTo('carrinho.php');
}

// Buscar endereços do usuário
try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        SELECT id_endereco, rua, numero, complemento, bairro, cidade, estado, cep 
        FROM Endereco 
        WHERE id_usuario = :usuario_id
    ");
    $stmt->bindParam(':usuario_id', $_SESSION['usuario_id']);
    $stmt->execute();
    $enderecos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    flashMessage('Erro ao carregar endereços: ' . $e->getMessage(), 'error');
    $enderecos = [];
}

// Calcular total do carrinho
$total = 0;
$carrinho = $_SESSION['carrinho'] ?? [];

foreach ($carrinho as $item) {
    $subtotal = $item['preco'] * $item['quantidade'];
    $total += $subtotal;
}

require_once '../includes/header.php';
?>

<style>
    .cart-item-img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 5px;
    }
    
    .cart-summary {
        position: sticky;
        top: 85px;
    }
    
    .quantity-input {
        width: 70px;
    }
    
    .empty-cart-icon {
        font-size: 5rem;
        color: #dee2e6;
    }
    
    .entrega-info {
        display: none;
    }
    
    @media (max-width: 768px) {
        .cart-item-img {
            width: 60px;
            height: 60px;
        }
    }
</style>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-cart"></i> Meu Carrinho 
                    <?php if (!empty($carrinho)): ?>
                        <span class="badge bg-primary rounded-pill"><?php echo count($carrinho); ?></span>
                    <?php endif; ?>
                </h5>
                <a href="cardapio.php" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Continuar Comprando
                </a>
            </div>
            
            <div class="card-body">
                <?php displayFlashMessage(); ?>
                
                <?php if (empty($carrinho)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-cart-x empty-cart-icon"></i>
                        <h4 class="mt-3">Seu carrinho está vazio</h4>
                        <p class="text-muted">Adicione itens do nosso cardápio para começar seu pedido</p>
                        <a href="cardapio.php" class="btn btn-primary mt-3">
                            <i class="bi bi-arrow-left"></i> Ver Cardápio
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Preço</th>
                                    <th>Quantidade</th>
                                    <th>Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($carrinho as $item): ?>
                                    <?php 
                                        $subtotal = $item['preco'] * $item['quantidade'];
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($item['imagem_url'] ?? '')): ?>
                                                    <img src="<?php echo htmlspecialchars($item['imagem_url']); ?>" 
                                                         class="cart-item-img me-3" 
                                                         alt="<?php echo htmlspecialchars($item['nome']); ?>">
                                                <?php else: ?>
                                                    <div class="cart-item-img me-3 bg-light d-flex align-items-center justify-content-center">
                                                        <i class="bi bi-image text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($item['nome']); ?></h6>
                                                    <small class="text-muted">Código: #<?php echo $item['id_produto']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo formatCurrency($item['preco']); ?></td>
                                        <td>
                                            <form action="atualizar-carrinho.php" method="POST" class="d-inline">
                                                <input type="hidden" name="id_produto" value="<?php echo $item['id_produto']; ?>">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" name="quantidade" 
                                                           class="form-control quantity-input" 
                                                           value="<?php echo $item['quantidade']; ?>" 
                                                           min="1" max="10">
                                                    <button type="submit" class="btn btn-outline-secondary">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                </div>
                                            </form>
                                        </td>
                                        <td>
                                            <span class="fw-bold"><?php echo formatCurrency($subtotal); ?></span>
                                        </td>
                                        <td>
                                            <a href="carrinho.php?remover=<?php echo $item['id_produto']; ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Tem certeza que deseja remover este item?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <h5>Detalhes do Pedido</h5>
                        <form method="POST" id="finalizar-form">
                            <input type="hidden" name="finalizar_pedido" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label">Forma de Entrega</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" 
                                           name="forma_entrega" id="retirada" 
                                           value="retirada" required checked>
                                    <label class="form-check-label" for="retirada">
                                        Retirada no Local
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" 
                                           name="forma_entrega" id="delivery" 
                                           value="delivery" required>
                                    <label class="form-check-label" for="delivery">
                                        Entrega em Domicílio
                                    </label>
                                </div>
                            </div>

                            <div id="endereco-section" class="entrega-info mb-3">
                                <label class="form-label">Selecione o Endereço</label>
                                <?php if (empty($enderecos)): ?>
                                    <div class="alert alert-warning">
                                        Você não possui endereços cadastrados. 
                                        <a href="../perfil.php?adicionar_endereco=1" target="_blank">
                                            Adicionar Endereço
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <select name="endereco_id" class="form-select">
                                        <?php foreach ($enderecos as $endereco): ?>
                                            <option value="<?php echo $endereco['id_endereco']; ?>">
                                                <?php echo htmlspecialchars(
                                                    sprintf("%s, %s - %s, %s - %s", 
                                                        $endereco['rua'], 
                                                        $endereco['numero'], 
                                                        $endereco['bairro'], 
                                                        $endereco['cidade'], 
                                                        $endereco['estado']
                                                    )
                                                ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Método de Pagamento</label>
                                <select name="metodo_pagamento" class="form-select" required>
                                    <option value="pix">PIX</option>
                                    <option value="cartao">Cartão</option>
                                    <option value="dinheiro">Dinheiro</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="observacoes" class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="3" 
                                          placeholder="Alguma observação sobre o pedido?"></textarea>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <?php if (!empty($carrinho)): ?>
            <div class="card shadow-sm cart-summary">
                <div class="card-header">
                    <h5 class="card-title mb-0">Resumo do Pedido</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <span><?php echo formatCurrency($total); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Taxa de entrega</span>
                        <span id="taxa-entrega">Grátis</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="fw-bold">Total</span>
                        <span class="fw-bold text-success fs-4" id="valor-total"><?php echo formatCurrency($total); ?></span>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" form="finalizar-form" class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i> Finalizar Pedido
                        </button>
                        <a href="cardapio.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Continuar Comprando
                        </a>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <div class="small text-muted">
                        <i class="bi bi-shield-check"></i> Pagamento seguro
                    </div>
                    <div class="small text-muted mt-1">
                        <i class="bi bi-truck"></i> Entrega rápida e segura
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const retiradaRadio = document.getElementById('retirada');
        const deliveryRadio = document.getElementById('delivery');
        const enderecoSection = document.getElementById('endereco-section');

        function toggleEnderecoSection() {
            if (deliveryRadio.checked) {
                enderecoSection.style.display = 'block';
                // Se implementar taxa de entrega, atualizar aqui
            } else {
                enderecoSection.style.display = 'none';
            }
        }

        retiradaRadio.addEventListener('change', toggleEnderecoSection);
        deliveryRadio.addEventListener('change', toggleEnderecoSection);

        // Estado inicial
        toggleEnderecoSection();
    });
</script>

<?php require_once '../includes/footer.php'; ?>