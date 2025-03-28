<?php
class Mailer
{
    private $from;
    private $fromName;
    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    private $smtpSecure;

    public function __construct()
    {
        $this->from = 'noreply@votredomaine.com';
        $this->fromName = 'Gestion des Classes';
        $this->smtpHost = 'smtp.votredomaine.com';
        $this->smtpPort = 587;
        $this->smtpUser = 'votre_email@votredomaine.com';
        $this->smtpPass = 'votre_mot_de_passe';
        $this->smtpSecure = 'tls';
    }

    public function sendCredentials($to, $name, $email, $password)
    {
        $subject = 'Vos identifiants de connexion';
        $message = "
            <html>
            <head>
                <title>Vos identifiants de connexion</title>
            </head>
            <body>
                <h2>Bienvenue {$name} !</h2>
                <p>Vos identifiants de connexion ont été créés avec succès.</p>
                <p><strong>Email :</strong> {$email}</p>
                <p><strong>Mot de passe :</strong> {$password}</p>
                <p>Pour des raisons de sécurité, nous vous recommandons de changer votre mot de passe lors de votre première connexion.</p>
                <p>Cordialement,<br>L'équipe de gestion des classes</p>
            </body>
            </html>
        ";

        return $this->send($to, $subject, $message);
    }

    private function send($to, $subject, $message)
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . $this->fromName . ' <' . $this->from . '>',
            'Reply-To: ' . $this->from,
            'X-Mailer: PHP/' . phpversion()
        ];

        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
}
