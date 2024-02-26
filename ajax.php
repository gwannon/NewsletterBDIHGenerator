<?php 
ini_set("display_errors", 0);
date_default_timezone_set('Europe/Madrid');
header('Content-Type: application/json; charset=utf-8');
$json = [];
 if(isset($_GET['action']) && $_GET['action'] == 'items') {
  $lang = strip_tags($_GET['lang']);

  /*$stream_context = stream_context_create([
    "ssl" => [
      "verify_peer" => false,
      "verify_peer_name" => false
    ]
  ]);*/


  $items = json_decode(file_get_contents("https://bdih.spri.eus/".($lang != 'eu' ? $lang."/" : "").
"wp-json/wp/v2/posts?_embed&per_page=100"/*, false, $stream_context*/));
  foreach ($items as $item) {
    if(isset($item->_embedded->{'wp:featuredmedia'}[0])) {
      $featuredimage = $item->_embedded->{'wp:featuredmedia'}[0]->media_details->sizes->full->source_url;
    } else $featuredimage = "";
    $json[] = [
      'type' => 'Noticia',
      'id' => $item->id,
      "timestamp" => strtotime($item->date),
      "date" => date("Y-m-d", strtotime($item->date)),
      'title' => $item->title->rendered,
      'url' => $item->link,
      'image' => $featuredimage,
      'description' => strip_tags($item->excerpt->rendered),
    ];
  }

  $items = json_decode(file_get_contents("https://www.spri.eus/ejson/casos-uso/?lang=".$lang.
"&per_page=100"/*, false, $stream_context*/));
  foreach ($items as $item) {
    list($dia, $mes, $ano) = explode("/", $item->fecha_caso);
    $json[] = [
      'type' => 'Caso de uso',
      'id' => $item->id,
      "timestamp" => strtotime($ano."/".$mes."/".$dia),
      "date" =>  date("Y-m-d", strtotime($ano."/".$mes."/".$dia)),
      'title' => $item->title,
      'url' => ($lang == 'es' ? "https://bdih.spri.eus/es/casos-de-uso/" : "https://bdih.spri.eus/erabilera-kasuak/").basename($item->url_spri),
      'image' => $item->img,
      'description' => strip_tags($item->extracto),
    ];
  }

  $keys = array_column($json, 'timestamp');
  array_multisort($keys, SORT_DESC, $json);


} else if(isset($_POST['action']) && ($_POST['action'] == 'generate' || $_POST['action'] == 'send')) {
  $lang = strip_tags($_POST['lang']);
  $html = file_get_contents("templates/main_".$lang.".html");
  $innerhtml = "";
  if(isset($_POST['form'])) {
    foreach ($_POST['form'] as $item) {
      if($item['type'] == 'button') {
        $innerhtml .= file_get_contents("templates/button_".$item['value'][2].".html");
        $innerhtml = str_replace('[url]', $item['value'][0], $innerhtml);
        $innerhtml = str_replace('[texto]', $item['value'][1], $innerhtml);
      } else if($item['type'] == 'image') {
        $innerhtml .= file_get_contents("templates/image.html");
        $innerhtml = str_replace('[image]', $item['value'][0], $innerhtml);
      } else if($item['type'] == 'spaciator') {
        $innerhtml .= file_get_contents("templates/spaciator.html");
        $innerhtml = str_replace('[size]', $item['value'][0], $innerhtml);
        $innerhtml = str_replace('[color]', $item['value'][1], $innerhtml);
      } else if($item['type'] == 'title') {
        $innerhtml .= file_get_contents("templates/title-".$item['value'][0]."_".$lang.".html");
      } else if($item['type'] == 'item') {
        if($item['value'][5] == 'featured')  $temp = file_get_contents("templates/featureditem_".$lang.".html");
        else if($item['value'][5] == 'event')  $temp = file_get_contents("templates/event_".$lang.".html");
        else if($item['value'][5] == 'case')  $temp = file_get_contents("templates/case_".$lang.".html");
        else $temp = file_get_contents("templates/item_".$lang.".html");
        $temp = str_replace('[title]', $item['value'][0], $temp);
        if($item['value'][1] != '') {
          $temp = str_replace('[has_subtitle]', "", $temp);
          $temp = str_replace('[/has_subtitle]', "", $temp);
          $temp = str_replace('[subtitle]', $item['value'][1], $temp);
        } else {
          $temp = str_replace('[has_subtitle]', "<!-- ", $temp);
          $temp = str_replace('[/has_subtitle]', " -->", $temp);
        }
        $temp = str_replace('[imagen_url]', $item['value'][2], $temp);
        $temp = str_replace('[url]', $item['value'][3], $temp);
        $temp = str_replace('[description]', $item['value'][4], $temp);
        $innerhtml .= $temp;
      } else if($item['type'] == 'banner') {
        $innerhtml .= file_get_contents("templates/banner-".$item['value'][0]."_".$lang.".html");
      }
    }
  }
  $html = str_replace("[MAIN]", $innerhtml, $html);
  file_put_contents("temp.html", $html);
  if($_POST['action'] == 'send') {
    $file = date("Y-m-d_H_i_s").".html";
    file_put_contents("./html/".$file, $html);
    /*foreach(explode(",", $_POST['email']) as $email) {
      $email = chop($email);
      if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if(!mail($email, "Prueba: Activos Tecnológicos BDIH. Antes de invertir en tecnología...", 
          $html."<a href=\"http://".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']."/html/".$file."\">Descargar</a>", 
          "Content-Type: text/html; charset=UTF-8\r\n")) $json = ['status' => 'danger', 'text' => 'NO se ha podido enviar la newsletter. Inténtelo más tarde.'];
      } else if(!isset($json['status'])) $json = ['status' => 'danger', 'text' => 'Email incorrecto "'.$email.'".'];
    }*/
    foreach(explode(",", $_POST['email']) as $email) {
    	$email = chop($email);
    	if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    		if(!sendTest($email, "Activos Tecnológicos BDIH. Antes de invertir en tecnología...", $file)) $json = ['status' => 'danger', 'text' => 'NO se ha podido enviar la newsletter. Inténtelo más tarde.'];
    	} else if(!isset($json['status'])) $json = ['status' => 'danger', 'text' => 'Email incorrecto "'.$email.'".'];
    }
    
    if(!isset($json['status'])) $json = ['status' => 'success', 'text' => 'Newsletter enviada correctamente aaaaa: '.$_POST['email']];
  }
} else if(isset($_POST['action']) && $_POST['action'] == 'save' && isset($_POST['form'])) {
  file_put_contents("./saves/".(isset($_POST['namesave']) && $_POST['namesave'] != '' ? $_POST['namesave'] : 'Guardado')."-".$_POST['lang'].".json", json_encode($_POST['form']));
  if(!isset($json['status'])) $json = ['status' => 'success', 'text' => 'Newsletter guardada correctamente.'];
}
echo json_encode($json);






function sendTest($emails, $title, $file) {
	include_once(dirname(__FILE__)."/../phpmailer/PHPMailerAutoload.php");	
	$emails = explode(",", $emails);
	$title = "PRUEBA: ".$title;
  $date = date("Y-m-d H:i");
  $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]".str_replace(basename($_SERVER['PHP_SELF']), "", $_SERVER[REQUEST_URI]);
  $content = file_get_contents("temp.html")."<br/><br><a href='".$actual_link."html/".$file."'>DESCARGAR</a>";
  $content = str_replace("%SENDER-INFO-SINGLELINE%", "SPRI – Agencia Vasca de Desarrollo Empresarial, Alameda Urquijo, 36 - 4ª Plta., Edificio Plaza Bizkaia, 48011 BILBAO, Bi, España ", $content);
	$mail = new PHPMailer();
	$mail->IsSMTP();
	$mail->SMTPDebug  = false;
	$mail->SMTPAuth   = false; 
	$mail->SMTPAutoTLS = false;
	$mail->SMTPSecure = false;
	$mail->CharSet = 'UTF-8';
	$mail->Host = "192.168.10.11";
	$mail->Port = 25;
	$mail->SetFrom('boletin@acapi.spri.eus', 'Hacer boletín BDHI');
	//$mail->AddReplyTo($replyto);
	$mail->Subject = $title;
	$mail->MsgHTML($content);
	foreach ($emails as $email) {
		$mail->AddAddress($email);
	}
	
	if($mail->Send()) return true;
	else {
		return false;
	}
}


function cmp($a, $b) {
  return ($a->timestamp > $b->timestamp) ? -1 : 1;
}

