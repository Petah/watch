<?php


//$stdin = fopen('php://stdin', 'r');
//stream_set_blocking($stdin, 0);
//echo 'Press enter to force run command...' . PHP_EOL;
//echo fread($stdin, 1);
//die();



$argv = $_SERVER['argv'];

// CLI options
$exclude = array();
$debug = false;
$verbose = false;
$path = '.';
$usage = false;
$message = null;
$command = null;
$keyboard = false;

for ($i = 1, $l = sizeof($argv);$i < $l; $i++) {
    switch ($argv[$i]) {
        case '--keyboard':
        case '-k': {
            $keyboard = true;
            echo 'Keyboard mode.' . PHP_EOL;
            break;
        }
        case '--debug':
        case '-d': {
            $debug = true;
            echo 'Debug mode.' . PHP_EOL;
            break;
        }
        case '--verbose':
        case '-verbose': {
            $verbose = true;
            echo 'Verbose mode.' . PHP_EOL;
            break;
        }
        case '--exclude':
        case '-e': {
            $exclude[] = realpath($argv[++$i]);
            break;
        }
        default: {
            $path = realpath($argv[$i]);
            $command = implode(' ', array_splice($argv, ++$i));
            if (!is_dir($path)) {
                $usage = true;
                $message = 'Path does not exist: ' . $argv[$i];
            }
            break 2;
        }
    }
}

if (!$path) {
    $usage = true;
    $mesage = 'Missing path.';
}

if (!$command) {
    $usage = true;
    $mesage = 'Missing command.';
}

if ($message) {
    echo $message . PHP_EOL;
}

if ($usage) {
    echo 'watch [-v|--verbose] [-d|--debug] [-e|--exclude <path>] <path> <command>' . PHP_EOL;
    return;
}

if ($debug) {
    echo 'Exclude: ' . implode(', ', $exclude) . PHP_EOL;
    echo 'Path: ' . $path . PHP_EOL;
}

echo 'Creating index...' . PHP_EOL;

function index($path, &$index = array()) {
    global $exclude;
    foreach (glob($path . '/*') as $file) {
        $realpath = realpath($file);
        foreach ($exclude as $e) {
            if (strpos($realpath, $e) === 0) {
                continue 2;
            }
        }
        if (is_dir($realpath)) {
            index($realpath, $index);
        } elseif (is_file($realpath)) {
            $index[$realpath] = md5_file($realpath);
        } else {
            //echo 'Unknown file: ' . $file . PHP_EOL;
        }
    }
    return $index;
}

$index = index($path);
echo sizeof($index) . ' files have been added to the index.' . PHP_EOL;

function non_block_read($fd, &$data) {
    $read = array($fd);
    $write = array();
    $except = array();
    $result = stream_select($read, $write, $except, 0);
    if($result === false) throw new Exception('stream_select failed');
    if($result === 0) return false;
    $data = stream_get_line($fd, 1);
    return true;
}

while (true) {
    $reindex = index($path);
    $changed = false;
    $removed = array_diff_key($index, $reindex);
    $added = array_diff_key($reindex, $index);
    if (sizeof($removed) > 0 ||
            sizeof($added) > 0) {
        echo PHP_EOL . sizeof($added) . ' file(s) has been added to the index, ' . sizeof($removed) . ' file(s) has been removed.';
        $changed = true;
    } else {
        foreach ($index as $file => $hash) {
            if ($hash !== md5_file($file)) {
                $changed = true;
                echo PHP_EOL . 'Changed detected in ' . $file;
            }
        }
    }

    if ($keyboard) {
        $line = '';
        $data = '';
        while (non_block_read(STDIN, $data)) {
            $line .= $data;
        }
        if ($line && !$changed) {
            echo PHP_EOL . 'Key press detected: ' . $line;
            $changed = true;
        }
    }

    if ($changed) {
        echo PHP_EOL . 'Running: ' . $command;
        echo PHP_EOL;
        system($command);
        echo PHP_EOL . 'Finished.';
        echo PHP_EOL;
        $index = $reindex;
    }
    usleep(200000);
    echo "\r" . 'Scanning for changes at ' . date(DATE_RSS) . '...';
}

echo PHP_EOL;
