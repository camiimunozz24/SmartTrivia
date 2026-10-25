<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/../vendor/phpmailer/src/Exception.php";
require_once __DIR__ . "/../vendor/phpmailer/src/PHPMailer.php";
require_once __DIR__ . "/../vendor/phpmailer/src/SMTP.php";

class Mailer
{
    // Manda el código de verificación para activar la cuenta.
    // El mail no lleva links a propósito: los mails con links a dominios
    // gratuitos son los que más filtra el spam de Gmail.
    public static function enviarCodigo($destinatario, $nombre, $codigo)
    {
        $mail = new PHPMailer(true);

        $config = parse_ini_file(__DIR__ . "/../config/config.ini");

        try {
            // Servidor SMTP (Gmail)
            $mail->isSMTP();
            $mail->Host       = $config['mail_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['mail_usuario'];
            $mail->Password   = $config['mail_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) $config['mail_port'];
            $mail->CharSet    = 'UTF-8';

            // Remitente y destinatario
            $mail->setFrom($config['mail_usuario'], 'SmartTrivia');
            $mail->addAddress($destinatario, $nombre);

            // Contenido
            $nombre = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
            $mail->isHTML(true);
            $mail->Subject = 'Tu código de SmartTrivia';
            $mail->Body = "
                <p>Hola, {$nombre}.</p>
                <p>Tu código para activar tu cuenta en SmartTrivia es:</p>
                <p style='font-size:32px;font-weight:bold;letter-spacing:6px;'>{$codigo}</p>
                <p>Ingresalo en la pantalla de verificación.</p>
                <p style='color:#888;font-size:12px;'>Si no te registraste en SmartTrivia, ignorá este mensaje.</p>
            ";
            $mail->AltBody = "Hola, {$nombre}. Tu código para activar tu cuenta en SmartTrivia es: {$codigo}. "
                           . "Si no te registraste, ignorá este mensaje.";

            $mail->send();
            return true;
        } catch (Exception $e) {
            // Si falla, queda registrado en el log de errores del servidor
            error_log("Error enviando mail: " . $mail->ErrorInfo);
            return false;
        }
    }
}
