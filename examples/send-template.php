<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Iletiniz\IletinizClient;

$client = new IletinizClient([
    'apiKey' => getenv('ILETINIZ_API_KEY') ?: null,
]);

$result = $client->messages->send([
    'to' => '+905551234567',
    'template' => 'order_shipped',
    'variables' => ['name' => 'Ayşe', 'tracking_no' => 'TR123456789'],
]);

echo 'Sent via template: ' . ($result['template_key'] ?? '-') . ' → ' . $result['status'] . PHP_EOL;
