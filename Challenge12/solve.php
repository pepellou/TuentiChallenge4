#!/usr/bin/php
<?php

define('ROAD',  '.');
define('WALL',  '#');
define('START', 'S');
define('DESTINATION', 'X');

define('UP',    '^');
define('DOWN',  'v');
define('LEFT',  '<');
define('RIGHT', '>');

define('FORWARD',   '->');
define('BACKWARDS', '<-');

define('INFINITE', '99999999');

class Directions {

    private static $turnsRight = array(
        UP    => RIGHT,
        RIGHT => DOWN,
        DOWN  => LEFT,
        LEFT  => UP
    );

    private static $turnsLeft = array(
        UP    => LEFT,
        RIGHT => UP,
        DOWN  => RIGHT,
        LEFT  => DOWN
    );

    public static function rightOf($direction) {
        return self::$turnsRight[$direction];
    }

    public static function leftOf($direction) {
        return self::$turnsLeft[$direction];
    }

    public static function opposite($direction) {
        return self::rightOf(self::rightOf($direction));
    }

    public static function all() {
        return array(UP, RIGHT, DOWN, LEFT);
    }

}

class Cell {

    public $type;
    public $to;

    public $stepsFromStart, $stepsToEnd;

    public function __construct($type) {
        $this->type = $type;

        $this->to = array(
            UP    => null, DOWN  => null,
            LEFT  => null, RIGHT => null
        );
        $this->stepsFromStart = array(
            UP    => null, DOWN  => null,
            LEFT  => null, RIGHT => null
        );
        $this->stepsToEnd = array(
            UP    => null, DOWN  => null,
            LEFT  => null, RIGHT => null
        );
    }

    public function checkSolution() {
        foreach (Directions::all() as $direction) {
            $from = $this->stepsFromStart[$direction];
            $straight_to = $this->stepsToEnd[$direction];
            $turning_to = $this->stepsToEnd[Directions::rightOf($direction)];
            $to = ($straight_to != null) ? $straight_to : $turning_to;
            if ($from != null && $to != null) {
                return $from + $to;
            }
        }
        return false;
    }

    public function getSteps($direction, $way) {
        return ($way == FORWARD)
            ? array(
                $this->stepsFromStart[$direction],
                $this->stepsFromStart[Directions::leftOf($direction)]
            )
            : array(
                $this->stepsToEnd[Directions::opposite($direction)],
                $this->stepsToEnd[Directions::leftOf($direction)]
            );
    }

    public function setSteps($steps, $way, $direction) {
        return ($way == FORWARD)
            ? $this->setStepsFromStart($steps, $direction)
            : $this->setStepsToEnd($steps, Directions::opposite($direction));
    }

    private function setStepsFromStart($steps, $direction) {
        if ($this->stepsFromStart[$direction] !== null) {
            return false;
        }
        $this->stepsFromStart[$direction] = $steps;
        return true;
    }

    private function setStepsToEnd($steps, $direction) {
        if ($this->stepsToEnd[$direction] !== null) {
            return false;
        }
        $this->stepsToEnd[$direction] = $steps;
        return true;
    }

}

class Map {

    private $start, $destination;

    private $_currentSteps;

    public function __construct($data) {
        list(
            $this->start,
            $this->destination
        ) = $this->buildCells($data);
    }

    private function buildCells($data) {
        $previousLine = null;
        foreach ($data as $row) {
            $currentLine = array();
            $len = strlen($row);
            for ($i = 0; $i < $len; $i++) {
                $cell = new Cell($row[$i]);
                if ($cell->type == START) {
                    $start = $cell;
                }
                if ($cell->type == DESTINATION) {
                    $destination = $cell;
                }
                if ($previousLine != null) {
                    $cell->to[UP] = $previousLine[$i];
                    $previousLine[$i]->to[DOWN] = $cell;
                }
                if ($i > 0) {
                    $cell->to[LEFT] = $currentLine[$i-1];
                    $currentLine[$i-1]->to[RIGHT] = $cell;
                }
                $currentLine []= $cell;
            }
            $previousLine = $currentLine;
        }
        return array($start, $destination);
    }

    public function solve() {
        $this->_current_steps = 0;
        $forward = array($this->start);
        $backwards = array($this->destination);
        $solution = false;
        while ($solution === false && count($forward) > 0 && count($backwards) > 0) {
            $this->_current_steps++;

            $forward = $this->explore(FORWARD, $forward, $solution);

            $backwards = $this->explore(BACKWARDS, $backwards, $solution);
        }
        return ($solution === false) ? "ERROR" : $solution;
    }

    private function explore($way, $toExplore, &$solution) {
        $new_cells = array();
        foreach($toExplore as $cell) {
            foreach(Directions::all() as $direction) {
                $opposite = Directions::opposite($direction);
                $newCell = $cell->to[$direction];
                if ($this->isValidMove($cell, $newCell, $direction, $way)
                    && ($newCell->setSteps($this->_current_steps, $way, $direction))) {
                    $new_cells []= $newCell;
                    $solution = $newCell->checkSolution();
                    if ($solution !== false) {
                        return array();
                    }
                }
            }
        }
        return $new_cells;
    }

    private function isValidMove($from, $to, $direction, $way) {
        $steps = $from->getSteps($direction, $way);
        return ($to != null 
            && $to->type != WALL 
            && ($steps[0] == $this->_current_steps - 1
             || $steps[1] == $this->_current_steps - 1));
    }

}

$input = fopen('php://stdin', 'r');
$numProblems = trim(fgets($input));

for ($case = 1; $case <= $numProblems; $case++) {
    list($m, $n) = explode(" ", trim(fgets($input)));
    $data = array();
    for ($i = 0; $i < $n; $i++) {
        $data []= trim(fgets($input));
    }
    $map = new Map($data);
    echo "Case #$case: {$map->solve()}\n";
}
