<?php
error_reporting(E_ALL);
set_time_limit(0);// 设置超时时间为无限,防止超时
date_default_timezone_set('Asia/Taipei');

$host = 'localhost'; //host
$port = '9000'; //port
$null = NULL; //null var

//Create TCP/IP sream socket [創建 TCP/IP 流式套接字]
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
//reuseable port [可重複使用的端口]
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

//bind socket to specified host [將套接字綁定到指定主機]
socket_bind($socket, 0, $port);

//listen to port [監聽端口]
socket_listen($socket);

//create & add listning socket to the list [創建並將列表套接字添加到列表中]
$clients = array($socket);

//start endless loop, so that our script doesn't stop [開始無限循環，這樣我們的腳本就不會停止]
while (true)
{
	//manage multipal connections [管理多個連接]
	$changed = $clients;
	//returns the socket resources in $changed array [返回 $changed 數組中的套接字資源]
	socket_select($changed, $null, $null, 0, 10);
	
	//check for new socket [檢查新的socket]
	if (in_array($socket, $changed))
	{
		$socket_new = socket_accept($socket); //accpet new socket [接受新的套接字]
		$clients[] = $socket_new; //add socket to client array [將套接字添加到客戶端數組]
		
		$header = socket_read($socket_new, 1024); //read data sent by the socket [讀取套接字發送的數據]
		perform_handshaking($header, $socket_new, $host, $port); //perform websocket handshake [執行 websocket 握手]
		
		socket_getpeername($socket_new, $ip); //get ip address of connected socket [獲取已連接套接字的 ip 地址]
		$response = mask(json_encode(array('type'=>'system', 'message'=>$ip.' connected'))); //prepare json data [準備json數據]
		send_message($response); //notify all users about new connection [通知所有用戶有關新連接的信息]
		
		//make room for new socket [為socket騰出空間]
		$found_socket = array_search($socket, $changed);
		unset($changed[$found_socket]);
	}
	
	//loop through all connected sockets [遍歷所有連接的套接字]
	foreach ($changed as $changed_socket)
	{	
		
		//check for any incomming data [檢查任何傳入數據]
		while(socket_recv($changed_socket, $buf, 1024, 0) >= 1)
		{
			$received_text = unmask($buf); //unmask data [取消屏蔽數據 ~ 解封包]
			$tst_msg = json_decode($received_text, true); //json decode 
			$user_name = $tst_msg['name']; //sender name
			$user_message = $tst_msg['message']; //message text
			$user_color = $tst_msg['color']; //color
			
			//prepare data to be sent to client [準備要發送給客戶端的數據]
			$response_text = mask(json_encode(array('type'=>'usermsg', 'name'=>$user_name, 'message'=>$user_message, 'color'=>$user_color)));
			send_message($response_text); //send data
			break 2; //exist this loop
		}
		
		$buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
		if ($buf === false) { // check disconnected client [檢查斷開連接的客戶端]
			// remove client for $clients array [刪除 $clients 數組的客戶端]
			$found_socket = array_search($changed_socket, $clients);
			socket_getpeername($changed_socket, $ip);
			unset($clients[$found_socket]);
			
			//notify all users about disconnected connection [通知所有用戶斷開連接]
			$response = mask(json_encode(array('type'=>'system', 'message'=>$ip.' disconnected')));
			send_message($response);
		}
	}
}
// close the listening socket [關閉監聽套接字]
socket_close($socket);

function send_message($msg)//傳送訊息函數
{
	global $clients;
	foreach($clients as $changed_socket)
	{
		@socket_write($changed_socket,$msg,strlen($msg));
	}
	return true;
}


//Unmask incoming framed message [取消屏蔽傳入的框架消息 ~ 解封包]
function unmask($text) {
	$length = ord($text[1]) & 127;
	if($length == 126) {
		$masks = substr($text, 4, 4);
		$data = substr($text, 8);
	}
	elseif($length == 127) {
		$masks = substr($text, 10, 4);
		$data = substr($text, 14);
	}
	else {
		$masks = substr($text, 2, 4);
		$data = substr($text, 6);
	}
	$text = "";
	for ($i = 0; $i < strlen($data); ++$i) {
		$text .= $data[$i] ^ $masks[$i%4];
	}
	return $text;
}

//Encode message for transfer to client.[編碼消息以傳輸給客戶端~加封包]
function mask($text)
{
	$b1 = 0x80 | (0x1 & 0x0f);
	$length = strlen($text);
	
	if($length <= 125)
		$header = pack('CC', $b1, $length);
	elseif($length > 125 && $length < 65536)
		$header = pack('CCn', $b1, 126, $length);
	elseif($length >= 65536)
		$header = pack('CCNN', $b1, 127, $length);
	return $header.$text;
}

//handshake new client. [握手新客戶 ~ WebSocket 第一次連線規定]
function perform_handshaking($receved_header,$client_conn, $host, $port)
{
	$headers = array();
	$lines = preg_split("/\r\n/", $receved_header);
	foreach($lines as $line)
	{
		$line = chop($line);
		if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
		{
			$headers[$matches[1]] = $matches[2];
		}
	}

	$secKey = $headers['Sec-WebSocket-Key'];
	$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
	//hand shaking header
	$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
	"Upgrade: websocket\r\n" .
	"Connection: Upgrade\r\n" .
	"WebSocket-Origin: $host\r\n" .
	"WebSocket-Location: ws://$host:$port/demo/shout.php\r\n".
	"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
	socket_write($client_conn,$upgrade,strlen($upgrade));
}
