<?php
$revision_dir = dirname(__FILE__).DIRECTORY_SEPARATOR.'revisions';
$article_file = "0_0.txt";
$lock_key = 'atomic_lock_timeout';
$text_key = "text_key";
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
	if($lock == FALSE) return false;
	return true;
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
	}catch(Exception $e){return false;}
	
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
		return $data;

	}catch(Exception $e){return false;}	
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
	}catch(Exception $e){return false;}
	return $data;
}
function strip_html($html){
	return preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
}

//Upload File
if(!empty($_FILES)){
	$temp = explode(".", $_FILES["file"]["name"]);
	$extension = end($temp);
	$fname = $temp[0];
	
	if ($_FILES["file"]["type"] == "text/plain" && $_FILES["file"]["size"] < $max_size && $extension=='txt') {  
		if ($_FILES["file"]["error"] > 0) {
	    	$message = "Return Code: " . $_FILES["file"]["error"];
	  	}else {
	  	  
	  	  $locked = true;//acquireLock();
	  	  if(!$locked) $message = "Could not acquire lock! Please try again.";
	  	  else{
		  	  try{
			  	  $new_id = getNewId();
			  	  if($new_id === false){
			  	  	 $message = "Unexpected error occured! Please try again";
			  	  }else{			  	  	  			  	  	  
				  	  if( $new_id<=$max_revisions ){
				  	      $appended = getAppended($fname, $_FILES["file"]["tmp_name"]);
				  	      if($appended == FALSE){
				  	      	$message = "Unexpected error occured! Please try again.";
				  	      }else{
				  	      	  $merged = merge_revisions();
				  	      	  if($merged === FALSE){
							      	$message = "Could not merge revisions";
				  	      	  }else{
					  	  	  	  $new_revision = $revision_dir.DIRECTORY_SEPARATOR.$new_id.'_'.strlen($merged.$appended).'.txt';
						  	  	  $created = file_put_contents($new_revision, $appended);				  	  
							  	  if($created !== FALSE){							  	  	  
								      $merged = merge_revisions();
								      if($merged == FALSE){
								      		$message = "Could not merge revisions";
								      }else{
								      	  $mem->delete($text_key); 
								     	  $mem->delete($revision_key);	  	
								      }			  
							  	  }else if ($new_id>$max_revisions){
							  	  	 $message = "It is already ".$max_revisions." revisions of the text";
							  	  }else{
							  	  	$message = "Could not save uploaded file to appropriate directory";
							  	  }
				  	      	  }
				  	      }
				  	  }else{
				  	  	$message = "It is already ".$max_revisions." revisions of the text";
				  	  }
			  	  }	
		  	  }catch(Exception $e){releaseLock();}
		  	  
		  	  releaseLock();
	  	  }
	    }
	}else{
	  $message = "Invalid file! Please check that your file is .txt text file and is less then ".$max_size." bytes.";
	}
}
if ($message !="") $message.=' - <a href="index.php">close</a>';

$content = $mem->get($text_key);
$article_file = $mem->get($revision_key);
$content = FALSE;
if($text === FALSE || $article_file == FALSE){
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

<h4 style="color:red;text-align:center;"><?=$message?></h4>
<h1>Latest Plane Crash</h1>
<h2>
	<?=$article_file?> 
	[
	<a href="article_merged.txt" download="<?=$article_file?>">Download</a> | 
	<form action="index.php" method="post" enctype="multipart/form-data" style="display:inline;background:#ccc;padding:5px;">
	<input type="file" name="file" id="file" style="width:200px;"/>
	<input type="submit" name="submit" value="Upload File"/>
	</form> 
	]
</h2>
<?php 	
echo $content;
echo '<br/><br/><hr/>';
echo '<h2>Fibonacci(34): '.fibonacci(34).'</h2>';
?>
<br/><br/>
<hr/>
</body>
</html>
