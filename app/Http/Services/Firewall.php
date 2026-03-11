<?php
namespace App\Http\Services;


use Illuminate\Http\Request;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Bot as BotParser;
final class Firewall
{
    /**
     * Determine if the request is from a bot.
    */
    public function isBot(Request $request): bool
    {
        $userAgent = (string) $request->userAgent();

        $botParser = new BotParser();
        $botParser->setUserAgent($userAgent);
        $botParser->discardDetails();

        return ! is_null($botParser->parse());
    }

    /**
     * Check if request is from a bot and return bot info if available.
     */
    public function detectBot(Request $request): ?array
    {
        $userAgent = (string) $request->userAgent();

        $detector = new DeviceDetector($userAgent);
        $detector->parse();

        return $detector->getBot();
            
    }
}