<?php

require __DIR__.'/vendor/autoload.php';

$transport = Swift_SmtpTransport::newInstance('ssl://smtp.gmail.com', 465)
    ->setUsername('username@gmail.com')
    ->setPassword('password');

$mailer = Swift_Mailer::newInstance($transport);

$dbFile = 'ssscanner.db';
$scannedIds = null;
if (file_exists($dbFile))
    $scannedIds = unserialize(file_get_contents($dbFile));
if (!is_array($scannedIds))
    $scannedIds = array();

$contentUrls = [
    'https://www.ss.lv/lv/real-estate/flats/riga/purvciems/hand_over/filter/',
    'https://www.ss.lv/lv/real-estate/flats/riga/plyavnieki/hand_over/filter/',
    'https://www.ss.lv/lv/real-estate/flats/riga/mezhciems/hand_over/filter/',
    'https://www.ss.lv/lv/real-estate/flats/riga-region/balozi/hand_over/filter/',
    'https://www.ss.lv/lv/real-estate/flats/riga-region/salaspils/hand_over/filter/',
    'https://www.ss.lv/lv/real-estate/flats/riga-region/marupes-pag/hand_over/filter/',
    'https://www.ss.lv/lv/real-estate/flats/riga-region/kekavas-pag/hand_over/filter/'
];

/*$post = array(
    'topt[8][min]' => 200,
    'topt[8][max]' => 350,
    'topt[1][min]' => 2,
    'topt[1][max]' => 3,
    'topt[3][min]' => 45,
    'topt[3][max]' => 70,
    'topt[4][min]' => 2
);*/
$postStr = 'topt%5B8%5D%5Bmin%5D=200&topt%5B8%5D%5Bmax%5D=350&topt%5B1%5D%5Bmin%5D=2&topt%5B1%5D%5Bmax%5D=3&topt%5B3%5D%5Bmin%5D=45&topt%5B3%5D%5Bmax%5D=70&topt%5B4%5D%5Bmin%5D=&topt%5B4%5D%5Bmax%5D=&opt%5B6%5D=&opt%5B11%5D=';
/*foreach ($post as $key => $value) {
    $postStr .= '&'.urlencode($key).'='.urlencode($value);
}
if (!empty($postStr))
    $postStr = substr($postStr, 1);*/

foreach ($contentUrls as $contentUrl) {
    sleep(5);

    logMessage('Downloading ' . $contentUrl);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $contentUrl);
    if (!empty($postStr)) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postStr);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, false);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, false);
    curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
    curl_setopt($ch, CURLOPT_COOKIEFILE, '');
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
                if (strpos($block, '€/dienā') === FALSE) {
                    if (!in_array($id, $scannedIds)) {
                        $subject = 'SS.LV: ' . $title;
                        $body = $url;

                        logMessage($url);

                        logMessage('New record found, sending email: ' . $subject);

                        $message = Swift_Message::newInstance($subject)
                            ->setFrom(array('from@gmail.com'))
                            ->setTo(array('to1@gmail.com', 'to2@inbox.lv'))
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
}

function logMessage($message)
{
    echo date('c') . ': ' . $message . PHP_EOL;
}
