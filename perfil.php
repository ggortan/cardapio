<?php
// perfil.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    flashMessage('Você precisa fazer login para acessar seu perfil', 'error');
    redirectTo('login.php');
}

$database = new Database();
$conn = $database->getConnection();

// Processar exclusão de endereço
if (isset($_GET['excluir_endereco']) && is_numeric($_GET['excluir_endereco'])) {
    $id_endereco = (int)$_GET['excluir_endereco'];
    
    try {
        // Verificar se o endereço pertence ao usuário logado
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM Endereco 
            WHERE id_endereco = :id_endereco AND id_usuario = :id_usuario
        ");
        $stmt->bindParam(':id_endereco', $id_endereco);
        $stmt->bindParam(':id_usuario', $_SESSION['usuario_id']);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            // Verificar se há pedidos usando este endereço
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM Pedido 
                WHERE id_endereco = :id_endereco
            ");
            $stmt->bindParam(':id_endereco', $id_endereco);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                flashMessage('Não é possível excluir este endereço pois existem pedidos associados a ele', 'error');
            } else {
                $stmt = $conn->prepare("DELETE FROM Endereco WHERE id_endereco = :id_endereco");
                $stmt->bindParam(':id_endereco', $id_endereco);
                $stmt->execute();
                flashMessage('Endereço excluído com sucesso', 'success');
            }
        } else {
            flashMessage('Endereço não encontrado', 'error');
        }
    } catch (Exception $e) {
        flashMessage('Erro ao excluir endereço: ' . $e->getMessage(), 'error');
    }
    
    redirectTo('perfil.php');
}

// Processar formulário de endereço
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_endereco'])) {
    $id_endereco = filter_input(INPUT_POST, 'id_endereco', FILTER_VALIDATE_INT);
    $rua = sanitizeInput($_POST['rua']);
    $numero = sanitizeInput($_POST['numero']);
    $complemento = sanitizeInput($_POST['complemento'] ?? '');
    $bairro = sanitizeInput($_POST['bairro']);
    $cidade = sanitizeInput($_POST['cidade']);
    $estado = sanitizeInput($_POST['estado']);
    $cep = sanitizeInput($_POST['cep']);
    
    // Validações básicas
    $errors = [];
    if (empty($rua)) $errors[] = "Rua é obrigatória";
    if (empty($numero)) $errors[] = "Número é obrigatório";
    if (empty($bairro)) $errors[] = "Bairro é obrigatório";
    if (empty($cidade)) $errors[] = "Cidade é obrigatória";
    if (empty($estado) || strlen($estado) != 2) $errors[] = "Estado inválido";
    if (empty($cep) || !preg_match('/^\d{5}-?\d{3}$/', $cep)) $errors[] = "CEP inválido";
    
    if (empty($errors)) {
        try {
            // Padronizar formato do CEP
            $cep = preg_replace('/[^0-9]/', '', $cep);
            $cep = substr($cep, 0, 5) . '-' . substr($cep, 5);
            
            // Se tem ID, atualiza, senão insere
            if ($id_endereco) {
                $stmt = $conn->prepare("
                    UPDATE Endereco SET 
                        rua = :rua, 
                        numero = :numero, 
                        complemento = :complemento, 
                        bairro = :bairro, 
                        cidade = :cidade, 
                        estado = :estado, 
                        cep = :cep
                    WHERE id_endereco = :id_endereco AND id_usuario = :id_usuario
                ");
                $stmt->bindParam(':id_endereco', $id_endereco);
                $stmt->bindParam(':id_usuario', $_SESSION['usuario_id']);
                $mensagem = 'Endereço atualizado com sucesso';
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO Endereco (
                        id_usuario, rua, numero, complemento, bairro, cidade, estado, cep
                    ) VALUES (
                        :id_usuario, :rua, :numero, :complemento, :bairro, :cidade, :estado, :cep
                    )
                ");
                $stmt->bindParam(':id_usuario', $_SESSION['usuario_id']);
                $mensagem = 'Endereço adicionado com sucesso';
            }
            
            $stmt->bindParam(':rua', $rua);
            $stmt->bindParam(':numero', $numero);
            $stmt->bindParam(':complemento', $complemento);
            $stmt->bindParam(':bairro', $bairro);
            $stmt->bindParam(':cidade', $cidade);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':cep', $cep);
            
            $stmt->execute();
            flashMessage($mensagem, 'success');
            redirectTo('perfil.php');
        } catch (Exception $e) {
            flashMessage('Erro ao salvar endereço: ' . $e->getMessage(), 'error');
        }
    }
}

// Verificar se está editando um endereço
$endereco = null;
if (isset($_GET['editar_endereco']) && is_numeric($_GET['editar_endereco'])) {
    $id_endereco = (int)$_GET['editar_endereco'];
    
    try {
        $stmt = $conn->prepare("
            SELECT * FROM Endereco 
            WHERE id_endereco = :id_endereco AND id_usuario = :id_usuario
        ");
        $stmt->bindParam(':id_endereco', $id_endereco);
        $stmt->bindParam(':id_usuario', $_SESSION['usuario_id']);
        $stmt->execute();
        
        $endereco = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$endereco) {
            flashMessage('Endereço não encontrado', 'error');
            redirectTo('perfil.php');
        }
    } catch (Exception $e) {
        flashMessage('Erro ao carregar endereço: ' . $e->getMessage(), 'error');
    }
}

// Buscar dados do usuário
try {
    $stmt = $conn->prepare("SELECT * FROM Usuario WHERE id_usuario = :id");
    $stmt->bindParam(':id', $_SESSION['usuario_id']);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Buscar endereços
    $stmt = $conn->prepare("SELECT * FROM Endereco WHERE id_usuario = :id ORDER BY id_endereco DESC");
    $stmt->bindParam(':id', $_SESSION['usuario_id']);
    $stmt->execute();
    $enderecos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    flashMessage('Erro ao carregar dados: ' . $e->getMessage(), 'error');
    $usuario = [];
    $enderecos = [];
}

// Verificar se deve mostrar o formulário de endereço
$mostrar_form_endereco = isset($_GET['adicionar_endereco']) || isset($_GET['editar_endereco']);

// Incluir o cabeçalho
require_once 'includes/header.php';
?>

<div class="container mt-4">
    <h1>Meu Perfil</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informações Pessoais</h5>
                </div>
                <div class="card-body">
                    <p><strong>Nome:</strong> <?php echo htmlspecialchars($usuario['nome']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($usuario['email']); ?></p>
                    <p><strong>Tipo de Conta:</strong> <?php echo ucfirst($usuario['tipo_usuario']); ?></p>
                    <p><strong>Data de Cadastro:</strong> <?php echo date('d/m/Y', strtotime($usuario['data_criacao'])); ?></p>
                    
                    <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#alterarSenhaModal">
                        <i class="bi bi-key"></i> Alterar Senha
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Meus Endereços</h5>
                    <?php if (!$mostrar_form_endereco): ?>
                        <a href="perfil.php?adicionar_endereco=1" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg"></i> Adicionar Endereço
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($mostrar_form_endereco): ?>
                        <!-- Formulário de Endereço -->
                        <h5><?php echo $endereco ? 'Editar Endereço' : 'Novo Endereço'; ?></h5>
                        <form method="POST" action="perfil.php">
                            <?php if ($endereco): ?>
                                <input type="hidden" name="id_endereco" value="<?php echo $endereco['id_endereco']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-9 mb-3">
                                    <label for="rua" class="form-label">Rua</label>
                                    <input type="text" class="form-control" id="rua" name="rua" 
                                           value="<?php echo $endereco ? htmlspecialchars($endereco['rua']) : ''; ?>" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="numero" class="form-label">Número</label>
                                    <input type="text" class="form-control" id="numero" name="numero" 
                                           value="<?php echo $endereco ? htmlspecialchars($endereco['numero']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="complemento" class="form-label">Complemento</label>
                                <input type="text" class="form-control" id="complemento" name="complemento" 
                                       value="<?php echo $endereco ? htmlspecialchars($endereco['complemento']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="bairro" class="form-label">Bairro</label>
                                <input type="text" class="form-control" id="bairro" name="bairro" 
                                       value="<?php echo $endereco ? htmlspecialchars($endereco['bairro']) : ''; ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="cidade" class="form-label">Cidade</label>
                                    <input type="text" class="form-control" id="cidade" name="cidade" 
                                           value="<?php echo $endereco ? htmlspecialchars($endereco['cidade']) : ''; ?>" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="estado" class="form-label">Estado</label>
                                    <select class="form-select" id="estado" name="estado" required>
                                        <option value="">UF</option>
                                        <option value="AC" <?php echo $endereco && $endereco['estado'] == 'AC' ? 'selected' : ''; ?>>AC</option>
                                        <option value="AL" <?php echo $endereco && $endereco['estado'] == 'AL' ? 'selected' : ''; ?>>AL</option>
                                        <option value="AP" <?php echo $endereco && $endereco['estado'] == 'AP' ? 'selected' : ''; ?>>AP</option>
                                        <option value="AM" <?php echo $endereco && $endereco['estado'] == 'AM' ? 'selected' : ''; ?>>AM</option>
                                        <option value="BA" <?php echo $endereco && $endereco['estado'] == 'BA' ? 'selected' : ''; ?>>BA</option>
                                        <option value="CE" <?php echo $endereco && $endereco['estado'] == 'CE' ? 'selected' : ''; ?>>CE</option>
                                        <option value="DF" <?php echo $endereco && $endereco['estado'] == 'DF' ? 'selected' : ''; ?>>DF</option>
                                        <option value="ES" <?php echo $endereco && $endereco['estado'] == 'ES' ? 'selected' : ''; ?>>ES</option>
                                        <option value="GO" <?php echo $endereco && $endereco['estado'] == 'GO' ? 'selected' : ''; ?>>GO</option>
                                        <option value="MA" <?php echo $endereco && $endereco['estado'] == 'MA' ? 'selected' : ''; ?>>MA</option>
                                        <option value="MT" <?php echo $endereco && $endereco['estado'] == 'MT' ? 'selected' : ''; ?>>MT</option>
                                        <option value="MS" <?php echo $endereco && $endereco['estado'] == 'MS' ? 'selected' : ''; ?>>MS</option>
                                        <option value="MG" <?php echo $endereco && $endereco['estado'] == 'MG' ? 'selected' : ''; ?>>MG</option>
                                        <option value="PA" <?php echo $endereco && $endereco['estado'] == 'PA' ? 'selected' : ''; ?>>PA</option>
                                        <option value="PB" <?php echo $endereco && $endereco['estado'] == 'PB' ? 'selected' : ''; ?>>PB</option>
                                        <option value="PR" <?php echo $endereco && $endereco['estado'] == 'PR' ? 'selected' : ''; ?>>PR</option>
                                        <option value="PE" <?php echo $endereco && $endereco['estado'] == 'PE' ? 'selected' : ''; ?>>PE</option>
                                        <option value="PI" <?php echo $endereco && $endereco['estado'] == 'PI' ? 'selected' : ''; ?>>PI</option>
                                        <option value="RJ" <?php echo $endereco && $endereco['estado'] == 'RJ' ? 'selected' : ''; ?>>RJ</option>
                                        <option value="RN" <?php echo $endereco && $endereco['estado'] == 'RN' ? 'selected' : ''; ?>>RN</option>
                                        <option value="RS" <?php echo $endereco && $endereco['estado'] == 'RS' ? 'selected' : ''; ?>>RS</option>
                                        <option value="RO" <?php echo $endereco && $endereco['estado'] == 'RO' ? 'selected' : ''; ?>>RO</option>
                                        <option value="RR" <?php echo $endereco && $endereco['estado'] == 'RR' ? 'selected' : ''; ?>>RR</option>
                                        <option value="SC" <?php echo $endereco && $endereco['estado'] == 'SC' ? 'selected' : ''; ?>>SC</option>
                                        <option value="SP" <?php echo $endereco && $endereco['estado'] == 'SP' ? 'selected' : ''; ?>>SP</option>
                                        <option value="SE" <?php echo $endereco && $endereco['estado'] == 'SE' ? 'selected' : ''; ?>>SE</option>
                                        <option value="TO" <?php echo $endereco && $endereco['estado'] == 'TO' ? 'selected' : ''; ?>>TO</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="cep" class="form-label">CEP</label>
                                    <input type="text" class="form-control" id="cep" name="cep" 
                                           placeholder="00000-000"
                                           value="<?php echo $endereco ? htmlspecialchars($endereco['cep']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" name="salvar_endereco" class="btn btn-primary">Salvar</button>
                                <a href="perfil.php" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <?php if (empty($enderecos)): ?>
                            <p class="text-center">Você ainda não cadastrou nenhum endereço.</p>
                        <?php else: ?>
                            <div class="row row-cols-1 row-cols-md-2 g-4">
                                <?php foreach ($enderecos as $end): ?>
                                    <div class="col">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($end['rua']) . ', ' . htmlspecialchars($end['numero']); ?></h6>
                                                <?php if (!empty($end['complemento'])): ?>
                                                    <p class="card-text mb-1"><?php echo htmlspecialchars($end['complemento']); ?></p>
                                                <?php endif; ?>
                                                <p class="card-text mb-1">
                                                    <?php echo htmlspecialchars($end['bairro']) . ', ' . htmlspecialchars($end['cidade']) . '/' . htmlspecialchars($end['estado']); ?>
                                                </p>
                                                <p class="card-text">CEP: <?php echo htmlspecialchars($end['cep']); ?></p>
                                            </div>
                                            <div class="card-footer">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="perfil.php?editar_endereco=<?php echo $end['id_endereco']; ?>" class="btn btn-outline-primary">
                                                        <i class="bi bi-pencil"></i> Editar
                                                    </a>
                                                    <a href="perfil.php?excluir_endereco=<?php echo $end['id_endereco']; ?>" 
                                                       class="btn btn-outline-danger"
                                                       onclick="return confirm('Tem certeza que deseja excluir este endereço?');">
                                                        <i class="bi bi-trash"></i> Excluir
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Alterar Senha -->
<div class="modal fade" id="alterarSenhaModal" tabindex="-1" aria-labelledby="alterarSenhaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="alterarSenhaModalLabel">Alterar Senha</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="atualizar-senha.php">
                    <div class="mb-3">
                        <label for="senha_atual" class="form-label">Senha Atual</label>
                        <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
                    </div>
                    <div class="mb-3">
                        <label for="nova_senha" class="form-label">Nova Senha</label>
                        <input type="password" class="form-control" id="nova_senha" name="nova_senha" required>
                        <div class="form-text">A senha deve ter pelo menos 8 caracteres.</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirma_senha" class="form-label">Confirme a Nova Senha</label>
                        <input type="password" class="form-control" id="confirma_senha" name="confirma_senha" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Alterar Senha</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Formatação do CEP
    document.addEventListener('DOMContentLoaded', function() {
        const cepInput = document.getElementById('cep');
        if (cepInput) {
            cepInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 5) {
                    value = value.substring(0, 5) + '-' + value.substring(5, 8);
                }
                e.target.value = value;
            });
        }
    });

    // Verificar se o usuário veio do carrinho
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar se há parâmetro de retorno na URL
        const urlParams = new URLSearchParams(window.location.search);
        const retorno = urlParams.get('retorno');
        
        // Se houver sucesso no formulário e veio do carrinho
        const sucessoMsg = document.querySelector('.alert-success');
        const retornarAoCarrinho = localStorage.getItem('retornarAoCarrinho');
        
        if (sucessoMsg && retorno === 'carrinho' && retornarAoCarrinho === 'true') {
            // Limpar flag
            localStorage.removeItem('retornarAoCarrinho');
            
            // Perguntar ao usuário se deseja retornar ao carrinho
            if (confirm('Endereço cadastrado com sucesso! Deseja retornar ao carrinho para finalizar seu pedido?')) {
                // Corrigido para usar caminho relativo correto
                // Assumindo que estamos na raiz do projeto e o carrinho está em /public/carrinho.php
                window.location.href = './public/carrinho.php';
            }
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>