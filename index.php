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
        .navbar {
            background-color: #fff;
        }
        #query_container {
            height: 150px;
            position: relative;
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
        <nav class="navbar navbar-light bg-light shadow-sm p-3">
            <div class="container-sm">
                <span class="navbar-brand mb-0 h1">Navbar</span>
            </div>
        </nav>
        <div class="container-sm my-5">
            <h1>Memecoin Robot Trading</h1>
            <div class="row mt-4">
                <div class="border col-6" style="height: max-content;">
                    <div class="border-bottom p-3 d-flex align-items-center justify-content-between">
                        <p class="mb-0 fw-bold">Latest Coin</p>
                        <button class="btn btn-sm btn-danger d-none" id="pause_btn" onclick="pauseRealTimeData(this);">
                            <i class="bi bi-pause-fill" style="font-size: 15px;"></i>
                        </button>
                        <button class="btn btn-sm btn-success" id="play_btn" onclick="startRealTimeData(this);">
                            <i class="bi bi-play-fill" style="font-size: 15px;"></i>
                        </button>
                    </div>
                    <div id="query_container" class="p-4">
                        <div class="custom-loader"></div>
                    </div>
                </div>
                <div class="col-6" style="height: max-content;">
                    <div class="border w-100" id="integrated-terminal" style="width: 400px; height: 568px;"></div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
        <script src='https://terminal.jup.ag/main-v3.js' data-preload defer></script>
        <script type="module" src="./jupiter.js"></script>
        <script>

            let interval_ID = null;

            function fetchLatestMessage() {
                fetch('http://localhost:8000/telegram.php')
                .then(response => response.text())
                .then(data => {
                    const contract_address = regexPost(data);
                    trojanManagement(contract_address)
                }).catch(err => console.error(err));
            }

            function regexPost(data) {
                const contract_address = data.split('\n')[data.split('\n').length - 1].trim();
                return {data: contract_address};
            }

            function startRealTimeData(element) {
                const pause_btn = $(element).siblings('#pause_btn');
                
                if ($(pause_btn).hasClass('d-none')) {
                    $(pause_btn).removeClass('d-none');
                    $(element).addClass('d-none');
                    interval_ID = setInterval(fetchLatestMessage, 1000);
                    console.log("Real Time Data: 🟢");
                }
            }
            
            function pauseRealTimeData(element) {
                const play_btn = $(element).siblings('#play_btn');
                
                if ($(play_btn).hasClass('d-none')) {
                    $(play_btn).removeClass('d-none');
                    $(element).addClass('d-none');
                    console.log("Real Time Data: 🔴");

                    if (interval_ID !== null) {
                        clearInterval(interval_ID);
                        interval_ID = null;
                    }
                }
            }

            function trojanManagement(CA) {
                const contract_address = CA.data;
                
                fetch('http://localhost:8000/trojan.php', {
                    method: "POST",
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(contract_address),
                })
                .then(response => response.text())
                .then(data => {
                    console.log(`${data}\n`);
                }).catch(err => console.error(err));
            }

            window.addEventListener("DOMContentLoaded", fetchLatestMessage);
        </script>
    </body>
</html>