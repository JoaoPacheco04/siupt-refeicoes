<?php


require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    public static function enviarComprovativo(string $emailDestino, array $compra): bool {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->CharSet = 'UTF-8';
            $mail->Host = MAIL_HOST;
            $mail->Port = MAIL_PORT;
            $mail->SMTPAuth = false;

            $mail->setFrom('siupt@upt.pt', 'SIUPT Cantina');
            $mail->addAddress($emailDestino);

            $mail->Subject = 'Comprovativo da tua refeicao - SIUPT';
            $mail->Body =
                "Prato: " . ($compra['prato_principal'] ?? '') . "\n" .
                "Sopa: " . ($compra['sopa'] ?? '') . "\n" .
                "Data: " . $compra['data_refeicao'] . "\n" .
                "Valor: " . $compra['preco_total'] . " EUR\n" .
                "PIN de validacao: " . $compra['codigo_pin'] . "\n\n" .
                "Apresenta o cartao de estudante na cantina, ou usa o PIN acima se nao o tiveres contigo.";

            return $mail->send();
        } catch (Exception $e) {
            error_log("Falha ao enviar comprovativo: " . $mail->ErrorInfo);
            return false;
        }
    }
}
