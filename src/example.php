<html><body><pre>
<?php 

/**
 * Esempio di uso CinebotApiClient
 */
require_once __DIR__ . '/../vendor/autoload.php';

$url="http://127.0.0.1:8080";
$pv="2";
$password="9wye916isvmf56qqcvwyyts3s3cp986k";

echo "Apertura Client verso ".$url."\r\n";
$ac=new \Cinebot\CinebotApiClient($url,$pv,$password);

echo "Richiesta server pronto?\r\n";
$resp=$ac->isReady();
var_dump($resp);
echo "\r\n";

echo "Richiesta programmazione\r\n";
$resp=$ac->getProgrammazione();
var_dump($resp);
echo "\r\n";


?>
</pre></body></html>