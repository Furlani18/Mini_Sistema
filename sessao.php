<?php
declare(strict_types=1);

// Fuso horário do Brasil (o PHP do XAMPP vem configurado como Europe/Berlin)
date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
