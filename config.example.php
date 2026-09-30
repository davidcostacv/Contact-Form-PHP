<?php
declare(strict_types=1);

// Copy to config.php in the repository root. Never place it inside public/.
return [
    'mode' => 'demo', // 'demo' validates without sending; 'mail' uses PHP mail().
    'to' => '',      // Your receiving mailbox, e.g. hello@your-domain.com.
    'from' => '',    // A sender authorized by your hosting provider.
];
