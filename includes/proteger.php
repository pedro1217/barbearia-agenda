<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION["usuario_id"])) {
    header("Location: /barbearia-agenda/login.php");
    exit;
}