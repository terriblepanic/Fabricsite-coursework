<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';  // обязательно для кириллицы!
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'unbearable.panic@gmail.com';
    $mail->Password = 'kypz adea endu jsnd';
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;

    $mail->setFrom('unbearable.panic@gmail.com', 'Чудотворец');
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->Body = $body;

    if (!$mail->send()) {
        throw new Exception('Mailer Error: ' . $mail->ErrorInfo);
    }
}