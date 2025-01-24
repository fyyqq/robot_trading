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

    function getGroupMessages($groupId) {
        global $MadelineProto;

        $messages = $MadelineProto->messages->getHistory([
            'peer' => $groupId,
            // 'filter' => ['topic_id' => $topicId],
            'offset_id' => 0,
            'limit' => 100,
        ]);
        
        if (!empty($messages['messages'])) {
            $latest_post = [];
            foreach ($messages['messages'] as $message) {
                // if (isset($message['message']) && strpos($message["message"], $targetPhrase) !== false) {}
        
                $each_message = explode("\n", $message['message']);
                $latest_post[] = [
                    'post' => $each_message,
                    'datetime' => date('d/m/Y H:i:s', $message['date']),
                ];
            }
            return $latest_post;
        } else {
            return null;
        }
    }

    $latest_post = getGroupMessages($groupId);
    $findCA = [];
    $latestPostNotCA = true;

    echo "\n";
    foreach ($latest_post[0]['post'] as $post) {
        if (strlen($post) == 44 && ctype_alnum($post)) {
            $latestPostNotCA = false;
        }
    }
    
    foreach ($latest_post as $entries) {
        foreach ($entries['post'] as $entry) {
            if (strlen($entry) == 44 && ctype_alnum($entry)) {
                array_push($findCA, $entry);
            }
        }
    }

    function filterCAEntries($array) {
        return array_filter($array, function($entry) {
            return strlen($entry) == 44 && ctype_alnum($entry);
        });
    }

    $newest_post = $latest_post[0]['post'];
    $prev_post = $latest_post[1]['post'];
    
    $first_ca = filterCAEntries($newest_post);
    $second_ca = filterCAEntries($prev_post);
    
    $result = !empty(array_intersect($first_ca, $second_ca));
    $notDuplicateLatestPost = $result; // true = duplicate CA | false = allowed to buy

    $latestCA = $findCA[0];
    print_r($latestCA . ',' . $latestPostNotCA . ',' . $notDuplicateLatestPost);

} catch (Exception $e) {
    echo 'Errors: ' . $e->getMessage();
}
