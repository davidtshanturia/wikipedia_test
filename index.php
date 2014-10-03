<?php
$revision_dir = dirname(__FILE__).DIRECTORY_SEPARATOR.'revisions';
$lock_key = 'atomic_lock_timeout';
$text_key = "text_key";
$message='';
$max_size = 20000;
$max_revisions = 2;

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
	}catch(Exception $e){return false;}
	
	return $count;
}
function strip_html($html){
	return preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
}

//Upload File
if(!empty($_FILES)){
	$temp = explode(".", $_FILES["file"]["name"]);
	$extension = end($temp);
	
	if ($_FILES["file"]["type"] == "text/plain" && $_FILES["file"]["size"] < $max_size && $extension=='txt') {  
		if ($_FILES["file"]["error"] > 0) {
	    	$message = "Return Code: " . $_FILES["file"]["error"];
	  	}else {
	  	  
	  	  acquireLock();
	  	  
	  	  try{
		  	  $new_id = getNewId();
		  	  if($new_id === false){
		  	  	 $message = "Unexpected error occured! Please try again";
		  	  }else{
		  	  	  $new_revision = $revision_dir.DIRECTORY_SEPARATOR.$new_id.'.txt';
			  	  $moved = move_uploaded_file($_FILES["file"]["tmp_name"], $new_revision);
			  	  if( $moved ){
				  	  if($new_id<=$max_revisions){			       
					      $mem->delete($text_key); 
					      //TODO: check this failure as well
					      @file_put_contents('sample.txt', strip_html(@file_get_contents($new_revision)));
					      $message = "Successfully uploaded";	  				  
				  	  }else if ($new_id>$max_revisions){
				  	  	 $message = "It is already ".$max_revisions." revisions of the text";
				  	  }else{
				  	  	$message = "Unexpected error occured! Please check that memcache i s running on your machine on localhost:11211";
				  	  }
			  	  }else{
			  	  	$message = "Could not move uploaded file to appropriate directory";
			  	  }
		  	  }	
	  	  }catch(Exception $e){releaseLock();}
	  	  
	  	  releaseLock();
	    }
	}else{
	  $message = "Invalid file! Please check that your file is .txt text file and is less then ".$max_size." bytes.";
	}
}
if ($message !="") $message.=' - <a href="index.php">close</a>';
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
	Sample.txt [<a href="sample.txt" download="sample.txt">Download</a> | 
	<form action="index.php" method="post" enctype="multipart/form-data" style="display:inline;">
	<input type="file" name="file" id="file" style="width:200px;"/>
	<input type="submit" name="submit" value="Upload File"/>
	</form>]
</h2>
<?php 
$fromCache = $mem->get($text_key);
if($fromCache != '') echo $fromCache;
else {
	$content = file_get_contents('sample.txt');	
	echo $content;
	$mem->add($text_key, $content);
}
echo '<h2>Fibonacci: '.fibonacci(34).'</h2>';
?>
</body>
</html>
