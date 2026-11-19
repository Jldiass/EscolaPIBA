<?php
/** Sai do sistema: apaga a sessão e volta para o login. */
require __DIR__ . '/includes/auth.php';

$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
