<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Iletiniz\IletinizClient;

$client = new IletinizClient([
    'apiKey' => getenv('ILETINIZ_API_KEY') ?: null,
]);

$result = $client->messages->sendBulk([
    'template' => 'low_stock_alert',
    'items' => [
        ['to' => '+905551111111', 'variables' => ['product' => 'Ürün A', 'stock' => 3]],
        ['to' => '+905552222222', 'variables' => ['product' => 'Ürün B', 'stock' => 1]],
    ],
]);

printf("Toplam: %d, Gönderilen: %d, Başarısız: %d\n", $result['total'], $result['sent'], $result['failed']);
