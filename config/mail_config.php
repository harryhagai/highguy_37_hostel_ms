<?php
return [
    'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'port' => (int)(getenv('SMTP_PORT') ?: 587),
    'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
    'username' => getenv('SMTP_USERNAME') ?: 'highguyea@gmail.com',
    'password' => getenv('SMTP_PASSWORD') ?: 'bywhspqvnokcaxum',
    'from_email' => getenv('SMTP_FROM_EMAIL') ?: 'highguyea@gmail.com',
    'from_name' => getenv('SMTP_FROM_NAME') ?: 'HostelPro System',
];
