<?php
declare(strict_types=1);

// Copy to email.local.php on hosts that do not support PHP environment variables.
// Keep the real file out of Git and never print its contents in a webpage.
return [
    'public_base_url' => 'https://your-sbcis-domain.example',
    'service_id' => 'your_emailjs_service_id',
    'template_id' => 'your_emailjs_template_id',
    'public_key' => 'your_emailjs_public_key',
    'private_key' => 'your_rotated_emailjs_private_key',
];
