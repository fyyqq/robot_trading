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

$groupId = $_ENV["TELEGRAM_GROUP_ID"]; // Meow Private Channel
// $topicId = '10';

try {
    // $result = $MadelineProto->channels->joinChannel(['channel' => $groupId]);

    function getGroupMessages($groupId, $limit = 10) {
        global $MadelineProto;

        $messages = $MadelineProto->messages->getHistory([
            'peer' => $groupId,
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

        return $latest_post;
    }

    $latest_post = getGroupMessages($groupId);
    $findCA = [];
    $latestPostNotCA = true;

    echo "\n";

    function filterCAEntries($array) {
        return array_filter($array, function($entry) {
            return strlen($entry) == 44 && ctype_alnum($entry);
        });
    }

    foreach ($latest_post as $entries) {
        foreach ($entries['post'] as $entry) {
            if (strlen($entry) == 44 && ctype_alnum($entry)) {
                $findCA[] = $entry;
                
                if ($entries === $latest_post[0]) {
                    $latestPostNotCA = false;
                }
            }
        }
    }

    $first_ca = filterCAEntries($latest_post[0]['post'] ?? []);
    $second_ca = filterCAEntries($latest_post[1]['post'] ?? []);

    print_r($findCA[0] . ',' . $latestPostNotCA . ',' . !empty(array_intersect($first_ca, $second_ca)));

} catch (Exception $e) {
    echo 'Errors: ' . $e->getMessage();
}
