#!/usr/bin/php
<?php

class PostRequest {

    private $url, $options;

    public function __construct($url, $data) {
        $this->url = $url;
        $this->options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
            )
        );
    }

    public function getResponse() {
        $context = stream_context_create($this->options);
        return file_get_contents($this->url, false, $context);
    }

}

class Cracker {

    private $url = 'http://54.83.207.90:4242/?debug=1';

    private $key, $solved, $input;
    private $error; // just in case

    private static $valid_chars = array(
        '0', '1', '2', '3', '4', '5', '6', '7',
        '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'
    );

    public function crack($input) {
        $this->input = $input;
        $this->key = '';
        $this->solved = false;
        $this->error = false;
        while (!$this->solved && !$this->error) {
            $this->key .= $this->findNextChar();
        }
        return ($this->error) ? "ERROR" : $this->key;
    }

    private function findNextChar() {
        $maxTime = 0;
        $bestCandidate = '';
        foreach (self::$valid_chars as $candidate) {
            $time = $this->tryChar($candidate);
            if ($time > $maxTime) {
                $maxTime = $time;
                $bestCandidate = $candidate;
            }
            if ($this->solved || $this->error) {
                return $candidate;
            }
        }
        return $bestCandidate;
    }

    private function tryChar($aChar) {
        $data = array(
            'input' => $this->input,
            'key' => $this->key.$aChar,
            'submit' => 'submit'
        );
        $request = new PostRequest($this->url, $data);
        $response = $request->getResponse();

        if ($this->parseSuccess($response)) {
            $this->solved = true;
            return -1;
        }

        return $this->parseTime($response);
    }

    private function parseSuccess($text) {
        return (strpos($text, 'correct key') !== false);
    }

    private function parseTime($text) {
        $begin_delimiter = 'Total run: ';
        $end_delimiter = '--';

        $start = strpos($text, $begin_delimiter) + strlen($begin_delimiter);
        if ($text == "" || $start === false) {
            $this->error = true;
            return -1;
        }
        $end = strpos($text, $end_delimiter, $start) - 1;
        return substr($text, $start, $end - $start);
    }

}

$cracker = new Cracker();
$stdin = fopen('php://stdin', 'r');
while ($input = fgets($stdin)) {
    echo $cracker->crack(trim($input))."\n";
}
