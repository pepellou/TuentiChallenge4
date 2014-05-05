#!/usr/bin/php
<?php

define ('WHITE', 'O');
define ('BLACK', 'X');
define ('BLANK', '.');
define ('IMPOSSIBLE', 'Impossible');
define ('SKIP_MOVE', 'I could skip this');

class Direction {

    private static $_all = array();

    private $dc, $dr;

    public function __construct($dc, $dr) {
        $this->dc = $dc;
        $this->dr = $dr;
    }

    public static function init() {
        self::$_all = array(
            new Direction(0, -1),
            new Direction(0, 1),
            new Direction(-1, 0),
            new Direction(1, 0),
            new Direction(-1, -1),
            new Direction(-1, 1),
            new Direction(1, -1),
            new Direction(1, 1)
        );
    }

    public function getNext($col, $row) {
        return array(
            ($this->dc == 0) ? $col : chr(ord($col) + $this->dc),
            $row + $this->dr
        );
    }

    public static function all() {
        return self::$_all;
    }

}
Direction::init();

class Board {

    private $data;

    public function __construct($setup) {
        $this->data = $setup;
    }

    public static function read($input) {
        $data = array();
        for ($i = 1; $i <= 8; $i++) {
            $line = trim(fgets($input));
            for ($c = 'a', $j = 0; $j < 8; $c++, $j++) {
                $data[$c][$i] = $line[$j];
            }
        }
        return new Board($data);
    }

    public function isValid($col, $row, $color, &$toFlip) {
        if ($this->data[$col][$row] != BLANK) {
            return false;
        }
        $toFlip = array();
        foreach(Direction::all() as $direction) {
            $foundTheOtherSlice = false;
            $foundOpponentDiscs = false;
            $foundBlank = false;
            $flipping = array();
            list($i, $j) = $direction->getNext($col, $row);
            while (!$foundBlank && !$foundTheOtherSlice
                    && $i <= 'h' && $i >= 'a' && $j <= 8 && $j >= 1) {
                $cell = $this->data[$i][$j];
                if ($cell == BLANK) {
                    $foundBlank = true;
                } else {
                    if ($cell == $color) {
                        $foundTheOtherSlice = true;
                    } else {
                        $foundOpponentDiscs = true;
                        $flipping []= array($i, $j);
                    }
                }
                list($i, $j) = $direction->getNext($i, $j);
            }
            if ($foundTheOtherSlice && $foundOpponentDiscs) {
                $toFlip = array_merge($toFlip, $flipping);
            }
        }
        return (count($toFlip) > 0);
    }

    public function isCorner($move) {
        list($col, $row) = $move;
        return ($col == 'a' || $col == 'h') && ($row == '1' || $row == '8');
    }

    public function applyMove($move, $color) {
        if ($move == SKIP_MOVE) {
            return;
        }
        list($col, $row, $toFlip) = $move;
        $this->data[$col][$row] = $color;
        foreach ($toFlip as $disc) {
            list($c, $r) = $disc;
            $this->data[$c][$r] = $color;
        }
    }

    public function unapplyMove($move, $color) {
        if ($move == SKIP_MOVE) {
            return;
        }
        list($col, $row, $toFlip) = $move;
        $this->data[$col][$row] = BLANK;
        foreach ($toFlip as $disc) {
            list($c, $r) = $disc;
            $this->data[$c][$r] = $color;
        }
    }

}

class Problem {

    private $turn, $goal, $board;
    private $color;

    public function __construct($board, $color, $goal) {
        $this->color = $color;
        $this->turn = $color;
        $this->goal = $goal;
        $this->board = $board;
    }

    public function solve() {
        foreach($this->getPossibleMoves() as $move) {
            $this->applyMove($move);
            if ($this->goal == 1) {
                if ($this->board->isCorner($move)) {
                    $this->unapplyMove($move);
                    return $this->printMove($move);
                }
            } else {
                $covers_all_defenses = true;
                foreach($this->getPossibleMoves() as $defense) {
                    if ($covers_all_defenses) {
                        $this->applyMove($defense);
                        $this->goal--;
                        if ($this->solve() == IMPOSSIBLE) {
                            $covers_all_defenses = false;
                        }
                        $this->goal++;
                        $this->unapplyMove($defense);
                    }
                }
                if ($covers_all_defenses) {
                    $this->unapplyMove($move);
                    return $this->printMove($move);
                }
            }
            $this->unapplyMove($move);
        }
        return IMPOSSIBLE;
    }

    private function applyMove($move) {
        $this->board->applyMove($move, $this->turn);
        $this->changeTurn();
    }

    private function unapplyMove($move) {
        $this->board->unapplyMove($move, $this->turn);
        $this->changeTurn();
    }

    private function changeTurn() {
        $this->turn = ($this->turn == WHITE) ? BLACK : WHITE;
    }

    private function printMove($move) {
        if ($move == SKIP_MOVE) {
            return SKIP_MOVE;
        }
        list($col, $row) = $move;
        return "$col$row";
    }

    private function getPossibleMoves() {
        $moves = array();
        for ($col = 'a'; $col <= 'h'; $col++) {
            for ($row = 1; $row <= 8; $row++) {
                if ($this->board->isValid($col, $row, $this->turn, $toFlip)) {
                    $moves []= array($col, $row, $toFlip);
                }
            }
        }
        return (count($moves) == 0) ? array(SKIP_MOVE) : $moves;
    }

    public static function read($input) {
        list($color, $in, $goal) = explode(" ", trim(fgets($input)));
        $color = ($color == 'White') ? WHITE : BLACK;
        return new Problem(Board::read($input), $color, $goal);
    }

}

$stdin = fopen('php://stdin', 'r');
$numCases = trim(fgets($stdin));
for ($case = 0; $case < $numCases; $case++) {
    $problem = Problem::read($stdin);
    echo $problem->solve()."\n";
}
