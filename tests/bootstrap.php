<?php

// Força os testes a usarem uma base de dados isolada, nunca a de desenvolvimento.
putenv('DB_NAME=siupt_refeicoes_test');
$_ENV['DB_NAME'] = 'siupt_refeicoes_test';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Infrastructure/Database.php';