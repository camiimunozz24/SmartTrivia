<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/../vendor/phpmailer/src/Exception.php";
require_once __DIR__ . "/../vendor/phpmailer/src/PHPMailer.php";
require_once __DIR__ . "/../vendor/phpmailer/src/SMTP.php";
class Mailer
{
    public static function enviarValidacion($destinatario, $nombre, $token)
    {
        $mail = new PHPMailer(true);

        $config = parse_ini_file(__DIR__ . "/../config/config.ini");

        try {
            //para Gmail
            $mail->isSMTP();
            $mail->Host       = $config['mail_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['mail_usuario'];
            $mail->Password =  $config['mail_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) $config['mail_port'];
            $mail->CharSet = 'UTF-8';


            //remitente y destinatario
            $mail->setFrom($config['mail_usuario'], 'SmartTrivia');
            $mail->addAddress($destinatario, $nombre);

            //contenido:
            $link = "http://localhost/registro/validarToken?token=" . urlencode($token);
            $mail->isHTML(true);
            $mail->Subject = 'Confirmá tu cuenta en SmartTrivia';
            $mail->Body = "
                <h2>¡Hola, {$nombre}!</h2>
                <p>Gracias por registrarte en <strong>SmartTrivia</strong>.</p>
                <p>Para activar tu cuenta hacé clic en el siguiente botón:</p>
                <p>
                    <a href='{$link}'
                       style='background:#4F46E5;color:white;padding:12px 24px;
                              text-decoration:none;border-radius:6px;font-size:16px;'>
                        Validar mi cuenta
                    </a>
                </p>
                <p style='color:#888;font-size:12px;'>
                    O copiá este link en tu navegador:<br>{$link}
                </p>
                <p style='color:#888;font-size:12px;'>
                    Si no te registraste en SmartTrivia, ignorá este email.
                </p>
            ";
            $mail->AltBody = "Hola {$nombre}, confirmá tu cuenta: {$link}";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error enviando mail: " . $mail->ErrorInfo);
            return false;
        }

    }
}