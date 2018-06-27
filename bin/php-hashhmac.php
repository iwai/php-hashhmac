#!/usr/bin/env php
<?php
/**
 * Created by PhpStorm.
 * User: iwai
 * Date: 2017/02/03
 * Time: 13:05
 */

ini_set('date.timezone', 'Asia/Tokyo');

if (PHP_SAPI !== 'cli') {
    echo sprintf('Warning: %s should be invoked via the CLI version of PHP, not the %s SAPI'.PHP_EOL, $argv[0], PHP_SAPI);
    exit(1);
}

require_once __DIR__.'/../vendor/autoload.php';

use CHH\Optparse;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

try {
    $logger = new Logger('standard');
    $logger->pushHandler(
        (new StreamHandler('php://stderr'))->setFormatter(new LineFormatter("[%datetime%] %level_name%: %message%\n"))
    );

    $parser = new Optparse\Parser();

    function usage() {
        global $parser;
        fwrite(STDERR, "{$parser->usage()}\n");
        exit(1);
    }

    $parser->setExamples([
        sprintf("%s -k YOUR_KEY -f message.json", $argv[0]),
        sprintf("%s -a sha256 -k YOUR_KEY -f message.json", $argv[0]),
    ]);

    $algorithm = $key = null;

    $parser->addFlag('help', [ 'alias' => '-h' ], 'usage');
    $parser->addFlag('verbose', [ 'alias' => '-v' ]);

    $parser->addFlagVar('algorithm', $algorithm, [ 'alias' => '-a', 'required' => true, 'has_value' => true, 'default' => 'sha256' ]);
    $parser->addFlagVar('key', $key, [ 'alias' => '-k', 'required' => true, 'has_value' => true ]);
    $parser->addArgument('file', [ 'alias' => '-f' ]);

    $parser->parse();

    if (!$algorithm || !$key) {
        usage();
    }

    $file_path = $parser['file'];

    if ($file_path) {
        if (($fp = fopen($file_path, 'r')) === false) {
            die('Could not open '.$file_path);
        }
    } else {
        if (($fp = fopen('php://stdin', 'r')) === false) {
            usage();
        }
        $read = [$fp];
        $w = $e = null;
        $num_changed_streams = stream_select($read, $w, $e, 1);

        if (!$num_changed_streams) {
            usage();
        }
    }

    // hash_hmac()

    $content = null;

    while (!feof($fp)) {
        $content .= fgets($fp);
    }
    fclose($fp);

    echo $algorithm, ': ', base64_encode(hash_hmac($algorithm, $content, $key, true)), PHP_EOL;

} catch (\Exception $e) {

    $logger->error($e->getMessage());

    exit(255);
}
