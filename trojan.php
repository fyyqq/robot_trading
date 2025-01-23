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

// Create Connection DB
$server_name = "localhost";
$user_name = "root";
$password = "";
$db_name = "bot_trade";

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
        // $ca = json_decode(file_get_contents('php://input'), true);
        $ca = "AqHusHSkyokKs7wTY6YxikQ24gomqE5HF96jy9DMpump";
        header('Content-Type: application/json');

        return $ca;
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
        $ca = $this->getCA();

        $sql = "SELECT * FROM trade_history WHERE user_id = ? AND contract_address = ?";
        $stmt = self::$connect->prepare($sql);
        $stmt->bind_param("ss", $user_id, $ca);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $buy_count_on_existing_trade = null;
        $add_buy_count_on_existing_trade = false;
        
        if (!empty($result->num_rows)) {
            $buy_count_on_existing_trade = $result->fetch_all(MYSQLI_ASSOC)[0]["buy_count"];

            if (empty($result->num_rows) || $buy_count_on_existing_trade == 1) {
                global $add_buy_count_on_existing_trade;
                
                if (empty($result->num_rows)) {
                    $this->allowed_buy = true;
                } else if ($buy_count_on_existing_trade == 1) {
                    $this->allowed_buy = true;
                    $add_buy_count_on_existing_trade = true;
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
        $MadelineProto->messages->sendMessage([
            'peer' => $this->chatId,
            'message' => "/start",
        ]);

        sleep(1);

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
        if ($balance_wallet >= 22 && $allowed_buy && $callback_api) {
            $user_id = $this->getUserID();
            $ca = $this->getCA();

            $MadelineProto->messages->sendMessage([
                'peer' => $this->chatId,
                'message' => "/buy",
            ]);

            sleep(1);
        
            $MadelineProto->messages->sendMessage([
                'peer' => $this->chatId,
                'message' => $ca,
            ]);

            sleep(1);

            for ($i = 0; $i < 10; $i++) {
                sleep(2);

                $buy_reply = $MadelineProto->messages->getHistory([
                    'peer' => $this->chatId,
                    'offset_id' => 0,
                ]);

                $message_response = $buy_reply['messages'][0]['message'];
                if (strpos($message_response, 'Buy Success!') !== false) {
                    echo "🟢 Buy Success!\n";
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

                    echo "\nAmount: $amount_buy\n";

                    sleep(1);

                    // Second Buy
                    if ($add_buy_count) {
                        $buy_second_trade = $this->buySecondTrade($ca, $amount_buy, $buy_count);
                        if ($buy_second_trade) {
                            $this->buyValidation($MadelineProto);
                        }
                    } else {
                        // First Buy
                        $buy_first_trade = $this->buyNewTrade($user_id, $ca, $amount_buy);
                        if ($buy_first_trade) {
                            $this->buyValidation($MadelineProto);
                        }
                    }
                } else {
                    echo "🔴 Buy Failed!.\n";
                }
                break;
            }

            sleep(1);
        } else {
            if (!$allowed_buy && !$callback_api) {
                echo "🔴 Already Bought Limit\n";
            } else {
                echo !$allowed_buy ? "🔴 Already Bought Limit\n" : "🔴 Insufficient Balance\n";
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

            $buy_count_on_existing_trade = $buy_count + 1;
            $update_trade_history = "UPDATE trade_history SET buy_count = $buy_count_on_existing_trade, buy_amount = $update_buy_amount WHERE contract_address = ?";
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
}

$ca = Trojan::getCA();

if (isset($ca)) {
    echo "\n";
    $trojan = new Trojan($server_name, $user_name, $password, $db_name);
    $user_id = $trojan->getUserID();
    list($buy_count, $add_buy_count, $allowed_buy, $callback_api) = $trojan->checkExistingTrade();

    if ($allowed_buy) {
        list($balance, $address_wallet) = $trojan->Start($MadelineProto);
        $check_buy_result = $trojan->Buy($balance, $allowed_buy, $callback_api, $buy_count, $add_buy_count, $MadelineProto);
    } else {
        if (!$allowed_buy && !$callback_api) {
            echo "🔴 Already Bought Limit\n";
        } else {
            echo "🔴 Insufficient Balance\n";
        }
    }
} else {
    echo "🔴 PHP: CA has a problem";
}

