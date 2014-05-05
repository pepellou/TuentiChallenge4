#!/usr/bin/php
<?php

class Particle {

    public $x, $y, $r;

    public function __construct($x, $y, $r) {
        $this->x = $x;
        $this->y = $y;
        $this->r = $r;
    }

    public function collidesWith($particle) {
        $sum_r = $this->r + $particle->r;
        $dx = $particle->x - $this->x;
        $dy = $particle->y - $this->y;
        return ($dx * $dx + $dy * $dy < $sum_r * $sum_r);
    }

}

class Box {

    public $particles = array();
    public $numParticles = 0;
    public $x, $y;

    private static $instances = array();

    public function __construct($x, $y) {
        $this->x = $x;
        $this->y = $y;
        self::$instances[$x][$y] = $this;
    }

    public function addParticle($particle) {
        $this->particles []= $particle;
        $this->numParticles++;
    }

    public static function at($x, $y) {
        return (isset(self::$instances[$x]) && isset(self::$instances[$x][$y]))
            ? self::$instances[$x][$y]
            : null;
    }

    public static function all() {
        return self::$instances;
    }

    public function collisions() {
        return $this->collisionsInside()
            + $this->collisionsWith(Box::at($this->x, $this->y + 1))
            + $this->collisionsWith(Box::at($this->x + 1, $this->y))
            + $this->collisionsWith(Box::at($this->x + 1, $this->y + 1))
            + $this->collisionsWith(Box::at($this->x - 1, $this->y + 1));
    }

    private function collisionsInside() {
        $count = 0;
        for ($i = 0; $i < $this->numParticles; $i++) {
            $p1 = $this->particles[$i];
            for ($j = $i + 1; $j < $this->numParticles; $j++) {
                $p2 = $this->particles[$j];
                if ($p1->collidesWith($p2)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    private function collisionsWith($box) {
        if ($box == null) {
            return 0;
        }
        $count = 0;
        foreach ($this->particles as $p1) {
            foreach ($box->particles as $p2) {
                if ($p1->collidesWith($p2)) {
                    $count++;
                }
            }
        }
        return $count;
    }

}

class Collider {

    public function addParticle($x, $y, $r) {
        $box_x = floor($x / 1000);
        $box_y = floor($y / 1000);
        $box = Box::at($box_x, $box_y);
        if ($box == null) {
            $box = new Box($box_x, $box_y);
        }
        $box->addParticle(new Particle($x, $y, $r));
    }

    public function getCollisions() {
        $count = 0;
        foreach (Box::all() as $i => $row) {
            foreach ($row as $j => $box) {
                $count += $box->collisions();
            }
        }
        return $count;
    }

}

$stdin = fopen('php://stdin', 'r');
while ($problem = fgets($stdin)) {
    $points_file = fopen(dirname(__FILE__).'/points', 'r');
    $collider = new Collider();
    list($start, $count) = explode(",", trim($problem));
    fseek($points_file, 27 * $start);
    for ($i = 0; $i < $count; $i++) {
        list($x, $y, $r) = explode("\t", trim(fgets($points_file)));
        $y = trim($y);
        $r = trim($r);
        $collider->addParticle($x, $y, $r);
    }
    echo $collider->getCollisions()."\n";
    fclose($points_file);
}
