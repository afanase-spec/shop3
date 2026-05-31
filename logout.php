<?php
require_once __DIR__ . '/includes/functions.php';

session_unset();
session_destroy();

setFlashMessage('Вы успешно вышли из аккаунта', 'success');
redirect('/');
