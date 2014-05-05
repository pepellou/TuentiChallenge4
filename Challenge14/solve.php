#!/usr/bin/php
<?php

class Combination {

    public $elements;
    public $score;

    public function __construct($elements, $score) {
        $this->elements = $elements;
        $this->score = $score;
    }

}

class Combinator {

    private $combinations;

    public function __construct($elements) {
        $this->resolveCombinations($elements);
        $this->cursor = 0;
    }

    public function hasNext() {
        return ($this->cursor < count($this->combinations));
    }

    public function getNext() {
        if (!$this->hasNext()) {
            return null;
        }
        return $this->combinations[$this->cursor++];
    }

    private function resolveCombinations($elements) {
        $this->combinations = array();
        foreach ($this->getCombinations($elements) as $combination) {
            $i = 0;
            while ($i < count($this->combinations) && $this->combinations[$i]->score > $combination->score) {
                $i++;
            }
            $j = count($this->combinations);
            while ($j > $i) {
                $this->combinations[$j] = $this->combinations[$j - 1];
                $j--;
            }
            $this->combinations[$i] = $combination;
        }
    }

    private function getCombinations($elements) {
        if (count($elements) == 1) {
            return array(
                new Combination(array($elements[0]), $elements[0]->value),
                new Combination(array(), 0)
            );
        }
        $last = array_pop($elements);
        $result = array();
        foreach ($this->getCombinations($elements) as $subComb) {
            $elements = $subComb->elements;
            $result []= new Combination($elements, $subComb->score);
            $elements[]= $last;
            $result []= new Combination($elements, $subComb->score + $last->value);
        }
        return $result;
    }

    public static function forElements($elements) {
        return new Combinator($elements);
    }

}

class Wagon {

    public $destination, $value, $station;

    public function __construct($destination, $value, $station) {
        $this->destination = $destination;
        $this->value = $value;
        $this->station = $station;
    }

    public function getDestination() {
        return Station::byName($this->destination);
    }

}

class Station {

    public $wagons, $x, $y, $name;
    public $connections = array();

    public static $instances = array();
    public static $names = array();

    public function __construct($data) {
        list($this->name, $coords, $destination, $value) = explode(" ", $data);
        list($this->x, $this->y) = explode(",", $coords);
        $this->wagons = array(
            new Wagon($destination, $value, $this)
        );
        self::$instances[$this->name]= $this;
        self::$names[]= $this->name;
    }

    public static function byName($name) {
        return self::$instances[$name];
    }

    public static function allNames() {
        return self::$names;
    }

    public static function init() {
        self::$instances = array();
    }

    public function getWaysTo($goalStation, $prohibited_stations = array()) {
        if ($this == $goalStation) {
            return array($this->name);
        }
        $ways = array();
        foreach ($this->connections as $connection) {
            $station = $connection->destination;
            if (!in_array($station->name, $prohibited_stations)) {
                $prohibited_stations []= $this->name;
                $subways /* ^_^ */ = $station->getWaysTo($goalStation, $prohibited_stations);
                foreach ($subways as $subway) {
                    $ways []= $this->name."-".$subway;
                }
            }
        }
        return $ways;
    }

    public function distanceTo($destination) {
        if (!isset($this->_distance[$destination->name])) {
            $dx = $this->x - $destination->x;
            $dy = $this->y - $destination->y;
            $this->_distance[$destination->name] = sqrt($dx * $dx + $dy * $dy);
        }
        return $this->_distance[$destination->name];
    }

}

class Connection {

    public $destination, $trains;

    public function __construct($destination, $train) {
        $this->destination = $destination;
        $this->trains = array($train);
    }

    public function addTrain($train) {
        if (!in_array($train, $this->trains)) {
            $this->trains []= $train;
        }
    }

}

class Train {

    public $location, $edges;

    private static $instances = array();

    public static function all() {
        return self::$instances;
    }

    public static function init() {
        self::$instances = array();
    }

    public function __construct($data) {
        $this->edges = explode(" ", $data);
        $this->location = Station::byName(array_shift($this->edges));
        $this->resolveEdges();
        self::$instances []= $this;
    }

    private function resolveEdges() {
        foreach($this->edges as $edge) {
            list($s1, $s2) = explode("-", $edge);
            $st1 = Station::byName($s1);
            $st2 = Station::byName($s2);
            if (!isset($st1->connections[$st2->name])) {
                $st1->connections[$st2->name] = new Connection($st2, $this);
            } else {
                $st1->connections[$st2->name]->addTrain($this);
            }
            if (!isset($st2->connections[$st1->name])) {
                $st2->connections[$st1->name]= new Connection($st1, $this);
            } else {
                $st2->connections[$st1->name]->addTrain($this);
            }
        }
    }

    public function canSolve($moves, $fuel) {
        foreach ($moves as $move) {
            $fuel -= $move->cost;
        }
        if ($fuel < 0) {
            return false;
        }
        return $this->canSearchPath($this->location->name, $moves, $fuel, array());
    }

    private function canSearchPath($station, $moves, $fuel) {
        if (count($moves) == 0) {
            return true;
        }
        $covered_destinations = array();
        foreach ($moves as $index => $move) {
            if ($move->departure == $station) {
                unset($moves[$index]);
                $covered_destinations []= $move->destination;
                if ($this->canSearchPath($move->destination, $moves, $fuel)) {
                    return true;
                }
                $moves[$index] = $move;
            }
        }
        if ($fuel > 0) {
            foreach (Station::byName($station)->connections as $destination => $connection) {
                if (in_array($this, $connection->trains) && !in_array($destination, $covered_destinations)) {
                    $possibleMove = new Move($station, $destination, 'X');
                    if ($possibleMove->cost <= $fuel && 
                        $this->canSearchPath($destination, $moves, $fuel - $possibleMove->cost)) {
                                return true;
                    }
                }
            }
        }
    }

}

class Move {

    public $departure, $destination, $wagon;
    public $cost;

    public function __construct($departure, $destination, $wagon) {
        $this->departure = $departure;
        $this->destination = $destination;
        $this->wagon = $wagon;
        $this->cost = Station::byName($departure)->distanceTo(Station::byName($destination));
    }

}

class Problem {

    private $stations, $trains, $fuel;

    public function __construct($stations, $trains, $fuel) {
        $this->stations = $stations;
        $this->trains = $trains;
        $this->fuel = $fuel;

        $this->resolveWagons();
    }

    public function solve() {
        $combinator = Combinator::forElements($this->wagons);
        while ($combinator->hasNext()) {
            $combination = $combinator->getNext();
            if ($this->canSolve($combination)) {
                return $combination->score;
            }
        }
        return 0;
    }

    private function canSolve($combination) {
        $ways = $this->explodeWays($this->getWaysForWagons($combination));
        foreach ($ways as $way) {
            if ($this->canSolveWaysForWagons($way)) {
                return true;
            }
        }
        return false;
    }

    private function getWaysForWagons($combination) {
        $ways = array();
        foreach ($combination->elements as $wagon) {
            $ways[$wagon->station->name]= $wagon->station->getWaysTo($wagon->getDestination());
        }
        return $ways;
    }

    private function explodeWays($waysForWagons) {
        $exploded = array(array());
        foreach ($waysForWagons as $wagon => $ways) {
            $new_exploded = array();
            foreach ($exploded as $possibility) {
                foreach ($ways as $way) {
                    $possibility [$wagon]= $way;
                    $new_exploded []= $possibility;
                }
            }
            $exploded = $new_exploded;
        }
        return $exploded;
    }

    private function canSolveWaysForWagons($ways) {
        $moves = $this->getMoves($ways);
        foreach ($this->allocateMoves($moves) as $allocation) {
            $problems = false;
            foreach (Train::all() as $train) {
                if (!$problems) {
                    $toSolve = $allocation[$train->location->name];
                    if (!$train->canSolve($toSolve, $this->fuel)) {
                        $problems = true;
                    }
                }
            }
            if (!$problems) {
                return true;
            }
        }
        return false;
    }

    private function allocateMoves($moves) {
        $baseAllocation = $this->extractBaseAllocation($moves);
        $allocations = array($baseAllocation);
        while (count($moves) > 0) {
            list($T1, $T2) = Train::all();
            $nextMove = array_shift($moves);
            $newAllocations = array();
            foreach ($allocations as $allocation) {
                $newAllocations[]= $this->addMoveToAllocation($allocation, $nextMove, $T1);
                $newAllocations[]= $this->addMoveToAllocation($allocation, $nextMove, $T2);
            }
            $allocations = $newAllocations;
        }
        return $allocations;
    }

    private function addMoveToAllocation($allocation, $move, $train) {
        $allocation[$train->location->name][]= $move;
        return $allocation;
    }

    private function extractBaseAllocation(&$moves) {
        $baseAllocation = array();
        foreach (Train::all() as $train) {
            $baseAllocation[$train->location->name] = array();
        }
        $count = count($moves);
        for ($m = 0; $m < $count; $m++) {
            $move = $moves[$m];
            $departure = Station::byName($move->departure);
            $connection = $departure->connections[$move->destination];
            if (count($connection->trains) == 1) {
                $train = $connection->trains[0];
                $baseAllocation[$train->location->name][] = $move;
                unset($moves[$m]);
            }
        }
        return $baseAllocation;
    }

    private function getMoves($ways) {
        $moves = array();
        foreach ($ways as $wagon => $way) {
            $stops = explode("-", $way);
            $departure = array_shift($stops);
            while (count($stops) > 0) {
                $destination = array_shift($stops);
                $moves []= new Move($departure, $destination, $wagon);
                $departure = $destination;
            }
        }
        return $moves;
    }

    private function resolveWagons() {
        $this->wagons = array();
        foreach($this->stations as $station) {
            $wagon = $station->wagons[0];
            $i = 0;
            while ($i < count($this->wagons) && $this->wagons[$i]->value > $wagon->value) {
                $i++;
            }
            $j = count($this->wagons);
            while ($j > $i) {
                $this->wagons[$j] = $this->wagons[$j - 1];
                $j--;
            }
            $this->wagons[$i] = $wagon;
        }
    }

    public static function read($input) {
        list($s, $r, $fuel) = explode(",", trim(fgets($input)));
        Station::init();
        Train::init();
        $stations = array();
        $trains = array();
        for ($i = 0; $i < $s; $i++) {
            $stations[] = new Station(trim(fgets($input)));
        }
        for ($i = 0; $i < $r; $i++) {
            $trains[] = new Train(trim(fgets($input)));
        }
        return new Problem($stations, $trains, $fuel);
    }

}

$stdin = fopen('php://stdin', 'r');
$numCases = trim(fgets($stdin));
for ($case = 0; $case < $numCases; $case++) {
    $problem = Problem::read($stdin);
    echo $problem->solve()."\n";
}
