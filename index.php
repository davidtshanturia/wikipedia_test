<?php
$revision_dir = dirname(__FILE__).DIRECTORY_SEPARATOR.'revisions';
$article_file = "0_0.txt";
$lock_key = 'atomic_lock_timeout';
$text_key = "text_key";
$revision_key = "latest_revision";
$max_size = 20000;
$max_revisions = 10;
$message='';
$last_revision_lines = 0;

$mem = new Memcached;
$mem->addServer("127.0.0.1", 11211);

function fibonacci($n, $val=1, $prev=0) {
	if ($n == 0) return $prev;
	if ($n == 1) return $val;
	return fibonacci($n-1, $val+$prev, $val);
}
function acquireLock($try = 15, $expire = 100) {
	global $mem, $lock_key;

	$i=0;
	$lock = $mem->add($lock_key, 1, $expire);
	while($lock == FALSE && $i<=$try){
		sleep(1);
		$lock = $mem->add($lock_key, 1, $expire);
		$i++;
	}
	if($lock == FALSE) return FALSE;
	return TRUE;
}
function releaseLock() {
	global $mem, $lock_key;
	$mem->delete($lock_key);
}
function getNewId(){
	global $revision_dir;
	$count = 0;
	try{
		$dh  = opendir($revision_dir);
		while (false !== ($filename = readdir($dh))) {
			if ($filename != '.' && $filename!= '..')
			$count++;
		}
		closedir($dh);
	}catch(Exception $e){return FALSE;}

	return $count;
}
function getAppended($fname, $tmpfile){
	global $revision_dir;
	try{
		$arr = explode('_', $fname);
		$bytes = intval($arr[1]);
		$fp = fopen($tmpfile, 'r');
		fseek($fp, $bytes);
		$data = fread($fp, filesize($tmpfile));
		fclose($fp);
		return strip_html($data);

	}catch(Exception $e){return FALSE;}
}
function merge_revisions(){
	global $revision_dir;
	$data = "";
	try{
		$dh  = opendir($revision_dir);
		while (false !== ($filename = readdir($dh))) {
			if ($filename != '.' && $filename!= '..')
			$data .= file_get_contents($revision_dir.DIRECTORY_SEPARATOR.$filename);
		}
		closedir($dh);
	}catch(Exception $e){return FALSE;}
	return $data;
}
function strip_html($html){
	return preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
}
function uploadFile(){

	global $max_size, $max_revisions, $revision_dir, $revision_key, $text_key, $mem;

	try{

		$temp = explode(".", $_FILES["file"]["name"]);
		$extension = end($temp);
		$fname = $temp[0];

		if ($_FILES["file"]["type"] != "text/plain" || $_FILES["file"]["size"] > $max_size || $extension!='txt')
		return "Invalid file! Please check that your file is .txt text file and is less then ".$max_size." bytes.";

		if ($_FILES["file"]["error"] > 0) return "Return Code: " . $_FILES["file"]["error"];

		$locked = acquireLock();
		if(!$locked) return "Could not acquire lock! Please try again.";

		$new_id = getNewId();
		if($new_id === FALSE) return "Unexpected error occured! Please try again";
		if( $new_id >= $max_revisions ) return "It is already ".$max_revisions." revisions of the text";

		$appended = getAppended($fname, $_FILES["file"]["tmp_name"]);
		if($appended === FALSE) return "Unexpected error occured! Please try again.";

		$merged = merge_revisions();
		if($merged === FALSE) return "Could not merge revisions";

		$new_revision = $revision_dir.DIRECTORY_SEPARATOR.$new_id.'_'.strlen($merged.$appended).'.txt';
		$created = file_put_contents($new_revision, $appended);
		if($created === FALSE) return "Could not save uploaded file to appropriate directory";

		$merged = merge_revisions();
		if($merged === FALSE) return "Could not merge revisions";

		$mem->delete($text_key);
		$mem->delete($revision_key);
		return TRUE;

	}catch(Exception $e){
		return $e->getMessage();
	}
}

if(!empty($_FILES)){

	$msg = uploadFile();
	releaseLock();

	if($msg !== TRUE) $message = $msg;
	else $message = "Uploaded Successfully";

	$message.=' - <a href="index.php">close</a>';
}

$content = $mem->get($text_key);
$article_file = $mem->get($revision_key);
if($content === FALSE || $article_file === FALSE){
	$content = merge_revisions();
	$mem->add($text_key, $content);
	@file_put_contents('article_merged.txt', $content);

	$dh  = opendir($revision_dir);
	while (false !== ($filename = readdir($dh))) {
		if ($filename != '.' && $filename!= '..')
		$revisions[] = $filename;
	}
	closedir($dh);

	rsort($revisions);
	$article_file = $revisions[0];
	$mem->add($revision_key, $revisions[0]);
}
?>

<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>Latest Plane Crash</title>
</head>
<body>

	<h4 style="color: red; text-align: center;">
	<?=$message?>
	</h4>
	<h1>Latest Plane Crash</h1>
	<h2>
	<?=$article_file?>
		[ <a href="article_merged.txt" download="<?=$article_file?>">Download</a>
		|
		<form action="index.php" method="post" enctype="multipart/form-data"
			style="display: inline; background: #ccc; padding: 5px;">
			<input type="file" name="file" id="file" style="width: 200px;" /> <input
				type="submit" name="submit" value="Upload File" />
		</form>
		]
	</h2>
	<?php
	echo $content;
	echo '<br/><br/><hr/>';
	echo '<h2>Fibonacci(34): '.fibonacci(34).'</h2>';
	?>
	<br />
	<br />
	<hr />
</body>
</html>
