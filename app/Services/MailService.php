<?php

namespace App\Services;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
    private PHPMailer $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);

        $this->mailer->isSMTP();

        $this->mailer->Host = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = getenv('MAIL_USERNAME');
        $this->mailer->Password = getenv('MAIL_PASSWORD');

        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = (int) (getenv('MAIL_PORT') ?: 587);

        $this->mailer->setFrom(
            getenv('MAIL_FROM_ADDRESS'),
            getenv('MAIL_FROM_NAME') ?: 'HairConnect'
        );

        $this->mailer->isHTML(true);
        $this->mailer->CharSet = 'UTF-8';
    }

    public function sendPasswordResetCode(
        string $recipientEmail,
        string $code
    ): void {
        try {
            $this->mailer->clearAddresses();

            $this->mailer->addAddress($recipientEmail);

            $this->mailer->Subject = 'HairConnect Password Reset Code';

            $this->mailer->Body = '
                <div style="
                    font-family: Arial, sans-serif;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 30px;
                    border: 1px solid #e5e7eb;
                    border-radius: 10px;
                ">
                    <h2>HairConnect Password Reset</h2>

                    <p>
                        We received a request to reset your HairConnect
                        account password.
                    </p>

                    <p>Your verification code is:</p>

                    <div style="
                        font-size: 32px;
                        font-weight: bold;
                        letter-spacing: 8px;
                        text-align: center;
                        padding: 20px;
                        margin: 20px 0;
                        background: #f3f4f6;
                        border-radius: 8px;
                    ">
                        ' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '
                    </div>

                    <p>
                        This code will expire in <strong>10 minutes</strong>.
                    </p>

                    <p>
                        If you did not request a password reset,
                        you can safely ignore this email.
                    </p>

                    <p>
                        Regards,<br>
                        <strong>HairConnect Team</strong>
                    </p>
                </div>
            ';

            $this->mailer->AltBody =
                "HairConnect Password Reset\n\n" .
                "Your verification code is: {$code}\n\n" .
                "This code will expire in 10 minutes.\n\n" .
                "If you did not request a password reset, " .
                "you can safely ignore this email.";

            $this->mailer->send();

        } catch (PHPMailerException $e) {
            throw new \Exception(
                'Unable to send the verification email.'
            );
        }
    }
}
