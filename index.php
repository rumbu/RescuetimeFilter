<?php
// settings
define('RESCUETIME_FILTER_TIMEFIELD', 'Time Spent (seconds)');
define('RESCUETIME_FILTER_BY', 'Activity,Document');
$cacheDir = './cache';
$filter_logic = 'or';
$limit = 30;

// variables defaults
$keywords = $email = '';
$uploaded = false;
$time = 0;
$fields = $content = array();

// actions
ini_set('display_errors', 1);
session_start();

// utils
function rescuetime_format($time) {
    $h = floor($time/3660);
    $i = round($time/60%60);
    $s = round($time%60);
    return ($h?$h.'h':'') . ' ' . ($i?$i.'m':'') . ' ' . ($s?$s.'s':'');
}

// handle file uploading
if (!empty($_FILES['csvFile']) && UPLOAD_ERR_OK==$_FILES['csvFile']['error']) {
    $_SESSION['file'] = $file = '/'.md5(mt_rand() . microtime(true));
    move_uploaded_file($_FILES['csvFile']['tmp_name'], $cacheDir.$file);
    header("Location: ".reset(explode('?',$_SERVER['REQUEST_URI']))); exit;
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
        $keywords = trim(implode(', ', $words));
        while (($d = fgetcsv($csv, 1000, ",")) !== FALSE) {
            $row++;
            if (1==$row) { $fields = $d; foreach($by as $i) { $filter[]=array_search($i, $fields); } continue; }
            // filtering
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
            $content_row[RESCUETIME_FILTER_TIMEFIELD] = rescuetime_format(intval($content_row[RESCUETIME_FILTER_TIMEFIELD]));
            if (count($content) >= $limit) continue;
            $content[] = $content_row;
        }
        fclose($csv);
    }
}

// view
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Rescuetime Filter</title>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <p class="lead">This project is a patch for <a href="http://www.rescuetime.com">Rescuetime</a> time tracker.
        Rescuetime Filter provides a right filtering like paid "Custom Reports" feature, that is not working well
            now (Sep 2012).</p>
    </header>
    <section>
        <h2<?php if ($uploaded) {?> class="muted" onclick="$(this).siblings('form').slideToggle()" <?php }?>>1. Submit your logged activity <?php
            if ($uploaded) { ?>
                <em class="badge badge-success">Uploaded</em>
            <?php } else { ?>
                <em class="badge badge-important">Not uploaded yet</em><?php
            } ?></h2>
        <form method="post" enctype="multipart/form-data" class="form-inline <?php if ($uploaded) {?> hide<?php }?>">
            <p>Upload an exported <b>CSV</b> file from <b>Documents and Details</b> section or by an
                <a href="https://www.rescuetime.com&shy;/browse/documents/by/rank/for/the/month/of/<?php echo date('Y-m-d')?>.csv"
                   target="_blank" rel="nofollow">URL</a></p>
            <label class="control-label" for="csvFile">File</label>
                <input type="file" name="csvFile" id="csvFile"/>
                <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </section>
    <section>
        <h2<?php if (!$uploaded) {?> class="muted"<?php }?>>2. Filter results</h2>
        <form method="get"<?php if (!$uploaded) {?> class="hide"<?php }?>>
            <div class="input-append">
                <input type="text" name="keywords" placeholder="Keywords, separated by commas" value="<?php echo $keywords?>" class="input-xxlarge" maxlength="255"/>
                <button type="submit" class="btn">Filter</button>
            </div>
        </form>
    </section>
    <?php if ($time>0) { ?>
    <section>
        <h3><?php echo rescuetime_format($time) ?> <small>total</small></h3>
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
        <p>Showed <?php echo count($content) ?> from <?php echo $row; ?> records.</p>
    </section>
    <?php } ?>
    <footer>
        <div class="well">&copy; 2012 &mdash; <a href="http://www.lisin.ru">lisin.ru</a></div>
    </footer>
</div>
</body>
</html>