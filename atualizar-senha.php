<?php
// atualizar-senha.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    flashMessage('Você precisa fazer login para alterar sua senha', 'error');
    redirectTo('login.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirma_senha = $_POST['confirma_senha'];
    
    // Validações básicas
    $errors = [];
    if (empty($senha_atual)) $errors[] = "Senha atual é obrigatória";
    if (empty($nova_senha)) $errors[] = "Nova senha é obrigatória";
    if (strlen($nova_senha) < 8) $errors[] = "A nova senha deve ter pelo menos 8 caracteres";
    if ($nova_senha !== $confirma_senha) $errors[] = "As senhas não coincidem";
    
    if (empty($errors)) {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            // Verificar senha atual
            $stmt = $conn->prepare("SELECT senha FROM Usuario WHERE id_usuario = :id");
            $stmt->bindParam(':id', $_SESSION['usuario_id']);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!passwordVerify($senha_atual, $usuario['senha'])) {
                flashMessage('Senha atual incorreta', 'error');
            } else {
                // Atualizar senha
                $nova_senha_hash = passwordHash($nova_senha);
                
                $stmt = $conn->prepare("UPDATE Usuario SET senha = :senha WHERE id_usuario = :id");
                $stmt->bindParam(':senha', $nova_senha_hash);
                $stmt->bindParam(':id', $_SESSION['usuario_id']);
                $stmt->execute();
                
                flashMessage('Senha atualizada com sucesso', 'success');
            }
        } catch (Exception $e) {
            flashMessage('Erro ao atualizar senha: ' . $e->getMessage(), 'error');
        }
    } else {
        foreach ($errors as $error) {
            flashMessage($error, 'error');
        }
    }
}

redirectTo('perfil.php');