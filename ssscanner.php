<?php

require __DIR__.'/vendor/autoload.php';

$transport = Swift_SmtpTransport::newInstance('ssl://smtp.gmail.com', 465)
    ->setUsername('ropez.erik.test@gmail.com')
    ->setPassword('TrorreySheet');

$mailer = Swift_Mailer::newInstance($transport);

$dbFile = 'ssscanner.db';
$scannedIds = null;
if (file_exists($dbFile))
    $scannedIds = unserialize(file_get_contents($dbFile));
if (!is_array($scannedIds))
    $scannedIds = array();

$contentUrl = 'https://www.ss.lv/ru/real-estate/flats/daugavpils-and-reg/daugavpils/hand_over/';

logMessage('Downloading ' . $contentUrl);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $contentUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0); 
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_FAILONERROR, true);
$content = curl_exec($ch);
curl_close($ch);

$offset = 0;

while (($blockBegin = strpos($content, '<td class="msga2"><a href="', $offset)) !== FALSE) {
    $blockEnd = strpos($content, '</tr>', $blockBegin);
    $block = substr($content, $blockBegin, $blockEnd - $blockBegin);

    if (preg_match('/class="am" href="([^"]+)">(<b>)?([^<]+)/', $block, $matches)) {
        $url = $matches[1];
        $title = $matches[3];

        if (strpos($url, 'http') !== 0)
            $url = 'https://www.ss.lv' . $url;
    
        if (preg_match('/\/([^\.\/]+)\.html$/', $url, $matches)) {
            $id = $matches[1];
            if (strpos($block, '€/день') === FALSE) {
                if (!in_array($id, $scannedIds)) {
                    $subject = 'SS.LV: ' . $title;
                    $body = $url;

                    logMessage($url);

                    logMessage('New record found, sending email: ' . $subject);

                    $message = Swift_Message::newInstance($subject)
                        ->setFrom(array('ropez.erik.test@gmail.com'))
                        ->setTo(array('ropez.erik@gmail.com', 'zvirbulekristine@gmail.com'))
                        ->setBody($body);

                    if ($mailer->send($message)) {
                        $scannedIds[] = $id;
                        file_put_contents($dbFile, serialize($scannedIds));
                    } else {
                        logMessage('Failed to send email, will try again later');
                    }
                }   
            }
        } else {
            logMessage('Cannot find ID in URL: ' . $url);
        }
    } else {
        logMessage('Cannot find URL and title in the block:' . PHP_EOL . $block);
    }

    $offset = $blockEnd;
}

function logMessage($message)
{
    echo date('c') . ': ' . $message . PHP_EOL;
}
