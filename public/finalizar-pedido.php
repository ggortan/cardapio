<?php
// public/finalizar-pedido.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    flashMessage('Você precisa fazer login para finalizar o pedido', 'error');
    redirectTo('../login.php');
}

// Verificar se carrinho não está vazio
if (empty($_SESSION['carrinho'])) {
    flashMessage('Seu carrinho está vazio', 'error');
    redirectTo('cardapio.php');
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Buscar endereços do usuário
    $stmt = $conn->prepare("
        SELECT id_endereco, rua, numero, complemento, bairro, cidade, estado, cep 
        FROM Endereco 
        WHERE id_usuario = :usuario_id
    ");
    $stmt->bindParam(':usuario_id', $_SESSION['usuario_id']);
    $stmt->execute();
    $enderecos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular total do pedido
    $total = 0;
    foreach ($_SESSION['carrinho'] as $item) {
        $total += $item['preco'] * $item['quantidade'];
    }
} catch (Exception $e) {
    flashMessage('Erro ao carregar dados: ' . $e->getMessage(), 'error');
    $enderecos = [];
}

// Processar finalização do pedido
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $forma_entrega = filter_input(INPUT_POST, 'forma_entrega', FILTER_SANITIZE_STRING);
    $observacoes = filter_input(INPUT_POST, 'observacoes', FILTER_SANITIZE_STRING);
    $metodo_pagamento = filter_input(INPUT_POST, 'metodo_pagamento', FILTER_SANITIZE_STRING);
    $endereco_id = $forma_entrega == 'delivery' ? 
        filter_input(INPUT_POST, 'endereco_id', FILTER_VALIDATE_INT) : 
        null;

    try {
        $conn->beginTransaction();

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

        // Limpar carrinho
        unset($_SESSION['carrinho']);

        flashMessage('Pedido realizado com sucesso! Número do pedido: #' . $pedido_id, 'success');
        redirectTo('meus-pedidos.php');
    } catch (Exception $e) {
        $conn->rollBack();
        flashMessage('Erro ao finalizar pedido: ' . $e->getMessage(), 'error');
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Finalizar Pedido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Finalizar Pedido</h1>
        
        <?php displayFlashMessage(); ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Detalhes do Pedido</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Forma de Entrega</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" 
                                           name="forma_entrega" id="retirada" 
                                           value="retirada" required>
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

                            <div id="endereco-section" style="display:none;">
                                <div class="mb-3">
                                    <label class="form-label">Selecione o Endereço</label>
                                    <?php if (empty($enderecos)): ?>
                                        <div class="alert alert-warning">
                                            Você não possui endereços cadastrados. 
                                            <a href="../perfil.php?adicionar_endereco=1">
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

                            <button type="submit" class="btn btn-primary">
                                Confirmar Pedido
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Resumo do Pedido</h5>
                        <table class="table">
                            <?php 
                            $total = 0;
                            foreach ($_SESSION['carrinho'] as $item): 
                                $subtotal = $item['preco'] * $item['quantidade'];
                                $total += $subtotal;
                            ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($item['nome']); ?>
                                        <small class="text-muted">x<?php echo $item['quantidade']; ?></small>
                                    </td>
                                    <td class="text-end"><?php echo formatCurrency($subtotal); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-active">
                                <td class="fw-bold">Total</td>
                                <td class="text-end text-success fw-bold">
                                    <?php echo formatCurrency($total); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const retiradaRadio = document.getElementById('retirada');
            const deliveryRadio = document.getElementById('delivery');
            const enderecoSection = document.getElementById('endereco-section');

            function toggleEnderecoSection() {
                enderecoSection.style.display = 
                    deliveryRadio.checked ? 'block' : 'none';
            }

            retiradaRadio.addEventListener('change', toggleEnderecoSection);
            deliveryRadio.addEventListener('change', toggleEnderecoSection);

            // Initial state
            toggleEnderecoSection();
        });
    </script>
</body>
</html>