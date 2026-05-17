<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Iletiniz\Exception\IletinizNotFoundError;
use Iletiniz\IletinizClient;

$client = new IletinizClient([
    'apiKey' => getenv('ILETINIZ_API_KEY') ?: null,
]);

$jobId = $argv[1] ?? '';

try {
    $info = $client->messages->retrieve($jobId);
    print_r($info);
} catch (IletinizNotFoundError) {
    fwrite(STDERR, "Mesaj bulunamadı.\n");
}
