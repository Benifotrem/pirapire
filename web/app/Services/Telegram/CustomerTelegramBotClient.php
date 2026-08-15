<?php

namespace App\Services\Telegram;

/**
 * TelegramBotClient pre-configured with the public customer-facing bot's
 * token (services.telegram_customer_bot.bot_token) — deliberately a
 * separate bot from the admin ops bot (plain TelegramBotClient, resolved
 * with services.telegram.bot_token) so a customer typing "/vincular" can
 * never collide with the admin-account-linking flow. Type-hint this class
 * wherever code needs to message a customer; type-hint TelegramBotClient
 * directly for the admin bot.
 */
class CustomerTelegramBotClient extends TelegramBotClient
{
    public function __construct()
    {
        parent::__construct(config('services.telegram_customer_bot.bot_token'));
    }
}
