<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Iletiniz\IletinizClient;

$client = new IletinizClient([
    'apiKey' => getenv('ILETINIZ_API_KEY') ?: null,
]);

$result = $client->messages->send([
    'to' => '+905551234567',
    'body' => 'Merhaba! Bu Iletiniz SDK ile gönderilen test mesajıdır.',
]);

echo 'Job: ' . $result['job_id'] . ' Status: ' . $result['status'] . PHP_EOL;
