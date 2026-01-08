<?php
include ('./config.php');
include ('./functions.php');
ini_set('max_execution_time', 30);

$MySQLi = new mysqli('localhost',$DB['username'],$DB['password'],$DB['dbname']);
$MySQLi->query("SET NAMES 'utf8'");
$MySQLi->set_charset('utf8mb4');
if ($MySQLi->connect_error) die;


$getDB = mysqli_fetch_assoc(mysqli_query($MySQLi, "SELECT * FROM `sending` LIMIT 1"));
if(!$getDB){
    $MySQLi->close();
    die;
}

// Use prepared statement for OFFSET to prevent SQL injection
$offset = (int) $getDB['count'];
$stmt = $MySQLi->prepare("SELECT `id` FROM `users` LIMIT 100 OFFSET ?");
$stmt->bind_param("i", $offset);
$stmt->execute();
$result = $stmt->get_result();
$getUsers = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$plus = $offset + 100;
$stmt = $MySQLi->prepare("UPDATE `sending` SET `count` = ? LIMIT 1");
$stmt->bind_param("i", $plus);
$stmt->execute();
$stmt->close();


if($getDB['type'] == 'send'){
foreach($getUsers as $user){
LampStack('copyMessage',[
'chat_id' => $user['id'],
'from_chat_id' => $getDB['chat_id'],
'message_id' => $getDB['msg_id']
]);
usleep(200000);
}
}
if($getDB['type'] == 'forward'){
foreach($getUsers as $user){
LampStack('ForwardMessage',[
'chat_id' => $user['id'],
'from_chat_id' => $getDB['chat_id'],
'message_id' => $getDB['msg_id']
]);
usleep(200000);
}
}

$ToCheck = $MySQLi->query("SELECT `id` FROM `users`")->num_rows;
if($plus >= $ToCheck){
foreach($admins_user_id as $id){
LampStack('sendmessage',[
'chat_id'=> $id,
'text'=> 'Send|Forward operation to all users successfully completed ✅',
]);
usleep(100000);
}
$MySQLi->query("DELETE FROM `sending` WHERE `type` = 'send' OR `type` = 'forward'");
}

$MySQLi->close();
die;