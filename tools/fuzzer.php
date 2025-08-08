<?php declare(strict_types=1);

use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\PTSException;
use danog\MadelineProto\RPCErrorException;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\Logger as SettingsLogger;
use danog\MadelineProto\Settings\TLSchema;
use danog\MadelineProto\TL\TL;
use danog\MadelineProto\Tools;
use Revolt\EventLoop;
use Webmozart\Assert\Assert;

use function Amp\async;
use function Amp\Future\await;

/*
Copyright 2016-2020 Daniil Gentili
(https://daniil.it)
This file is part of MadelineProto.
MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU Affero General Public License for more details.
You should have received a copy of the GNU General Public License along with MadelineProto.
If not, see <http://www.gnu.org/licenses/>.
 */

require 'vendor/autoload.php';
$logger = new Logger(new SettingsLogger);

set_error_handler(['\danog\MadelineProto\Exception', 'ExceptionErrorHandler']);

/**
 * @internal
 */
function getTLSchema(): TLSchema
{
    $layerFile = glob(__DIR__."/../src/TL_telegram_v*.tl")[0];
    return (new TLSchema)->setAPISchema($layerFile)->setSecretSchema('')->setFuzzMode(true);
}

/**
 * Get TL info of layer.
 *
 * @internal
 *
 * @return void
 */
function getTL(TLSchema $schema)
{
    $layer = new TL();
    $layer->init($schema);

    return ['methods' => $layer->getMethods(), 'constructors' => $layer->getConstructors()];
}
$schema = getTLSchema();
$layer = getTL($schema);
$res = '';

$settings = new Settings;
$settings->setSchema($schema);
$settings->getLogger()->setLevel(Logger::ULTRA_VERBOSE);

echo "Bot login:".PHP_EOL;
$bot = new \danog\MadelineProto\API('fuzz_bot.madeline');
$bot->start();
$bot->updateSettings($settings);
Assert::true($bot->isSelfBot(), "fuzz_bot.madeline is not a bot!");
$u = $bot->getSelf()['username'];
Assert::true($bot->getSelf()['bot_business'], "fuzz_bot.madeline ($u) is not a business bot, enable business mode in botfather!");
$bot->restart();

echo "User login:".PHP_EOL;
$user = new \danog\MadelineProto\API('fuzz_user.madeline');
$user->start();
$user->updateSettings($settings);
Assert::true($user->isSelfUser(), "fuzz_user.madeline is not a user!");
$user->restart();

Tools::sleep(1.0);

$user->getSelf();
$bot->getSelf();
$bot->getUpdates();

Logger::log("Initializing business connection...");
$rights = ['_' => 'businessBotRights'];
foreach ($user->getTL()->getConstructors()->findByPredicate('businessBotRights')['params'] as $param) {
    if ($param['type'] === 'true') {
        $rights[$param['name']] = true;
    }
}

foreach ([true, false] as $deleted) {
    $user->account->updateConnectedBot(
        bot: $bot->getSelf()['username'],
        deleted: $deleted,
        rights: $rights,
        recipients: [
            '_' => 'inputBusinessBotRecipients',
            'existing_chats' => true,
            'new_chats' => true,
            'contacts' => true,
            'non_contacts' => true,
        ],
    );
}
$cId = null;
do {
    $offset = 0;
    foreach ($bot->getUpdates(['offset' => $offset, 'timeout' => 10.0]) as $u) {
        $offset = $u['update_id'] + 1;
        $u = $u['update'];
        if ($u['_'] !== 'updateBotBusinessConnect') {
            continue;
        }
        if ($u['connection']['disabled']) {
            continue;
        }
        $cId = $u['connection']['connection_id'];
        break 2;
    }
} while (true);
$bot->account->getBotBusinessConnection(
    connection_id: $cId,
);
$bot->setNoop();

Logger::log("Initialized business connection!");

function call(API $API, string $method, array $args = []): void
{
    Tools::getVar($API, 'wrapper')->getAPI()->methodCallAsyncRead($method, $args);
}

$methods = [];

foreach ($layer['methods']->by_id as $constructor) {
    $name = $constructor['method'];
    if (strtolower($name) === 'account.deleteaccount'
        || strtolower($name) === 'auth.logout'
        || $name === 'auth.resetAuthorizations'
        || $name === 'auth.dropTempAuthKeys'
        || $name === 'account.resetAuthorization'
        || $name === 'account.resetPassword'
        || $name === 'account.updateUsername'
        || $name === 'photos.updateProfilePhoto'
        || $name === 'photos.uploadProfilePhoto'
        || !str_contains($name, '.')) {
        continue;
    }
    $methods["bot $name"]= async(static function () use ($bot, $name, &$methods): void {
        try {
            call($bot, $name);
        } catch (RPCErrorException|PTSException) {
        }
        unset($methods["bot $name"]);
    });
    $methods["user $name"] = async(static function () use ($user, $name, &$methods): void {
        try {
            call($user, $name);
        } catch (RPCErrorException|PTSException) {
        }
        unset($methods["user $name"]);
    });
    $methods["business $name"] = async(static function () use ($bot, $name, $cId, &$methods): void {
        try {
            call($bot, $name, ['businessConnectionId' => $cId]);
        } catch (RPCErrorException|PTSException) {
        }
        unset($methods["business $name"]);
    });
    $methods["business invalid $name"] = async(static function () use ($bot, $name, &$methods): void {
        try {
            call($bot, $name, ['businessConnectionId' => '']);
        } catch (RPCErrorException|PTSException) {
        }
        unset($methods["business invalid $name"]);
    });
    if (count($methods) >= 10) {
        Logger::log("Processing ".implode(", ", array_keys($methods)));
        await($methods);
        Logger::log("Done!");
    }
}

Logger::log("Processing ".implode(", ", array_keys($methods)));
await($methods);
Logger::log("Done!");
Assert::isEmpty($methods, "Some methods were not processed!");

$user->account->updateConnectedBot(
    bot: $bot->getSelf()['username'],
    deleted: true,
    rights: $rights,
    recipients: [
        '_' => 'inputBusinessBotRecipients',
        'existing_chats' => true,
        'new_chats' => true,
        'contacts' => true,
        'non_contacts' => true,
    ],
);
unset($bot, $user);

EventLoop::run();
