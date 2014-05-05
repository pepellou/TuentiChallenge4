#!/usr/bin/php
<?php

define("RET_CHAR", chr(10));
define("SPACE", " ");
define("COLON", ";");
define("COMMA", ",");

define("BASE_DIR", dirname(__FILE__));
define("TIME_DIR", BASE_DIR."/last_times/");
define("FEED_DIR", BASE_DIR."/encrypted/");

class KeySuffixes {

    public static $suffixes = array();

    private static $valid_chars = array(
        'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
        'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
        'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'
    );

    public static function generate() {
        foreach (self::$valid_chars as $c1) {
            foreach (self::$valid_chars as $c2) {
                foreach (self::$valid_chars as $c3) {
                    self::$suffixes []= $c1.$c2.$c3;
                }
            }
        }
    }

}

KeySuffixes::generate();

class Event {

    public $timestamp, $id;
    public $previous, $next;

    public function __construct($timestamp, $id) {
        $this->timestamp = $timestamp;
        $this->id = $id;
        $this->previous = null;
        $this->next = null;
    }

}

class Feed {

    public $first_event, $last_event;
    public $numEvents;

    public function __construct($data = "") {
        $this->first_event = null;
        $this->last_event = null;
        $this->numEvents = 0;

        if ($data != "") {
            $parts = explode(SPACE, $data);
            $count = count($parts);
            for ($i = 0; $i + 2 < $count; $i += 3) {
                $this->addEvent(new Event($parts[$i+1], $parts[$i+2]));
            }
        }
    }

    public function printOut() {
        $event = $this->first_event;
        if ($event == null) {
            return "";
        }
        $out = $event->id;
        $event = $event->next;
        while ($event != null) {
            $out .= SPACE.$event->id;
            $event = $event->next;
        }
        return $out;
    }

    private function addEvent($event) {
        $this->last_event->next = $event;
        $event->previous = $this->last_event;
        $event->next = null;
        $this->last_event = $event;
        if ($this->first_event == null) {
            $this->first_event = $event;
        }
        $this->numEvents++;
    }

    private function insertEventBefore(&$event, &$existing) {
        $event->previous = $existing->previous;
        $event->next = $existing;
        if ($existing->previous != null) {
            $existing->previous->next = $event;
        }
        $existing->previous = $event;
        $this->numEvents++;
    }

    public function merge($another, $maxElements) {
        $eventToAdd = $another->first_event;
        while ($eventToAdd != null) {
            $next = $eventToAdd->next;
            $existing = $this->first_event;
            while ($existing != null && $existing->timestamp > $eventToAdd->timestamp) {
                $existing = $existing->next;
            }
            if ($existing == null) {
                if ($this->numEvents == $maxElements) {
                    return;
                }
                $this->addEvent($eventToAdd);
            } else {
                $this->insertEventBefore($eventToAdd, $existing);
            }
            $eventToAdd = $next;
        }
    }

    public function dropLast() {
        if ($this->last_event == null) { return; }
        
        $this->last_event = $this->last_event->previous;
        if ($this->last_event != null) {
            $this->last_event->next = null;
        } else {
            $this->first_event = null;
        }
        $this->numEvents--;
    }

}

class Friend {

    public $lastTime;
    private $uid, $key, $feed;
    private $uid_hash, $feed_start, $feed_start_len;
    private $cleared;

    public function __construct($uid, $key) {
        $this->uid = $uid;
        $this->key = $key;

        $this->uid_hash = substr($uid, strlen($uid) - 2);
        $this->lastTime = file_get_contents(TIME_DIR."{$this->uid_hash}/{$uid}.timestamp");

        $this->feed_start = (string) ($uid." ".$this->lastTime);
        $this->feed_start_len = strlen($this->feed_start);
    }

    public function getFeed() {
        if (!$this->cleared) {
            $this->resolveFeed();
        }
        return $this->feed;
    }

    private function resolveFeed() {
        $encrypted = file_get_contents(FEED_DIR."{$this->uid_hash}/{$this->uid}.feed");
        foreach (KeySuffixes::$suffixes as $suffix) {
            $theKey = $this->key.$suffix;
            $decrypted = mcrypt_decrypt('rijndael-128', $theKey, $encrypted, 'ecb');
            if ($this->properDecryption($decrypted)) {
                $this->feed = new Feed(trim(str_replace(RET_CHAR, SPACE, $decrypted)));
                $this->cleared = true;
            }
        }
    }

    private function properDecryption($message) {
        return (substr($message, 0, $this->feed_start_len) == $this->feed_start);
    }

}

class Problem {

    private $numEvents, $friends;

    public function __construct($numEvents, $friends) {
        $this->numEvents = $numEvents;
        $this->friends = $friends;
    }

    public function solve() {
        $feed = new Feed();
        $done = false;

        krsort($this->friends);

        while (!$done) {
            $nextFriend = array_shift($this->friends);
            $friendFeed = $nextFriend->getFeed();
            $feed->merge($friendFeed, $this->numEvents);

            while ($feed->numEvents > $this->numEvents) {
                $feed->dropLast();
            }
            
            if (count($this->friends) == 0 || ($feed->numEvents == $this->numEvents && reset($this->friends)->lastTime < $feed->last_event->timestamp)) {
                $done = true;
            }
        }
        return $feed->printOut();
    }

}

class ProblemReader {

    private $input;

    public function __construct($input) {
        $this->input = $input;
    }

    public function read() {
        $problems = array();
        foreach (file($this->input) as $line) {
            $parts = explode(COLON, $line);
            $numEvents = array_shift($parts);
            $friends = array();
            foreach ($parts as $friendData) {
                list($uid, $key) = explode(COMMA, trim($friendData));
                $new_friend = new Friend($uid, $key);
                $friends [$new_friend->lastTime]= $new_friend;
            }
            $problems []= new Problem($numEvents, $friends);
        }
        return $problems;
    }

}

$reader = new ProblemReader( 'php://stdin' );
$problems = $reader->read();
foreach($problems as $problem) {
    echo $problem->solve()."\n";
}
