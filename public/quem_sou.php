<?php session_start(); echo ($_SESSION["user_nome"] ?? "sem sessao") . " | tipo: " . ($_SESSION["user_tipo"] ?? "nenhum");
