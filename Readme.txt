純PHP 簡易線上聊天室[websocket chat] 範例

資料來源: https://github.com/sanwebe/Chat-Using-WebSocket-and-PHP-Socket

使用WINDOWS的USBWebserver_v8.6環境下測試可用

SERVER(server.php)

CLIENT(index.php)

-----

REM 網頁啟動 http://localhost:8080/PHP_websocket/server.php
REM 網頁PORT和 SERVER SOCKET PORT要不一致
REM SERVER.php
	REM $host = 'localhost'; //host
	REM $port = '9000'; //port
REM Clinet.php 內的連結 ws://localhost:9000/PHP_websocket/server.php


Code01 - 修正命名/定義路徑，確認上述REM的相關資訊
Code02 - 新增中文註解