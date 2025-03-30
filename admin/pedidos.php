<?php
// admin/pedidos.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar permissão (administrador ou operador)
if (!isLoggedIn() || ($_SESSION['tipo_usuario'] !== 'administrador' && $_SESSION['tipo_usuario'] !== 'operador')) {
    flashMessage('Acesso não autorizado', 'error');
    redirectTo('../login.php');
}

$database = new Database();
$conn = $database->getConnection();

// Processar atualização de status de pedido
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar_status'])) {
    $id_pedido = filter_input(INPUT_POST, 'id_pedido', FILTER_VALIDATE_INT);
    $novo_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    
    if ($id_pedido && in_array($novo_status, ['pendente', 'preparando', 'pronto', 'enviado', 'entregue', 'cancelado'])) {
        try {
            $conn->beginTransaction();
            
            // Atualizar status
            $stmt = $conn->prepare("UPDATE Pedido SET status = :status WHERE id_pedido = :id");
            $stmt->bindParam(':status', $novo_status);
            $stmt->bindParam(':id', $id_pedido);
            $stmt->execute();
            
            // Atualizar campos de data conforme o status
            switch ($novo_status) {
                case 'preparando':
                    $stmt = $conn->prepare("UPDATE Pedido SET hora_preparacao = NOW() WHERE id_pedido = :id AND hora_preparacao IS NULL");
                    break;
                case 'pronto':
                    $stmt = $conn->prepare("UPDATE Pedido SET hora_finalizacao = NOW() WHERE id_pedido = :id AND hora_finalizacao IS NULL");
                    break;
                case 'enviado':
                    $stmt = $conn->prepare("UPDATE Pedido SET hora_envio = NOW() WHERE id_pedido = :id AND hora_envio IS NULL");
                    break;
                case 'entregue':
                    $stmt = $conn->prepare("UPDATE Pedido SET hora_entrega = NOW() WHERE id_pedido = :id AND hora_entrega IS NULL");
                    break;
            }
            
            if (isset($stmt)) {
                $stmt->bindParam(':id', $id_pedido);
                $stmt->execute();
            }
            
            $conn->commit();
            flashMessage('Status do pedido atualizado com sucesso', 'success');
        } catch (Exception $e) {
            $conn->rollBack();
            flashMessage('Erro ao atualizar status: ' . $e->getMessage(), 'error');
        }
    } else {
        flashMessage('Dados inválidos para atualização', 'error');
    }
    
    // Redirecionar para evitar reenvio do formulário
    redirectTo('pedidos.php' . (isset($_GET['id']) ? '?id=' . $_GET['id'] : ''));
}

// Processar atualização de status de pagamento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar_pagamento'])) {
    $id_pagamento = filter_input(INPUT_POST, 'id_pagamento', FILTER_VALIDATE_INT);
    $novo_status = filter_input(INPUT_POST, 'status_pagamento', FILTER_SANITIZE_STRING);
    
    if ($id_pagamento && in_array($novo_status, ['pendente', 'aprovado', 'recusado', 'estornado'])) {
        try {
            $stmt = $conn->prepare("
                UPDATE Pagamento 
                SET status = :status, data_pagamento = :data_pagamento 
                WHERE id_pagamento = :id
            ");
            $stmt->bindParam(':status', $novo_status);
            $data_pagamento = $novo_status == 'aprovado' ? date('Y-m-d H:i:s') : null;
            $stmt->bindParam(':data_pagamento', $data_pagamento);
            $stmt->bindParam(':id', $id_pagamento);
            $stmt->execute();
            
            flashMessage('Status do pagamento atualizado com sucesso', 'success');
        } catch (Exception $e) {
            flashMessage('Erro ao atualizar pagamento: ' . $e->getMessage(), 'error');
        }
    }
    
    // Redirecionar para evitar reenvio do formulário
    redirectTo('pedidos.php' . (isset($_GET['id']) ? '?id=' . $_GET['id'] : ''));
}

// Exibir detalhes de um pedido específico
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_pedido = (int)$_GET['id'];
    
    try {
        // Buscar detalhes do pedido
        $stmt = $conn->prepare("
            SELECT 
                p.*, 
                u.nome as cliente_nome,
                u.email as cliente_email,
                pg.id_pagamento,
                pg.metodo as forma_pagamento,
                pg.status as status_pagamento,
                pg.valor_pago,
                pg.data_pagamento,
                e.rua, e.numero, e.complemento, e.bairro, e.cidade, e.estado, e.cep
            FROM Pedido p
            JOIN Usuario u ON p.id_usuario = u.id_usuario
            LEFT JOIN Pagamento pg ON p.id_pedido = pg.id_pedido
            LEFT JOIN Endereco e ON p.id_endereco = e.id_endereco
            WHERE p.id_pedido = :id
        ");
        $stmt->bindParam(':id', $id_pedido);
        $stmt->execute();
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pedido) {
            flashMessage('Pedido não encontrado', 'error');
            redirectTo('pedidos.php');
        }
        
        // Buscar itens do pedido
        $stmt = $conn->prepare("
            SELECT 
                pi.*, 
                p.nome as produto_nome
            FROM Pedido_Item pi
            JOIN Produto p ON pi.id_produto = p.id_produto
            WHERE pi.id_pedido = :id
        ");
        $stmt->bindParam(':id', $id_pedido);
        $stmt->execute();
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        flashMessage('Erro ao carregar detalhes do pedido: ' . $e->getMessage(), 'error');
        redirectTo('pedidos.php');
    }
} else {
    // Listar todos os pedidos
    try {
        // Filtros
        $filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
        $filtro_data = isset($_GET['data']) ? $_GET['data'] : '';
        
        $where_clauses = [];
        $params = [];
        
        if (!empty($filtro_status)) {
            $where_clauses[] = "p.status = :status";
            $params[':status'] = $filtro_status;
        }
        
        if (!empty($filtro_data)) {
            $where_clauses[] = "DATE(p.data_pedido) = :data";
            $params[':data'] = $filtro_data;
        }
        
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(' AND ', $where_clauses) : "";
        
        $sql = "
            SELECT 
                p.id_pedido, 
                p.data_pedido, 
                p.status, 
                p.total, 
                p.forma_entrega,
                u.nome as cliente_nome,
                pg.status as status_pagamento
            FROM Pedido p
            JOIN Usuario u ON p.id_usuario = u.id_usuario
            LEFT JOIN Pagamento pg ON p.id_pedido = pg.id_pedido
            $where_sql
            ORDER BY p.data_pedido DESC
        ";
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        flashMessage('Erro ao listar pedidos: ' . $e->getMessage(), 'error');
        $pedidos = [];
    }
}

// Incluir o cabeçalho
require_once '../includes/header.php';
?>

<style>
.timeline {
    list-style-type: none;
    position: relative;
    padding-left: 30px;
    margin-left: 10px;
}
.timeline:before {
    content: ' ';
    background: #d4d9df;
    display: inline-block;
    position: absolute;
    left: 11px;
    width: 2px;
    height: 100%;
    z-index: 400;
}
.timeline-item {
    margin: 20px 0;
}
.timeline-marker {
    position: absolute;
    left: 1px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #4e73df;
    margin-top: 3px;
    margin-left: -11px;
}
.timeline-content {
    padding-bottom: 10px;
}
.timeline-title {
    margin-bottom: 0;
}
.timeline-date {
    color: #6c757d;
    font-size: 0.85em;
    margin-top: 0;
}
.filter-card {
    transition: all 0.3s ease;
}
.filter-card:focus-within {
    box-shadow: 0 0 15px rgba(0,0,0,0.1);
}
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <?php echo isset($pedido) ? 'Detalhes do Pedido #' . $pedido['id_pedido'] : 'Gerenciar Pedidos'; ?>
    </h1>
    <?php if (isset($pedido)): ?>
        <a href="pedidos.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar para Lista
        </a>
    <?php endif; ?>
</div>

<?php if (isset($pedido)): /* Detalhes do pedido */ ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Detalhes do Pedido</h6>
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
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Cliente:</strong> <?php echo htmlspecialchars($pedido['cliente_nome']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($pedido['cliente_email']); ?></p>
                            <p><strong>Data do Pedido:</strong> <?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></p>
                            <p><strong>Forma de Entrega:</strong> <?php echo ucfirst($pedido['forma_entrega']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Total:</strong> <?php echo formatCurrency($pedido['total']); ?></p>
                            <p>
                                <strong>Pagamento:</strong> 
                                <?php echo strtoupper($pedido['forma_pagamento']); ?>
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
                            <?php if ($pedido['data_pagamento']): ?>
                                <p><strong>Data do Pagamento:</strong> <?php echo date('d/m/Y H:i', strtotime($pedido['data_pagamento'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($pedido['forma_entrega'] == 'delivery' && isset($pedido['rua'])): ?>
                        <div class="mb-3">
                            <h6>Endereço de Entrega</h6>
                            <p>
                                <?php 
                                echo htmlspecialchars(sprintf(
                                    "%s, %s %s - %s, %s/%s - CEP: %s", 
                                    $pedido['rua'], 
                                    $pedido['numero'], 
                                    $pedido['complemento'] ? '- ' . $pedido['complemento'] : '', 
                                    $pedido['bairro'], 
                                    $pedido['cidade'], 
                                    $pedido['estado'],
                                    $pedido['cep']
                                )); 
                                ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($pedido['observacoes'])): ?>
                        <div class="mb-3">
                            <h6>Observações</h6>
                            <p><?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <h6>Itens do Pedido</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Preço Unit.</th>
                                    <th>Quantidade</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itens as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['produto_nome']); ?></td>
                                        <td><?php echo formatCurrency($item['preco_unitario']); ?></td>
                                        <td><?php echo $item['quantidade']; ?></td>
                                        <td><?php echo formatCurrency($item['subtotal']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-dark">
                                    <td colspan="3" class="text-end">Total:</td>
                                    <td><?php echo formatCurrency($pedido['total']); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Ações</h6>
                </div>
                <div class="card-body">
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="id_pedido" value="<?php echo $pedido['id_pedido']; ?>">
                        <div class="mb-3">
                            <label for="status" class="form-label">Atualizar Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="pendente" <?php echo $pedido['status'] == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                <option value="preparando" <?php echo $pedido['status'] == 'preparando' ? 'selected' : ''; ?>>Preparando</option>
                                <option value="pronto" <?php echo $pedido['status'] == 'pronto' ? 'selected' : ''; ?>>Pronto</option>
                                <option value="enviado" <?php echo $pedido['status'] == 'enviado' ? 'selected' : ''; ?>>Enviado</option>
                                <option value="entregue" <?php echo $pedido['status'] == 'entregue' ? 'selected' : ''; ?>>Entregue</option>
                                <option value="cancelado" <?php echo $pedido['status'] == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                        </div>
                        <button type="submit" name="atualizar_status" class="btn btn-primary">
                            Atualizar Status
                        </button>
                    </form>

                    <hr>
                    
                    <form method="POST">
                        <input type="hidden" name="id_pagamento" value="<?php echo $pedido['id_pagamento']; ?>">
                        <div class="mb-3">
                            <label for="status_pagamento" class="form-label">Status do Pagamento</label>
                            <select class="form-select" id="status_pagamento" name="status_pagamento">
                                <option value="pendente" <?php echo $pedido['status_pagamento'] == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                <option value="aprovado" <?php echo $pedido['status_pagamento'] == 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                                <option value="recusado" <?php echo $pedido['status_pagamento'] == 'recusado' ? 'selected' : ''; ?>>Recusado</option>
                                <option value="estornado" <?php echo $pedido['status_pagamento'] == 'estornado' ? 'selected' : ''; ?>>Estornado</option>
                            </select>
                        </div>
                        <button type="submit" name="atualizar_pagamento" class="btn btn-primary">
                            Atualizar Pagamento
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Timeline</h6>
                </div>
                <div class="card-body">
                    <ul class="timeline">
                        <li class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title">Pedido Recebido</h6>
                                <p class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></p>
                            </div>
                        </li>
                        
                        <?php if ($pedido['hora_preparacao']): ?>
                        <li class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title">Em Preparação</h6>
                                <p class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($pedido['hora_preparacao'])); ?></p>
                            </div>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($pedido['hora_finalizacao']): ?>
                        <li class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title">Pronto</h6>
                                <p class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($pedido['hora_finalizacao'])); ?></p>
                            </div>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($pedido['hora_envio']): ?>
                        <li class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title">Enviado</h6>
                                <p class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($pedido['hora_envio'])); ?></p>
                            </div>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($pedido['hora_entrega']): ?>
                        <li class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title">Entregue</h6>
                                <p class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($pedido['hora_entrega'])); ?></p>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

<?php else: /* Lista de pedidos */ ?>
    <!-- Filtros -->
    <div class="card shadow mb-4 filter-card">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filtros</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="pendente" <?php echo isset($_GET['status']) && $_GET['status'] === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="preparando" <?php echo isset($_GET['status']) && $_GET['status'] === 'preparando' ? 'selected' : ''; ?>>Preparando</option>
                        <option value="pronto" <?php echo isset($_GET['status']) && $_GET['status'] === 'pronto' ? 'selected' : ''; ?>>Pronto</option>
                        <option value="enviado" <?php echo isset($_GET['status']) && $_GET['status'] === 'enviado' ? 'selected' : ''; ?>>Enviado</option>
                        <option value="entregue" <?php echo isset($_GET['status']) && $_GET['status'] === 'entregue' ? 'selected' : ''; ?>>Entregue</option>
                        <option value="cancelado" <?php echo isset($_GET['status']) && $_GET['status'] === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="data" class="form-label">Data</label>
                    <input type="date" class="form-control" id="data" name="data" value="<?php echo isset($_GET['data']) ? $_GET['data'] : ''; ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filtrar</button>
                    <a href="pedidos.php" class="btn btn-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de Pedidos -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lista de Pedidos</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Data/Hora</th>
                            <th>Total</th>
                            <th>Forma de Entrega</th>
                            <th>Status</th>
                            <th>Pagamento</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $pedido_item): ?>
                            <tr>
                                <td><?php echo $pedido_item['id_pedido']; ?></td>
                                <td><?php echo htmlspecialchars($pedido_item['cliente_nome']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($pedido_item['data_pedido'])); ?></td>
                                <td><?php echo formatCurrency($pedido_item['total']); ?></td>
                                <td><?php echo ucfirst($pedido_item['forma_entrega']); ?></td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                        echo match($pedido_item['status']) {
                                            'pendente' => 'bg-warning',
                                            'preparando' => 'bg-info',
                                            'pronto' => 'bg-primary',
                                            'enviado' => 'bg-secondary',
                                            'entregue' => 'bg-success',
                                            'cancelado' => 'bg-danger',
                                            default => 'bg-light text-dark'
                                        };
                                        ?>">
                                        <?php echo ucfirst($pedido_item['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                        echo match($pedido_item['status_pagamento']) {
                                            'pendente' => 'bg-warning',
                                            'aprovado' => 'bg-success',
                                            'recusado' => 'bg-danger',
                                            'estornado' => 'bg-secondary',
                                            default => 'bg-light text-dark'
                                        };
                                        ?>">
                                        <?php echo ucfirst($pedido_item['status_pagamento']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="pedidos.php?id=<?php echo $pedido_item['id_pedido']; ?>" 
                                       class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> Detalhes
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pedidos)): ?>
                            <tr>
                                <td colspan="8" class="text-center">Nenhum pedido encontrado</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>