<?php

// settings
$cacheDir = './cache';
$apiUrl = 'https://www.rescuetime.com/anapi/data';
$filter_logic = 'or';
$limit = 30;
$url = explode('?',$_SERVER['REQUEST_URI']);
$selfUrl = $url[0];

// constants
define('RESCUETIME_FILTER_TIMEFIELD', 'Time Spent (seconds)');
define('RESCUETIME_FILTER_BY', 'Activity,Document');

// variables defaults
$keywords = (isset($_SESSION['keywords'])) ? $_SESSION['keywords'] : '';
$uploaded = false;
$time = 0;
$fields = $content = $errors = array();
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
$api = array('rb' => date_create('-1 week')->format('Y-m-d'), 're' => date_create()->format('Y-m-d'), 'key'=>'');

// debug
if ('127.0.0.1'==$ip) {
    ini_set('display_errors', 1);
}

// session
session_start();

// Load data
if (isset($_POST['api'])) {
    $api = $_POST['api'];
    $api['format'] = 'csv';
    $error = rescuetimeRequest($apiUrl, $cacheDir, $api);
    if ($error) $errors[] = $error;
    $_SESSION['api'] = $api;
    // remember checkbox
    if (isset($_POST['remember'])) {
        setcookie('api', base64_encode(serialize($api)), pow(10,10));
    } else {
        setcookie('api', null);
    }
}
elseif (isset($_COOKIE['api'])) {
    $api = unserialize(base64_decode($_COOKIE['api']));
}


// file parsing
if (!empty($_SESSION['file'])) {
    $uploaded = true;
    $csv = fopen($cacheDir.$_SESSION['file'], 'rb');
    if ($csv) {
        $row = 0;
        $by = explode(',', RESCUETIME_FILTER_BY);
        $filter = array();
        $words = !empty($_GET['keywords']) ? preg_split('~\s*,\s*~',$_GET['keywords'], -1, PREG_SPLIT_NO_EMPTY) : array();
        $words = array_map('trim', $words);
        $_SESSION['keywords'] = $keywords = trim(implode(', ', $words));
        while (($d = fgetcsv($csv, 1000, ",")) !== FALSE) {
            if (empty($fields)) { $fields = $d; foreach($by as $i) { $filter[]=array_search($i, $fields); } continue; }
            // filtering
            if (count($fields)!=count($d)) continue;
            if (count($words)) {
                $haystack = array();
                $found = 0;
                foreach($words as $word) {
                    array_walk($d, function($v,$key)use($filter,&$haystack){if(in_array($key,$filter))$haystack[]=$v;});
                    if (false!==stripos(implode(' ',$haystack), $word)) $found++;
                }
                if ('and'==$filter_logic && $found<count($words)) continue;
                if ('or'==$filter_logic && 0==$found) continue;
            }
            // push data
            $content_row = array_combine($fields, $d);
            $time += intval($content_row[RESCUETIME_FILTER_TIMEFIELD]);
            $row++;
            if (count($content) >= $limit) continue;
            $content_row[RESCUETIME_FILTER_TIMEFIELD] = rescuetimeFormat(intval($content_row[RESCUETIME_FILTER_TIMEFIELD]));
            $content[] = $content_row;
        }
        fclose($csv);
    }
}

// utils
function rescuetimeFormat($time) {
    $h = floor($time/3660);
    $i = round($time/60%60);
    $s = round($time%60);
    return ($h?$h.'h':'') . ' ' . ($i?$i.'m':'') . ' ' . ($s?$s.'s':'');
}
function rescuetimeRequest($apiUrl, $cacheDir, $api) {
    $_SESSION['file'] = '/'.rescuetimeHash($api);
    $file = $cacheDir . $_SESSION['file'];
    // a) Download csv "Documents and Details" by hacked cookie (copy from a signed in browser)
    if (false!==strpos($api['key'], 'auth_token=')) {
        $f = fopen($file, 'wb');
        $ret = '';
        $api['key'] = trim(str_replace('Cookie:','',$api['key']));
        $begin = date_create($api['rb']);
        $end = date_create($api['re']);
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_COOKIE         => $api['key'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'rescuetimefilter/2.0',
            CURLOPT_SSL_VERIFYPEER => false
        ));
        while($begin->getTimestamp() < $end->getTimestamp()) {
            curl_setopt($ch, CURLOPT_URL, 'https://www.rescuetime.com/browse/documents/by/rank/for/the/day/of/'.$begin->format('Y-m-d').'.csv');
            fwrite($f, curl_exec($ch));
            $ret .= 'Grabbing ' . $begin->format('Y-m-d') . '.csv<br/>';
            $begin->modify('+1 day');
        }
        curl_close($ch);
        fclose($f);
        return $ret;
    }
    // b) silly API
    copy($apiUrl . '?' . http_build_query($api), $cacheDir.$_SESSION['file']);
    if (filesize($cacheDir.$_SESSION['file']) < 20) return 'Error occured!';
    return null;
}
function rescuetimeHash($array) {
    return sha1(serialize($array));
}

// view
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Rescuetime Filter</title>
    <meta charset="UTF-8"/>
    <link href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.1.1/css/bootstrap-combined.min.css" rel="stylesheet">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.1/jquery.min.js"></script>
    <?php /*
    <script src="//netdna.bootstrapcdn.com/twitter-bootstrap/2.1.1/js/bootstrap.min.js"></script>
    */?>
</head>
<body>
<div class="container">
    <header>
        <h1>Rescuetime Filter</h1>
        <p class="small">This <a href="https://github.com/rumbu/rescuetimefilter">project</a> is a patch for <a href="http://www.rescuetime.com">Rescuetime</a> time tracker.
        Rescuetime Filter provides a better filtering than original service.</p>
    </header>
    <section>
        <?php foreach ($errors as $error) { ?>
            <p class="alert alert-error"><?php echo $error ?></p>
        <?php } ?>
        <h2<?php if ($uploaded) {?> class="muted" onclick="$(this).siblings('.hide').slideToggle()" <?php }?>>1. Load logged activity <?php
            if ($uploaded) { ?>
                <em class="badge badge-success">Uploaded: <?php echo $api['rb'],'&thinsp;&mdash;&thinsp;',$api['re'] ?></em>
            <?php } else { ?>
                <em class="badge badge-important">Not uploaded yet</em><?php
            } ?></h2>
        <div class="<?php if ($uploaded && empty($errors)) {?> hide<?php }?>">
            <p>Please, provide an <a href="https://www.rescuetime.com/anapi/setup" target="_blank" rel="nofollow">API Key</a>
            to autoload data by selected dates.
                Or, to get best available precision, paste to the field "API Key" your browser's <code>Cookie: <b>string</b></code></p>
            <form method="post" class="form-inline">
                <input type="text" name="api[key]" id="apiKey" required="required" placeholder="API Key" value="<?php echo $api['key']?>" class="input-large"/>&nbsp;
                Begin&thinsp;<sup><abbr title="Begin date, inclusive">?</abbr></sup>
                      <input type="date" name="api[rb]" class="input-small" value="<?php echo $api['rb']?>"/>&nbsp;
                End&thinsp;<sup><abbr title="End date, uninclusive">?</abbr></sup>
                    <input type="date" name="api[re]" class="input-small" value="<?php echo $api['re']?>"/>
                <label class="checkbox"><input type="checkbox" name="remember" id="remember" checked="checked"/>Remember</label>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        </div>
    </section>
    <section>
        <h2<?php if (!$uploaded) {?> class="muted"<?php }?>>2. Filter results<?php
            if (!empty($keywords)) {?> <a href="<?php echo $selfUrl?>" class="badge badge-inverse">Reset</a><?php }
            ?></h2>
        <form method="get"<?php if (!$uploaded) {?> class="hide"<?php }?>>
            <div class="input-append">
                <input type="text" name="keywords" placeholder="Keywords, separated by commas" value="<?php
                    echo htmlspecialchars($keywords) ?>" class="input-xxlarge" maxlength="255"/>
                <button type="submit" class="btn">Filter</button>
            </div>
        </form>
    </section>
    <?php if ($time>0) { ?>
    <section>
        <h3><?php echo rescuetimeFormat($time) ?> <small>total</small></h3>
        <table class="table table-condensed table-striped">
        <thead><tr>
            <?php foreach($fields as $field) { ?>
            <th><?php echo $field ?></th>
            <?php } ?>
        </tr></thead>
        <tbody>
        <?php foreach($content as $content_row) { ?>
            <tr>
                <?php foreach($content_row as $v) { ?>
                <td><?php echo htmlspecialchars($v) ?></td>
                <?php } ?>
            </tr>
        <?php } ?>
        </tbody></table>
        <p>Displayed <?php echo count($content) ?> from <?php echo $row; ?> records.</p>
    </section>
    <?php } ?>
    <footer>
        <div class="well">&copy; 2012 &mdash; <a href="http://www.lisin.ru">lisin.ru</a></div>
    </footer>
</div>
</body>
</html>