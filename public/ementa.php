<?php
session_start();
echo "Ola " . ($_SESSION["user_nome"] ?? "ninguem") . "! (aluno) Sessao ativa.";