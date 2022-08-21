<?php
declare(strict_types=1);

namespace TelegramBotTest\Unit;

use PHPUnit\Framework\TestCase;
use TelegramBot\CrashPad;
use TelegramBot\Entities\Update;
use TelegramBot\Plugin;
use TelegramBot\Telegram;
use TelegramBot\UpdateHandler;
use TelegramBot\Util\DotEnv;

class CrashTest extends \PHPUnit\Framework\TestCase
{

    public function test_crash(): void
    {
        $plugin = new class($this) extends Plugin {
            public function __construct(TestCase $testCase)
            {
                CrashPad::setAdminChatId(259760855);
                $testCase->assertEquals(259760855, CrashPad::getAdminChatId());
            }

            public function __process(Update $update): void
            {
                CrashPad::report(
                    CrashPad::getAdminChatId(),
                    new \Exception('test'),
                    json_encode($update->getRawData(), JSON_PRETTY_PRINT)
                );
            }
        };

        DotEnv::load(__DIR__ . '/../../.env');
        (new UpdateHandler())->addPlugins($plugin)->resolve(Telegram::processUpdate(
            '{"update_id":1,"message":{"message_id":1,"from":{"id":1,"is_bot":false,"first_name":"First","last_name":"Last","username":"username","language_code":"en"},"chat":{"id":1,"first_name":"First","last_name":"Last","username":"username","type":"private"},"date":1546300800,"text":"Hello World!"}}',
            getenv('TELEGRAM_BOT_TOKEN')
        ));
    }

}