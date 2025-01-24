<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
        <title>Robot Trading</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <script src="https://terminal.jup.ag/main-v3.js"></script>
    </head>
    <style>
        body {
            background-color: #f2f2f2;
        }
        text {
            display: none;
        }
        .custom-loader {
            position: absolute;
            top: 35%;
            left: 45%;
            width: 35px;
            height: 35px;
            display: grid;
            animation: s4 4s infinite;
        }
        .custom-loader::before,
        .custom-loader::after {    
            content:"";
            grid-area: 1/1;
            border:6px solid;
            border-radius: 50%;
            border-color: #1971F4 #1971F4 #0000 #0000;
            mix-blend-mode: darken;
            animation: s4 1s infinite linear;
        }
        .custom-loader::after {
            border-color:#0000 #0000 #E4E4ED #E4E4ED;
            animation-direction: reverse;
        }
        @keyframes s4{ 
            100% {
                transform: rotate(1turn);
            }
        }
    </style>
    <body>
        <div class="container-sm position-relative" style="height: 100vh;">
            <div class="d-flex align-items-center justify-content-between position-absolute" style="top: 50%; left: 50%; transform: translate(-50%, -50%);">
                <button class="btn btn-sm btn-danger d-none" id="pause_btn" onclick="pauseRealTimeData(this);">
                    <i class="bi bi-pause-fill" style="font-size: 15px;"></i>
                </button>
                <button class="btn btn-sm btn-success" id="play_btn" onclick="startRealTimeData(this);">
                    <i class="bi bi-play-fill" style="font-size: 15px;"></i>
                </button>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
        <script src='https://terminal.jup.ag/main-v3.js' data-preload defer></script>
        <script type="module" src="./jupiter.js"></script>
        <script>

            let interval_ID = null;
            let api_paused = false;

            function fetchLatestMessage() {
                if (api_paused) return;

                fetch('http://localhost:8000/telegram.php')
                .then(response => response.text())
                .then(data => {
                    if (!api_paused) {
                        const contract_address = regexPost(data);
                        trojanManagement(contract_address);
                    }
                }).catch(err => console.error(err));
            }

            function regexPost(data) {
                const contract_address = data.split('\n')[data.split('\n').length - 1].trim();
                const split_ca = contract_address.split(',');

                return {
                    "ca": split_ca[0],
                    "latest_post": split_ca[1],
                    "duplicate_latest_post": split_ca[2],
                };
            }

            function startRealTimeData(element) {
                const pause_btn = $(element).siblings('#pause_btn');
                
                if ($(pause_btn).hasClass('d-none')) {
                    $(pause_btn).removeClass('d-none');
                    $(element).addClass('d-none');

                    api_paused = false;
                    interval_ID = setInterval(fetchLatestMessage, 1000);
                    
                    console.log("Real Time Data: 🟢");
                }
            }
            
            function pauseRealTimeData(element) {
                const play_btn = $(element).siblings('#play_btn');
                
                if ($(play_btn).hasClass('d-none')) {
                    api_paused = true;
                    $(play_btn).removeClass('d-none');
                    $(element).addClass('d-none');

                    api_paused = true;
                    clearInterval(interval_ID);
                    interval_ID = null;
                    
                    console.log("Real Time Data: 🔴");
                }
            }

            let api_called_count = 0;

            function trojanManagement(CA) {
                const contract_address = CA.ca;
                const check_latest_post = CA.latest_post;
                const check_duplicate_latest_post = CA.duplicate_latest_post;
                api_called_count += 1;
                
                fetch('http://localhost:8000/trojan.php', {
                    method: "POST",
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({contract_address, check_latest_post, check_duplicate_latest_post}),
                })
                .then(response => response.text())
                .then(data => {
                    console.log(data.split('\n')[data.split('\n').length - 1].trim());
                    console.log(`API CALLED: ${api_called_count}`);
                }).catch(err => console.error(err));
            }

            window.addEventListener("DOMContentLoaded", fetchLatestMessage);
        </script>
    </body>
</html>