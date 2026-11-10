<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'preetam0900@gmail.com');
define('MAIL_PASSWORD', 'xajhiavdedyvxzve');
define('MAIL_FROM_NAME', 'MediQueue Hospital');
define('MAIL_FROM_EMAIL', 'preetam0900@gmail.com');
define('MAIL_ENCRYPTION', 'tls');

function getMailer() {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure = MAIL_ENCRYPTION;
    $mail->Port = MAIL_PORT;
    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->isHTML(true);
    return $mail;
}
?>
