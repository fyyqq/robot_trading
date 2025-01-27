<?php

// PROBLEM NEED TO SOLVE
// 1) Buy 2 times when calling api same ca
// 2) Solve = ???

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

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$settings = new Settings();

$settings->setAppInfo((new AppInfo)
->setApiId($_ENV['TELEGRAM_API_ID'])
->setApiHash($_ENV['TELEGRAM_API_HASH']));

$MadelineProto = new API('session.madeline', $settings);
$MadelineProto->start();

// Create Connection DB
$server_name = $_ENV["DB_SERVER_NAME"];
$user_name = $_ENV["DB_USER_NAME"];
$password = $_ENV["DB_PASSWORD"];
$db_name = $_ENV["DB_NAME"];

Class Trojan {
    private static $connect;
    private $chatId = '@solana_trojanbot';
    private $allowed_buy = false;
    private $callback_api = true;
    private $buy_validation = false;

    public function __construct($server_name, $user_name, $password, $db_name) {
        self::$connect = mysqli_connect($server_name, $user_name, $password, $db_name);

        if (mysqli_connect_errno()) {
            echo "\nConnection Failed: \n";
            print_r(self::$connect->connect_error);
            exit();
        }
    }

    public static function getCA() {
        $ca = json_decode(file_get_contents('php://input'), true);
        // $ca = "7Wwc9zTimb3aGottnAte8LkGUpV8sv3xcnLnN4Espump";
        header('Content-Type: application/json');

        
        $result = new stdClass();
        // $result->ca = '3d9vSXzJhfD1vUFuJab5oqG1ZXdDsFdY4jQjEBmndcpE';
        // $result->latest_post_not_ca = false;
        // $result->check_duplicate_latest_post = true;
        $result->ca = $ca['contract_address'];
        $result->latest_post_not_ca = filter_var($ca["check_latest_post"], FILTER_VALIDATE_BOOLEAN); // true = latest post have CA | false = latest post don't have CA
        $result->check_duplicate_latest_post = filter_var($ca["check_duplicate_latest_post"], FILTER_VALIDATE_BOOLEAN); // true = duplicate CA on 2 latest post | false = allowed to buy
        $result->check_prev_post_ca = $ca["check_prev_post_ca"];

        return $result;
    }

    public function getUserID() { 
        $find_user_sql = "SELECT * FROM users where id = 1";
        $result = mysqli_query(self::$connect, $find_user_sql);
        $user_id = null;

        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                global $user_id;
                $user_id = $row["id"];
            }
        }

        return $user_id;
    }

    public function checkExistingTrade() {
        $user_id = $this->getUserID();
        $ca_information = $this->getCA();

        $sql = "SELECT * FROM trade_history WHERE user_id = ? AND contract_address = ?";
        $stmt = self::$connect->prepare($sql);
        $stmt->bind_param("ss", $user_id, $ca_information->ca);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $buy_count_on_existing_trade = null;
        $add_buy_count_on_existing_trade = false;

        if (strlen($ca_information->check_prev_post_ca) > 5) {
            $sql = "UPDATE trade_history SET allow_second_buy = 1 WHERE user_id = ? AND contract_address = ?";
            $stmt = self::$connect->prepare($sql);
            $stmt->bind_param("ss",$user_id, $ca_information->check_prev_post_ca);

            if ($stmt->execute()) {
                $success_message = "🟢 Allow Second Buy!";
                $this->writeToLog('Response: ' . $success_message, 'success');
            } else {
                $error_message = "🔴 Error: " . $stmt->error;
                $this->writeToLog('Response: ' . $error_message, 'error');
            }
        }

        $sql = "SELECT * FROM trade_history WHERE user_id = ? AND contract_address = ?";
        $stmt = self::$connect->prepare($sql);
        $stmt->bind_param("ss", $user_id, $ca_information->check_prev_post_ca);
        $stmt->execute();
        $result_for_allow_second_buy = $stmt->get_result();
        
        if (!empty($result->num_rows)) {
            $row = $result->fetch_all(MYSQLI_ASSOC)[0];
            $buy_count_on_existing_trade = $row["buy_count"];
            $allow_second_buy = $result_for_allow_second_buy->fetch_all(MYSQLI_ASSOC)[0]['allow_second_buy'];

            if (empty($result->num_rows) || $buy_count_on_existing_trade == 1) {
                global $add_buy_count_on_existing_trade;
                
                // No Buy Yet.
                if (empty($result->num_rows)) {
                    $this->allowed_buy = true;
                } else if ($buy_count_on_existing_trade == 1) {
                    // Already Buy Once.
                    if ($allow_second_buy) {
                        $this->allowed_buy = true;
                        $add_buy_count_on_existing_trade = true;
                    } else {
                        $this->allowed_buy = false;
                    }
                } else {
                    $this->allowed_buy = false;
                    $this->callback_api = false;
                }
            } else {
                $this->callback_api = false;
            }
        } else {
            $this->allowed_buy = true;
        }

        return [$buy_count_on_existing_trade, $add_buy_count_on_existing_trade, $this->allowed_buy, $this->callback_api];
    }

    public function Start($MadelineProto) {
        usleep(500000);
        
        $MadelineProto->messages->sendMessage([
            'peer' => $this->chatId,
            'message' => "/start",
        ]);
        
        usleep(500000);

        $start_reply = $MadelineProto->messages->getHistory([
            'peer' => $this->chatId,
            'offset_id' => 0,
            'limit' => 1,
        ]);

        $message_list = explode("\n", $start_reply["messages"][0]['message']);
        $my_address_wallet = explode(" ", $message_list[1])[0];
        $balance_wallet = str_replace(['(', ')', '$'], '', explode(" ",$message_list[2])[3]);

        return [$balance_wallet, $my_address_wallet];
    }

    public function Buy($balance_wallet, $allowed_buy, $callback_api, $buy_count, $add_buy_count, $MadelineProto) {
        $ca_information = $this->getCA();

        if ($balance_wallet >= 22 && $allowed_buy && $callback_api) {
            // global $ca_information;
            $user_id = $this->getUserID();

            usleep(500000);

            $MadelineProto->messages->sendMessage([
                'peer' => $this->chatId,
                'message' => "/buy",
            ]);

            usleep(500000);
        
            $MadelineProto->messages->sendMessage([
                'peer' => $this->chatId,
                'message' => $ca_information->ca,
            ]);

            sleep(5);

            for ($i = 0; $i < 10; $i++) {
                sleep(2);

                $buy_reply = $MadelineProto->messages->getHistory([
                    'peer' => $this->chatId,
                    'offset_id' => 0,
                ]);

                $message_response = $buy_reply['messages'][0]['message'];
                if (strpos($message_response, 'Buy Success!') !== false) {
                    $success_message = "🟢 Buy Success!\n";
                    $this->writeToLog('Response: ' . $success_message, 'success');
                    $this->buy_validation = true;

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

                    $success_message = "\nAmount: $amount_buy\n";
                    $this->writeToLog('Response: ' . $success_message, 'success');
                    sleep(2);
                    
                    // Second Buy
                    if ($add_buy_count) {
                        $buy_second_trade = $this->buySecondTrade($ca_information->ca, $amount_buy, $buy_count);
                        if ($buy_second_trade) {
                            $this->buyValidation($MadelineProto);
                        }
                    } else {
                        // First Buy
                        $buy_first_trade = $this->buyNewTrade($user_id, $ca_information->ca, $amount_buy);
                        if ($buy_first_trade) {
                            $this->buyValidation($MadelineProto);
                        }
                    }
                } else {
                    $error_message = "🔴 Buy Failed!.\n";
                    $this->writeToLog('Response: ' . $error_message, 'error');
                }
                break;
            }
        } else {
            if (!$allowed_buy && !$callback_api) {
                $error_message = "🔴 Already Bought Limit\n";
                $this->writeToLog('Response: ' . $error_message, 'error');
            } else {
                $error_message =  "🔴 Insufficient Balance\n";
                $this->writeToLog('Response: ' . $error_message, 'error');
            }
        }
    }

    public function buyNewTrade($user_id, $ca, $amount_buy) {
        $buy_count = 1;
        $insert_trade = "INSERT INTO trade_history (user_id, contract_address, buy_amount, buy_count) VALUES ('$user_id', '$ca', '$amount_buy', '$buy_count')";

        if (!mysqli_query(self::$connect, $insert_trade)) {
            echo "Error: " . $insert_trade . "<br>" . mysqli_error(self::$connect);
        }

        return true;
    }

    public function buySecondTrade($ca, $amount_buy, $buy_count) {
        $find_trade = "SELECT * FROM trade_history WHERE contract_address = ?";
        $stmt = self::$connect->prepare($find_trade);
        $stmt->bind_param("s", $ca);
        $stmt->execute();
        $result = $stmt->get_result();
        $buy_amount_trade_history = $result->fetch_all(MYSQLI_ASSOC)[0]["buy_amount"];

        if (!empty($result->num_rows)) {
            $update_buy_amount = $amount_buy + $buy_amount_trade_history;
            $current_time_for_second_buy = date('Y-m-d H:i:s');

            $buy_count_on_existing_trade = $buy_count + 1;
            $update_trade_history = "UPDATE trade_history SET buy_count = $buy_count_on_existing_trade, buy_amount = $update_buy_amount, updated_at = '$current_time_for_second_buy' WHERE contract_address = ?";
            $update_stmt = self::$connect->prepare($update_trade_history);
            $update_stmt->bind_param("s", $ca);
            $execute_update = $update_stmt->execute();
        
            if ($execute_update) {
                print_r($update_stmt->error);
            }

            $update_stmt->close();

            return true;
        }
    }

    public function buyValidation($MadelineProto) {
        if ($this->buy_validation) {
            $MadelineProto->messages->sendMessage([
                'peer' => $this->chatId,
                'message' => "/sell",
            ]);

            sleep(1);

            $sell_reply = $MadelineProto->messages->getHistory([
                'peer' => $this->chatId,
                'offset_id' => 0,
            ]);

            $sell_reply_message = explode("\n", $sell_reply['messages'][0]['message']);
            $balance_wallet_after_buy = str_replace(['(', ')'], '', $sell_reply_message[1]);

            // $balance_wallet = $balance_wallet_after_buy;
            // $existing_bought_coins = array_slice($sell_reply_message, 2);
        }
    }

    public function writeToLog($message, $type = 'info') {
        $logFile = './log/message.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}

$ca = Trojan::getCA();

if (isset($ca->ca)) {
    echo "\n";
    $trojan = new Trojan($server_name, $user_name, $password, $db_name);
    $user_id = $trojan->getUserID();
    list($buy_count, $add_buy_count, $allowed_buy, $callback_api) = $trojan->checkExistingTrade();
    
    if ($allowed_buy && strlen($ca->ca) > 5 && !$ca->latest_post_not_ca && !$ca->check_duplicate_latest_post) { // Live Code
        // if ($allowed_buy) { // Testing Code
        list($balance, $address_wallet) = $trojan->Start($MadelineProto);
        $check_buy_result = $trojan->Buy($balance, $allowed_buy, $callback_api, $buy_count, $add_buy_count, $MadelineProto);
    } else {
        // print_r([$ca->ca, $ca->check_duplicate_latest_post, $ca->latest_post_not_ca]);
        if (!$allowed_buy && !$callback_api) {
            $error_message = "🔴 Already Bought Limit\n";
            $this->writeToLog('Response: ' . $error_message, 'success');
        } else if ($ca->latest_post_not_ca && !$ca->check_duplicate_latest_post && strlen($ca->ca) < 5) {
            $error_message = "🔴 No Signal Post Yet.";
            $this->writeToLog('Response: ' . $error_message, 'success');
        }
    }
} else {
    echo "🔴 PHP: CA has a problem";
}

