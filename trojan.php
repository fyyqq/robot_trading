<?php

// PROBLEM NEED TO SOLVE
// 1) JS CA already execute for 2 times buy
// 2) Buy 2 times even not sell yet

// php -S localhost:8000

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

use danog\MadelineProto\API;
use danog\MadelineProto\EventHandler;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

require './vendor/autoload.php';

date_default_timezone_set('Asia/Singapore'); // GMT+8

$settings = new Settings();

$settings->setAppInfo((new AppInfo)
->setApiId(24654333)
->setApiHash("6e49582d42bdeee87199ef227170a746"));

$MadelineProto = new API('session.madeline', $settings);
$MadelineProto->start();

// $ca = json_decode(file_get_contents('php://input'), true);
$ca = "AqHusHSkyokKs7wTY6YxikQ24gomqE5HF96jy9DMpump";
header('Content-Type: application/json');

// Create Connection DB
$server_name = "localhost";
$user_name = "root";
$password = "";
$db_name = "bot_trade";
$connect = mysqli_connect($server_name, $user_name, $password, $db_name);

try {
    function sendMessage($MadelineProto, $ca) {
        $chatId = '@solana_trojanbot';
        global $connect;
        echo "\n";

        $start = $MadelineProto->messages->sendMessage([
            'peer' => $chatId,
            'message' => "/start",
        ]);

        sleep(1);

        $reply = $MadelineProto->messages->getHistory([
            'peer' => $chatId,
            'offset_id' => 0,
            'limit' => 1,
        ]);

        $message_list = explode("\n", $reply["messages"][0]['message']);
        $my_address_wallet = explode(" ", $message_list[1])[0];
        $balance_wallet = str_replace(['(', ')', '$'], '', explode(" ",$message_list[2])[3]);

        $table = "trade_history";
        $columnsToCheck = ["user_id", "contract_address"];
        
        //// FIND TRADER ////
        $find_user_sql = "SELECT * FROM users where id = 1";
        $result = mysqli_query($connect, $find_user_sql);
        $user_id = null;
        
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                global $user_id;
                $user_id = $row["id"];
            }
        }
        
        //// FIND EXISTING TRADE ////
        $sql = "SELECT * FROM $table WHERE $columnsToCheck[0] = ? AND $columnsToCheck[1] = ?";
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("ss", $user_id, $ca);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $buy_count_on_existing_trade = null;
        $allowed_buy = false;
        $add_buy_count_on_existing_trade = false;
        
        if (!empty($result->num_rows)) {
            $buy_count_on_existing_trade = $result->fetch_all(MYSQLI_ASSOC)[0]["buy_count"];

            if (empty($result->num_rows) || $buy_count_on_existing_trade == 1) {
                global $add_buy_count_on_existing_trade;
                
                if (empty($result->num_rows)) {
                    $allowed_buy = true;
                } else if ($buy_count_on_existing_trade == 1) {
                    $allowed_buy = true;
                    $add_buy_count_on_existing_trade = true;
                } else {
                    $allowed_buy = false;
                }
            }
        } else {
            $allowed_buy = true;
        }

        if ($balance_wallet >= 22 && $allowed_buy) {
            global $add_buy_count_on_existing_trade;
            global $user_id;
            global $ca;

            $buy = $MadelineProto->messages->sendMessage([
                'peer' => $chatId,
                'message' => "/buy",
            ]);

            sleep(1);
            
            $send_buy = $MadelineProto->messages->sendMessage([
                'peer' => $chatId,
                'message' => $ca,
            ]);

            sleep(1);
            $buy_validation = false;

            for ($i = 0; $i < 10; $i++) {
                sleep(2);

                $reply_buy = $MadelineProto->messages->getHistory([
                    'peer' => $chatId,
                    'offset_id' => 0,
                ]);

                $message_response = $reply_buy['messages'][0]['message'];
                if (strpos($message_response, 'Buy Success!') !== false) {
                    echo "🟢 Buy Success!\n";
                    global $buy_validation;
                    $buy_validation = true;

                    //// INSERT NEW TRADE //// 

                    $pattern = '/🟢 Fetched Quote \(RaydiumAMM\)[\s\S]*?Price Impact: [\d\.]+%/';
                    $amount_buy = null;

                    if (preg_match($pattern, $message_response, $matches)) {
                        $result = $matches[0];
                        preg_match_all('/\$\d+(\.\d+)?/', $result, $matches);
                        $amount_buy = str_replace(['$'], '', $matches[0][0]);
                    } else {
                        $result = "\n" . explode("\n", $message_response)[10];
                        $result = array_slice(explode(" ", $result), -1)[0];
                        $amount_buy = str_replace(['(', ')', '$'], '', $result);
                    }

                    echo "\nAmount: $amount_buy\n";

                    sleep(1);

                    // Second Buy
                    if ($add_buy_count_on_existing_trade) {
                        $find_trade = "SELECT * FROM trade_history WHERE contract_address = ?";
                        $stmt = $connect->prepare($find_trade);
                        $stmt->bind_param("s", $ca);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $buy_amount_trade_history = $result->fetch_all(MYSQLI_ASSOC)[0]["buy_amount"];

                        if (!empty($result->num_rows)) {
                            $update_buy_amount = $amount_buy + $buy_amount_trade_history;

                            $buy_count_on_existing_trade = $buy_count_on_existing_trade + 1;
                            $update_trade_history = "UPDATE trade_history SET buy_count = $buy_count_on_existing_trade, buy_amount = $update_buy_amount WHERE contract_address = ?";
                            $update_stmt = $connect->prepare($update_trade_history);
                            $update_stmt->bind_param("s", $ca);
                            $execute_update = $update_stmt->execute();
                        
                            if ($execute_update) {
                                print_r($update_stmt->error);
                            }

                            $update_stmt->close();
                        }
                    } else {
                        // First Buy
                        $buy_count = 1;
                        $insert_trade = "INSERT INTO trade_history (user_id, contract_address, buy_amount, buy_count) VALUES ('$user_id', '$ca', '$amount_buy', '$buy_count')";
    
                        if (!mysqli_query($connect, $insert_trade)) {
                            echo "Error: " . $insert_trade . "<br>" . mysqli_error($connect);
                        }
                    }
                } else {
                    echo "🔴 Buy Failed!.\n";
                }
                break;
            }

            sleep(1);

            if ($buy_validation) {
                $sell = $MadelineProto->messages->sendMessage([
                    'peer' => $chatId,
                    'message' => "/sell",
                ]);

                sleep(1);

                $reply_sell = $MadelineProto->messages->getHistory([
                    'peer' => $chatId,
                    'offset_id' => 0,
                ]);

                $sell_reply_message = explode("\n", $reply_sell['messages'][0]['message']);
                $balance_wallet_after_buy = str_replace(['(', ')'], '', $sell_reply_message[1]);

                // $balance_wallet = $balance_wallet_after_buy;
                // $existing_bought_coins = array_slice($sell_reply_message, 2);
            }

        } else {
            echo !$allowed_buy ? "🔴 Already Bought\n" : "🔴 Balance Low\n";
        }
    }

    if (isset($ca)) {
        $check_existsing_ca = [];

        if (!in_array($ca, $check_existsing_ca)) {
            array_push($check_existsing_ca, $ca);
            sendMessage($MadelineProto, $ca);
        } else {
            echo "🔴 PHP: CA already execute !";
        }
    } else {
        echo "🔴 PHP: CA has a problem";
    }

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}