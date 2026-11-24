<?php
declare(strict_types=1);

// Fuso horário do Brasil (o PHP do XAMPP vem configurado como Europe/Berlin)
date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) {
    // Cookie da sessão mais seguro:
    //   httponly → JavaScript não consegue ler (protege contra roubo de sessão via XSS)
    //   samesite → o navegador não envia o cookie em formulários vindos de outros sites
    //   secure   → só trafega por HTTPS (quando o site estiver em HTTPS)
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}
