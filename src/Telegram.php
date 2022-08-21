<?php

namespace TelegramBot;

use TelegramBot\Entities\Response;
use TelegramBot\Entities\Update;
use TelegramBot\Exception\TelegramException;
use TelegramBot\Util\DotEnv;
use TelegramBot\Util\Toolkit;

/**
 * Telegram class
 *
 * @link    https://github.com/telegram-bot-php/core
 * @author  Shahrad Elahi (https://github.com/shahradelahi)
 * @license https://github.com/telegram-bot-php/core/blob/master/LICENSE (MIT License)
 */
class Telegram
{

    /**
     * @var string
     */
    public static string $VERSION = 'v1.0.0';

    /**
     * @var string
     */
    private static string $api_key;

    /**
     * Telegram constructor.
     *
     * @param string $api_token
     */
    public function __construct(string $api_token = '')
    {
        if ($api_token === '') {
            $env_file = $this->getEnvFilePath();
            $api_token = DotEnv::load($env_file)->get('TELEGRAM_BOT_TOKEN');
        }

        if (empty($api_token) || self::validateToken($api_token) === false) {
            throw new TelegramException(sprintf(
                'Invalid Telegram API token: %s',
                $api_token
            ));
        }

        self::setToken($api_token);
    }

    /**
     * Set the current bot token.
     *
     * @param string $api_key
     * @return void
     */
    public static function setToken(string $api_key): void
    {
        static::$api_key = $api_key;
        DotEnv::put('TELEGRAM_BOT_TOKEN', $api_key);
    }

    /**
     * Get the current bot token.
     *
     * @return string|false
     */
    public static function getApiToken(): string|false
    {
        return static::$api_key;
    }

    /**
     * Get env file path and return it
     *
     * @return string
     */
    private function getEnvFilePath(): string
    {
        $defaultEnvPaths = [
            $_SERVER['DOCUMENT_ROOT'] . '/.env',
            getcwd() . '/../.env',
            getcwd() . '/.env',
        ];

        foreach ($defaultEnvPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return '';
    }

    /**
     * Get token from env file.
     *
     * @param string $file
     * @return ?string
     */
    protected function getEnvToken(string $file): ?string
    {
        if (!file_exists($file)) return null;
        return DotEnv::load($file)::get('TELEGRAM_BOT_TOKEN');
    }

    /**
     * Get bot info from given API key
     *
     * @return Response
     * @throws TelegramException
     */
    public function getInfo(): Response
    {
        $result = Request::getMe();

        if (!$result->isOk()) {
            throw new TelegramException($result->getErrorCode() . ': ' . $result->getDescription());
        }

        return $result;
    }

    /**
     * Set Webhook for bot
     *
     * @param string $url
     * @param array $data Optional parameters.
     * @return Response
     * @throws TelegramException
     */
    public function setWebhook(string $url, array $data = []): Response
    {
        if ($url === '') {
            throw new TelegramException('Hook url is empty!');
        }

        if (!str_starts_with($url, 'https://')) {
            throw new TelegramException('Hook url must start with https://');
        }

        $data = array_intersect_key($data, array_flip([
            'certificate',
            'ip_address',
            'max_connections',
            'allowed_updates',
            'drop_pending_updates',
        ]));
        $data['url'] = $url;

        $result = Request::setWebhook($data);

        if (!$result->isOk()) {
            throw new TelegramException(
                'Webhook was not set! Error: ' . $result->getErrorCode() . ' ' . $result->getDescription()
            );
        }

        return $result;
    }

    /**
     * Delete any assigned webhook
     *
     * @param array $data
     * @return Response
     * @throws TelegramException
     */
    public function deleteWebhook(array $data = []): Response
    {
        $result = Request::deleteWebhook($data);

        if (!$result->isOk()) {
            throw new TelegramException(
                'Webhook was not deleted! Error: ' . $result->getErrorCode() . ' ' . $result->getDescription()
            );
        }

        return $result;
    }

    /**
     * Pass the update to the given webhook handler
     *
     * @param UpdateHandler $webhook_handler The webhook handler
     * @param Update|null $update By default, it will get the update from input
     * @return void
     */
    public function fetchWith(UpdateHandler $webhook_handler, Update|null $update = null): void
    {
        if (is_subclass_of($webhook_handler, UpdateHandler::class)) {
            if ($update === null) $update = self::getUpdate();
            $webhook_handler->resolve($update);
        }
    }

    /**
     * Get the update from input
     *
     * @return Update|false
     */
    public static function getUpdate(): Update|false
    {
        $input = self::getInput();
        if (empty($input)) return false;
        return Telegram::processUpdate($input, self::getApiToken());
    }

    /**
     * Get input from stdin and return it
     *
     * @return string|null
     */
    public static function getInput(): string|null
    {
        return file_get_contents('php://input') ?? null;
    }

    /**
     * This method will convert a string to an update object
     *
     * @param string $input The input string
     * @param string|null $apiKey The API key
     * @return Update|false
     */
    public static function processUpdate(string $input, string|null $apiKey = null): Update|false
    {
        if (empty($input)) {
            throw new TelegramException(
                'Input is empty! Please check your code and try again.'
            );
        }

        if ($apiKey !== null && !self::validateToken($apiKey)) {
            throw new TelegramException(
                'Invalid token! Please check your code and try again.'
            );
        }

        if ($apiKey !== null && self::validateWebData($apiKey, $input)) {
            if (Toolkit::isUrlEncode($input)) {
                $web_data = Toolkit::urlDecode($input);
            }

            if (Toolkit::isJson($input)) {
                $web_data = json_decode($input, true);
            }

            if (!empty($web_data) && is_array($web_data)) {
                $input = json_encode([
                    'web_data' => $web_data,
                ]);
            }
        }

        if (!Toolkit::isJson($input)) {
            throw new TelegramException(sprintf(
                "Input is not a valid JSON string! Please check your code and try again.\nInput: %s",
                $input
            ));
        }

        $input = json_decode($input, true);

        return new Update($input);
    }

    /**
     * Validate the token
     *
     * @param string $token (e.g. 123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11) {digit}:{alphanumeric[34]}
     * @return bool
     */
    public static function validateToken(string $token): bool
    {
        preg_match_all('/([0-9]+:[a-zA-Z0-9-_]+)/', $token, $matches);
        return count($matches[0]) == 1;
    }

    /**
     * Validate webapp data from is from Telegram
     *
     * @link https://core.telegram.org/bots/webapps#validating-data-received-via-the-web-app
     *
     * @param string $token The bot token
     * @param string $body The message body from getInput()
     * @return bool
     */
    public static function validateWebData(string $token, string $body): bool
    {
        if (!Toolkit::isJson($body)) {
            $raw_data = rawurldecode(str_replace('_auth=', '', $body));
            $data = Toolkit::urlDecode($raw_data);

            if (empty($data['user'])) {
                return false;
            }

            $data['user'] = urldecode($data['user']);

        } else {
            $data = json_decode($body, true);

            if (empty($data['user'])) {
                return false;
            }

            $data['user'] = json_encode($data['user']);
        }

        $data_check_string = "auth_date={$data['auth_date']}\nquery_id={$data['query_id']}\nuser={$data['user']}";
        $secret_key = hash_hmac('sha256', $token, "WebAppData", true);

        return hash_hmac('sha256', $data_check_string, $secret_key) == $data['hash'];
    }

}