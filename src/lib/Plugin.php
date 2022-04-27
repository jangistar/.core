<?php

namespace TelegramBot;

use TelegramBot\Entities\Update;
use TelegramBot\Interfaces\UpdateTypes;

/**
 * Class Plugin
 *
 * @link    https://github.com/telegram-bot-php/core
 * @author  Shahrad Elahi (https://github.com/shahradelahi)
 * @license https://github.com/telegram-bot-php/core/blob/master/LICENSE (MIT License)
 */
abstract class Plugin
{

    /**
     * The Update types
     *
     * @var array
     */
    protected array $update_types = [
        'message',
        'edited_message',
        'channel_post',
        'edited_channel_post',
        'inline_query',
        'chosen_inline_result',
        'callback_query',
        'shipping_query',
        'pre_checkout_query',
    ];

    /**
     * @var WebhookHandler
     */
    protected WebhookHandler $hook;

    /**
     * @var \Generator
     */
    protected \Generator $returns;

    /**
     * @var bool
     */
    protected bool $kill_on_yield = false;

    /**
     * Execute the plugin.
     *
     * @param WebhookHandler $receiver
     * @param Update $update
     * @return void
     */
    public function __execute(WebhookHandler $receiver, Update $update): void
    {
        $this->hook = $receiver;

        $returns = null;
        $method = 'onReceivedUpdate';
        if (method_exists($this, $method)) {
            $returns = $this->{$method}($update);
        }

        if ($returns->getReturn()) {
            if ($this->kill_on_yield) $this->kill();
        }
    }

    /**
     * Identify the update type and if method of the type is exists, execute it.
     *
     * @param Update $update
     * @return \Generator
     */
    protected function onReceivedUpdate(Update $update): \Generator
    {
        $method = UpdateTypes::identify($update);
        if (method_exists($this, $method)) {
            $return = $this->$method($update);
        }
        return $return ?? new \Generator();
    }

    /**
     * Kill the plugin.
     *
     * @return void
     */
    public function kill(): void
    {
        $this->hook->kill();
    }

}