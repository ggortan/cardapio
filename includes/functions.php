<?php
// includes/functions.php

// Função para sanitizar entrada de dados
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Função para gerar hash de senha seguro
function passwordHash($password) {
    return password_hash($password, PASSWORD_ARGON2ID);
}

// Função para verificar senha
function passwordVerify($password, $hash) {
    return password_verify($password, $hash);
}

// Função para redirecionar
function redirectTo($page) {
    header("Location: $page");
    exit();
}

// Função para exibir mensagens de erro/sucesso
function flashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Função para renderizar mensagens de flash
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_message']['type'];
        $message = $_SESSION['flash_message']['message'];
        
        $alertClass = match($type) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };

        echo "<div class='alert $alertClass alert-dismissible fade show' role='alert'>
                {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";

        unset($_SESSION['flash_message']);
    }
}

// Função para formatar moeda
function formatCurrency($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

// Função para verificar se usuário está logado
function isLoggedIn() {
    return isset($_SESSION['usuario_id']);
}

// Função para verificar permissão de usuário
function checkUserPermission($requiredRole) {
    if (!isLoggedIn() || $_SESSION['tipo_usuario'] !== $requiredRole) {
        flashMessage('Acesso não autorizado', 'error');
        redirectTo('login.php');
    }
}