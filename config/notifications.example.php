<?php
declare(strict_types=1);

return [
    "email" => [
        "enabled" => false,
        "to" => "gravcab@yandex.ru",
        "from" => "no-reply@svider.art",
        "from_name" => "svider.art",
        "transport" => "smtp",
        "smtp" => [
            "host" => "smtp.yandex.ru",
            "port" => 465,
            "encryption" => "ssl",
            "username" => "gravcab@yandex.ru",
            "password" => "",
            "timeout" => 15,
        ],
    ],
    "telegram" => [
        "enabled" => false,
        "bot_token" => "",
        "chat_id" => "",
    ],
    "whatsapp" => [
        "enabled" => false,
        "webhook_url" => "",
        "token" => "",
    ],
    "max" => [
        "enabled" => false,
        "webhook_url" => "",
        "token" => "",
    ],
];
