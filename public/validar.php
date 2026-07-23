<?php
session_start();
echo "Ola " . ($_SESSION["user_nome"] ?? "ninguem") . "! (funcionario) Sessao ativa.";