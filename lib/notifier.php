<?php
declare(strict_types=1);

function loadNotificationConfig(string $root): array
{
    $configPath = $root . "/config/notifications.php";
    if (!is_file($configPath)) {
        return [];
    }

    $config = require $configPath;
    return is_array($config) ? $config : [];
}

function loadSiteConfig(string $root): array
{
    $configPath = $root . "/config/site.php";
    if (!is_file($configPath)) {
        return [];
    }

    $config = require $configPath;
    return is_array($config) ? $config : [];
}

function normalizeSiteHosts(array $siteConfig): array
{
    $hosts = [];
    $primary = trim((string) ($siteConfig["primary_domain"] ?? $siteConfig["domain"] ?? ($siteConfig["domains"]["primary"] ?? "")));
    if ($primary !== "") {
        $hosts[] = $primary;
    }

    $aliasSources = [];
    if (array_key_exists("mirror_domains", $siteConfig)) {
        $aliasSources[] = $siteConfig["mirror_domains"];
    }
    if (array_key_exists("aliases", $siteConfig)) {
        $aliasSources[] = $siteConfig["aliases"];
    }
    if (isset($siteConfig["domains"]) && is_array($siteConfig["domains"]) && array_key_exists("aliases", $siteConfig["domains"])) {
        $aliasSources[] = $siteConfig["domains"]["aliases"];
    }

    foreach ($aliasSources as $source) {
        if (!is_array($source)) {
            $source = [$source];
        }

        foreach ($source as $entry) {
            $entry = trim((string) $entry);
            if ($entry === "" || in_array($entry, $hosts, true)) {
                continue;
            }
            $hosts[] = $entry;
        }
    }

    return $hosts;
}

function getSiteLabel(array $siteConfig): string
{
    if (!empty($siteConfig["label"])) {
        return (string) $siteConfig["label"];
    }
    if (!empty($siteConfig["name"])) {
        return (string) $siteConfig["name"];
    }

    $hosts = normalizeSiteHosts($siteConfig);
    if ($hosts) {
        return implode(" / ", $hosts);
    }

    return "svider.art";
}

function getSitePrimaryDomain(array $siteConfig): string
{
    $hosts = normalizeSiteHosts($siteConfig);
    return $hosts[0] ?? "svider.art";
}

function notifyAboutRequest(array $request, array $contacts, array $config, array $siteConfig): array
{
    $results = [
        "email" => ["enabled" => false, "sent" => false, "message" => "not configured"],
        "telegram" => ["enabled" => false, "sent" => false, "message" => "not configured"],
        "whatsapp" => ["enabled" => false, "sent" => false, "message" => "not configured"],
        "max" => ["enabled" => false, "sent" => false, "message" => "not configured"],
    ];

    $results["email"] = notifyViaEmail($request, $contacts, $config["email"] ?? [], $siteConfig);
    $results["telegram"] = notifyViaTelegram($request, $config["telegram"] ?? [], $siteConfig);
    $results["whatsapp"] = notifyViaWebhookChannel($request, $config["whatsapp"] ?? [], "WhatsApp", $siteConfig);
    $results["max"] = notifyViaWebhookChannel($request, $config["max"] ?? [], "MAX", $siteConfig);

    return $results;
}

function humanizeRequestType(string $type): string
{
    return match ($type) {
        "ready_work" => "Готовая работа",
        "individual" => "Индивидуальный запрос",
        default => $type !== "" ? $type : "Не указан",
    };
}

function humanizeRequestSource(string $source): string
{
    return match ($source) {
        "homepage" => "Главная страница",
        "work-detail" => "Страница работы",
        "site" => "Сайт",
        default => $source !== "" ? $source : "Сайт",
    };
}

function requestFieldValue(array $request, string $key, string $fallback = "Не указан"): string
{
    $value = trim((string) ($request[$key] ?? ""));
    return $value !== "" ? $value : $fallback;
}

function requestNotificationLines(array $request, array $siteConfig): array
{
    $siteLabel = getSiteLabel($siteConfig);

    return [
        "Новая заявка с сайта {$siteLabel}",
        "",
        "Имя: " . requestFieldValue($request, "name"),
        "Контакт: " . requestFieldValue($request, "contact"),
        "Тип запроса: " . humanizeRequestType((string) ($request["requestType"] ?? "")),
        "Работа: " . requestFieldValue($request, "workTitle", "Не указана"),
        "Размер / формат: " . requestFieldValue($request, "size"),
        "Город: " . requestFieldValue($request, "city"),
        "Предпочтительный способ связи: " . requestFieldValue($request, "preferredChannel"),
        "Источник: " . humanizeRequestSource((string) ($request["source"] ?? "site")),
        "",
        "Текст обращения:",
        requestFieldValue($request, "message", "Не указан"),
    ];
}

function requestNotificationText(array $request, array $siteConfig): string
{
    return implode(PHP_EOL, requestNotificationLines($request, $siteConfig));
}

function telegramEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function requestNotificationTelegramText(array $request, array $siteConfig): string
{
    $siteLabel = getSiteLabel($siteConfig);
    $lines = [
        "<b>Новая заявка с сайта {$siteLabel}</b>",
        "",
        "Тип запроса: <b>" . telegramEscape(humanizeRequestType((string) ($request["requestType"] ?? ""))) . "</b>",
        "Имя: <b>" . telegramEscape(requestFieldValue($request, "name")) . "</b>",
        "Контакт: <b>" . telegramEscape(requestFieldValue($request, "contact")) . "</b>",
        "Работа: <b>" . telegramEscape(requestFieldValue($request, "workTitle", "Не указана")) . "</b>",
        "Размер / формат: <b>" . telegramEscape(requestFieldValue($request, "size")) . "</b>",
        "Город: <b>" . telegramEscape(requestFieldValue($request, "city")) . "</b>",
        "Предпочтительный способ связи: <b>" . telegramEscape(requestFieldValue($request, "preferredChannel")) . "</b>",
        "Источник: <b>" . telegramEscape(humanizeRequestSource((string) ($request["source"] ?? "site"))) . "</b>",
        "",
        "<b>Текст обращения</b>",
        telegramEscape(requestFieldValue($request, "message", "Не указан")),
    ];

    return implode(PHP_EOL, $lines);
}

function firstEmailContactForNotifications(array $contacts): ?string
{
    foreach ($contacts as $contact) {
        if (($contact["type"] ?? "") === "email" && !empty($contact["value"])) {
            return (string) $contact["value"];
        }
    }

    return null;
}

function notifyViaEmail(array $request, array $contacts, array $config, array $siteConfig): array
{
    $enabled = (bool) ($config["enabled"] ?? false);
    $to = (string) ($config["to"] ?? firstEmailContactForNotifications($contacts) ?? "");
    $siteLabel = getSiteLabel($siteConfig);
    $primaryDomain = getSitePrimaryDomain($siteConfig);
    $from = (string) ($config["from"] ?? "no-reply@{$primaryDomain}");
    $fromName = (string) ($config["from_name"] ?? $siteLabel);

    if (!$enabled) {
        return ["enabled" => false, "sent" => false, "message" => "disabled"];
    }

    if ($to === "") {
        return ["enabled" => true, "sent" => false, "message" => "empty recipient"];
    }

    $subject = "Новая заявка с сайта {$siteLabel}";
    $body = requestNotificationText($request, $siteConfig);
    $transport = (string) ($config["transport"] ?? "mail");

    try {
        if ($transport === "smtp") {
            $smtp = is_array($config["smtp"] ?? null) ? $config["smtp"] : [];
            smtpSendMail($to, $subject, $body, $from, $fromName, $siteConfig, $smtp);
            return ["enabled" => true, "sent" => true, "message" => "sent via smtp"];
        }

        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
            "From: {$fromName} <{$from}>",
        ];
        $ok = @mail($to, "=?UTF-8?B?" . base64_encode($subject) . "?=", $body, implode("\r\n", $headers));
        return [
            "enabled" => true,
            "sent" => $ok,
            "message" => $ok ? "sent via mail()" : "mail() returned false",
        ];
    } catch (Throwable $error) {
        return ["enabled" => true, "sent" => false, "message" => $error->getMessage()];
    }
}

function smtpSendMail(string $to, string $subject, string $body, string $from, string $fromName, array $siteConfig, array $smtp): void
{
    $host = (string) ($smtp["host"] ?? "");
    $port = (int) ($smtp["port"] ?? 465);
    $encryption = (string) ($smtp["encryption"] ?? "ssl");
    $username = (string) ($smtp["username"] ?? "");
    $password = (string) ($smtp["password"] ?? "");
    $timeout = (int) ($smtp["timeout"] ?? 15);

    if ($host === "" || $username === "" || $password === "") {
        throw new RuntimeException("SMTP credentials are incomplete");
    }

    $remote = $encryption === "ssl" ? "ssl://{$host}:{$port}" : "{$host}:{$port}";
    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        throw new RuntimeException("SMTP connection failed: {$errstr} ({$errno})");
    }

    stream_set_timeout($socket, $timeout);

    smtpExpect($socket, [220]);
    $smtpHostLabel = getSitePrimaryDomain($siteConfig);
    smtpCommand($socket, "EHLO {$smtpHostLabel}", [250]);

    if ($encryption === "tls") {
        smtpCommand($socket, "STARTTLS", [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException("Failed to start TLS");
        }
        smtpCommand($socket, "EHLO {$smtpHostLabel}", [250]);
    }

    smtpCommand($socket, "AUTH LOGIN", [334]);
    smtpCommand($socket, base64_encode($username), [334]);
    smtpCommand($socket, base64_encode($password), [235]);
    smtpCommand($socket, "MAIL FROM:<{$from}>", [250]);
    smtpCommand($socket, "RCPT TO:<{$to}>", [250, 251]);
    smtpCommand($socket, "DATA", [354]);

    $headers = [
        "Date: " . date(DATE_RFC2822),
        "From: {$fromName} <{$from}>",
        "To: <{$to}>",
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
        "MIME-Version: 1.0",
        "Content-Type: text/plain; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit",
    ];
    $message = implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n.", "\n."], ["\r\n..", "\n.."], $body) . "\r\n.";
    smtpCommand($socket, $message, [250]);
    smtpCommand($socket, "QUIT", [221]);
    fclose($socket);
}

function smtpCommand($socket, string $command, array $expectedCodes): string
{
    fwrite($socket, $command . "\r\n");
    return smtpExpect($socket, $expectedCodes);
}

function smtpExpect($socket, array $expectedCodes): string
{
    $response = "";
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (preg_match('/^\d{3} /', $line)) {
            break;
        }
    }

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException("SMTP error: " . trim($response));
    }

    return $response;
}

function notifyViaTelegram(array $request, array $config, array $siteConfig): array
{
    $enabled = (bool) ($config["enabled"] ?? false);
    if (!$enabled) {
        return ["enabled" => false, "sent" => false, "message" => "disabled"];
    }

    $botToken = (string) ($config["bot_token"] ?? "");
    $chatIds = [];
    if (!empty($config["chat_id"])) {
        $chatIds[] = (string) $config["chat_id"];
    }
    if (is_array($config["chat_ids"] ?? null)) {
        foreach ($config["chat_ids"] as $chatId) {
            $chatId = trim((string) $chatId);
            if ($chatId !== "") {
                $chatIds[] = $chatId;
            }
        }
    }
    $chatIds = array_values(array_unique($chatIds));

    if ($botToken === "" || $chatIds === []) {
        return ["enabled" => true, "sent" => false, "message" => "telegram credentials are incomplete"];
    }

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $delivered = 0;
    $errors = [];

    foreach ($chatIds as $chatId) {
        $payload = [
            "chat_id" => $chatId,
            "text" => requestNotificationTelegramText($request, $siteConfig),
            "parse_mode" => "HTML",
            "disable_web_page_preview" => true,
        ];

        $result = postJsonNotification($url, $payload, "Telegram");
        if (!empty($result["sent"])) {
            $delivered++;
        } else {
            $errors[] = "{$chatId}: " . ($result["message"] ?? "unknown error");
        }
    }

    if ($delivered === count($chatIds)) {
        return [
            "enabled" => true,
            "sent" => true,
            "message" => "Telegram delivered to {$delivered} chat(s)",
        ];
    }

    return [
        "enabled" => true,
        "sent" => $delivered > 0,
        "message" => $delivered > 0
            ? "Telegram delivered to {$delivered} chat(s), errors: " . implode("; ", $errors)
            : implode("; ", $errors),
    ];
}

function notifyViaWebhookChannel(array $request, array $config, string $label, array $siteConfig): array
{
    $enabled = (bool) ($config["enabled"] ?? false);
    if (!$enabled) {
        return ["enabled" => false, "sent" => false, "message" => "disabled"];
    }

    $url = (string) ($config["webhook_url"] ?? "");
    if ($url === "") {
        return ["enabled" => true, "sent" => false, "message" => "{$label} webhook_url is empty"];
    }

    $payload = [
        "service" => strtolower($label),
        "text" => requestNotificationText($request, $siteConfig),
        "request" => $request,
    ];

    $headers = [];
    if (!empty($config["token"])) {
        $headers[] = "Authorization: Bearer " . $config["token"];
    }

    return postJsonNotification($url, $payload, $label, $headers);
}

function postJsonNotification(string $url, array $payload, string $label, array $headers = []): array
{
    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_merge(["Content-Type: application/json"], $headers),
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status >= 400) {
            return [
                "enabled" => true,
                "sent" => false,
                "message" => $error !== "" ? $error : "{$label} HTTP {$status}",
            ];
        }

        return [
            "enabled" => true,
            "sent" => true,
            "message" => "{$label} delivered",
        ];
    } catch (Throwable $error) {
        return ["enabled" => true, "sent" => false, "message" => $error->getMessage()];
    }
}
