<?php
require_once __DIR__ . '/../config/email.php';

function sendEmail($to, $toName, $subject, $body) {
    try {
        $mail = getMailer();
        $mail->addAddress($to, $toName);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed: " . $mail->ErrorInfo);
        return false;
    }
}

function sendOTPByEmail($email, $name, $otp) {
    $subject = "Your OTP - MediQueue";
    $body = "<html><body>";
    $body .= "<h2>Email Verification</h2>";
    $body .= "<p>Dear " . htmlspecialchars($name) . ",</p>";
    $body .= "<p>Your OTP is: <strong style='font-size:24px;'>" . $otp . "</strong></p>";
    $body .= "<p>Valid for 10 minutes.</p>";
    $body .= "</body></html>";
    return sendEmail($email, $name, $subject, $body);
}

function sendAppointmentBookedEmail($email, $name, $token, $doctor, $department, $date, $wait) {
    $subject = "Appointment Confirmed - MediQueue";
    $body  = "<html><body style='font-family:Inter,sans-serif;color:#333;'>";
    $body .= "<h2 style='color:#667eea;'>Appointment Confirmed</h2>";
    $body .= "<p>Dear " . htmlspecialchars($name) . ",</p>";
    $body .= "<p>Your appointment has been booked successfully.</p>";
    $body .= "<div style='background:#f0f4ff;padding:16px;border-radius:10px;margin:16px 0;'>";
    $body .= "<p><strong>Token:</strong> " . htmlspecialchars($token) . "</p>";
    $body .= "<p><strong>Doctor:</strong> Dr. " . htmlspecialchars($doctor) . "</p>";
    $body .= "<p><strong>Department:</strong> " . htmlspecialchars($department) . "</p>";
    $body .= "<p><strong>Date:</strong> " . htmlspecialchars($date) . "</p>";
    $body .= "<p><strong>Estimated Wait:</strong> " . (int)$wait . " minutes</p>";
    $body .= "</div>";
    $body .= "<p>Please arrive 10 minutes before your token is called.</p>";
    $body .= "</body></html>";
    return sendEmail($email, $name, $subject, $body);
}
?>
