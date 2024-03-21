<?php

$files = scan_dir(__DIR__);
$files = array_diff($files, [".", "..", "index.php"]);
echo json_encode($files);



function scan_dir($dir) {
  $ignored = array('.', '..', '.svn', '.htaccess');

  $files = array();    
  foreach (scandir($dir) as $file) {
      if (in_array($file, $ignored)) continue;
      if(preg_match("/-".$_GET['lang'].".json/", $file)) $files[$file] = filemtime($dir . '/' . $file);
  }

  arsort($files);
  $files = array_keys($files);

  return $files;
}
