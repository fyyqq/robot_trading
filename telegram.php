<?php

// php -S localhost:8000

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

use danog\MadelineProto\API;
use danog\MadelineProto\EventHandler;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

// require '../vendor/autoload.php';
require './vendor/autoload.php';

date_default_timezone_set('Asia/Singapore'); // GMT+8

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$settings = new Settings();

$settings->setAppInfo((new AppInfo)
->setApiId($_ENV['TELEGRAM_API_ID'])
->setApiHash($_ENV['TELEGRAM_API_HASH']));

$MadelineProto = new API('session.madeline', $settings);
$MadelineProto->start();

$groupIdMeow = $_ENV["TELEGRAM_GROUP_ID_MEOW"]; // Meow Private Channel
$groupIdPF = $_ENV["TELEGRAM_GROUP_ID_PFINSIDER"]; // pumpfun insiders
// $topicId = '10';

try {
    function getGroupMessagesMeow($groupIdMeow, $limit = 50) {
        global $MadelineProto;

        sleep(1);

        $messages = $MadelineProto->messages->getHistory([
            'peer' => $groupIdMeow,
            // 'filter' => ['topic_id' => $topicId],
            'offset_id' => 0,
            'limit' => $limit,
        ]);
        
        $latest_post = array_map(function($message) {
            return [
                'post' => explode("\n", $message['message']),
                'datetime' => date('d/m/Y H:i:s', $message['date']),
            ];
        }, $messages['messages'] ?? []);

        $findCA = [];
        $latestPostNotCA = true;
        
        foreach ($latest_post as $entries) {
            foreach ($entries['post'] as $entry) {
                if ((strlen($entry) == 43 || strlen($entry) == 44) && ctype_alnum($entry)) {
                    global $findCA;
                    $findCA[] = $entry;
                    
                    if ($entries === $latest_post[0]) {
                        global $latestPostNotCA;
                        $latestPostNotCA = false;
                    }
                }
            }
        }
        
        $first_ca = filterCAEntries($latest_post[0]['post'] ?? []);
        $second_ca = filterCAEntries($latest_post[1]['post'] ?? []);
        $check_duplicate_latest_post = !empty(array_intersect($first_ca, $second_ca));
        $findCA = !empty($findCA[0]) ? $findCA[0] : false;
        $prevPostCA =  isset($second_ca[0]) ? $second_ca[0] : "";

        print_r($findCA . ',' . $latestPostNotCA . ',' . $check_duplicate_latest_post . ',' . $prevPostCA);
    }

    function filterCAEntries($array) {
        return array_filter($array, function($entry) {
            return strlen($entry) == 44 && ctype_alnum($entry);
        });
    }

    getGroupMessagesMeow($groupIdMeow);
    echo "\n\n";

    function getGroupMessagesPumpFunInsider($groupIdPF, $limit = 2) {
        global $MadelineProto;

        sleep(1);

        $messages = $MadelineProto->messages->getHistory([
            'peer' => $groupIdPF,
            // 'filter' => ['topic_id' => $topicId],
            'offset_id' => 0,
            'limit' => $limit,
        ]);
        
        $latest_post = array_map(function($message) {
            return [
                'post' => explode("\n", $message['message']),
                'datetime' => date('d/m/Y H:i:s', $message['date']),
            ];
        }, $messages['messages'] ?? []);

        $findCA = [];
        $latestPostNotCA = true;

        foreach ($latest_post as $entries) {
            foreach ($entries['post'] as $entry) {
                if ((strlen($entry) == 43 || strlen($entry) == 44) && ctype_alnum($entry)) {
                    global $findCA;
                    $findCA[] = $entry;
                    
                    if ($entries === $latest_post[0]) {
                        global $latestPostNotCA;
                        $latestPostNotCA = false;
                    }
                }
            }
        }

        $first_ca = filterCAEntries($latest_post[0]['post'] ?? []);
        $second_ca = filterCAEntries($latest_post[1]['post'] ?? []);
        $check_duplicate_latest_post = !empty(array_intersect($first_ca, $second_ca));
        $findCA = !empty($findCA[0]) ? $findCA[0] : false;
        $prevPostCA =  isset($second_ca[0]) ? $second_ca[0] : "";
        
        //  latest_post have ca = buy | db buy_count = 1
        //  check db if buy_count = 1 && prev post haven't same used ca = cancel buy 

        print_r($findCA . ',' . $latestPostNotCA . ',' . $check_duplicate_latest_post . ',' . $prevPostCA);
    }

    // getGroupMessagesPumpFunInsider($groupIdPF);

} catch (Exception $e) {
    echo 'Errors: ' . $e->getMessage();
}
