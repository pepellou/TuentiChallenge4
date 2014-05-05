#!/usr/bin/php
<?php

define ('CLUSTER_SIZE', 4096);
define ('SECTOR_SIZE',   512);
define ('DIR_RECORD_SIZE', 32);

class StrHex {

    public static function toDecimal($data) {
        $multiplier = 1;
        $result = 0;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $result += ord($data[$i]) * $multiplier;
            $multiplier *= 256;
        }
        return $result;
    }

}

class File {

    private $corrupt = true;
    private $data;

    public function __construct($data = null) {
        $this->data = $data;
        $this->corrupt = ($data == null);
    }

    public function isCorrupt() {
        return $this->corrupt;
    }

    public function md5() {
        return md5($this->data);
    }

}

class Disk {

    private $maxComputableSector = 4194303;
    private $rootClusterAddress = 12279;
    private $fatAddress = 2050;

    public function __construct($path) {
        $this->handle = fopen($path, 'rb');
    }

    public function getFile($path) {
        $parts = explode("/", $path);
        array_shift($parts);
        $currentCluster = 2;
        $currentFileInfo = null;
        while ($currentCluster < 0x0ffffff8 && count($parts) > 0) {
            $nextName = array_shift($parts);
            $files = $this->getFiles($currentCluster);
            $foundName = false;
            foreach ($files as $name => $fileInfo) {
                if (!$foundName && $name == $nextName) {
                    $foundName = true;
                    $currentCluster = $fileInfo['cluster'];
                    $currentFileInfo = $fileInfo;
                }
            }
            if (!$foundName) {
                return new File();
            }
        }
        return new File($this->getFileData($currentFileInfo));
    }

    private function getFileData($fileInfo) {
        $expectedClusters = 1 + (($fileInfo['size'] - 1) >> 12);
        $chain = $this->getClusterChain($fileInfo['cluster']);
        if (count($chain) != $expectedClusters) {
            return null;
        }
        $bytesInLastCluster = $fileInfo['size'] - ($expectedClusters - 1) * CLUSTER_SIZE;

        $data = "";
        $c = 0;
        for (; $c < count($chain) - 1; $c++) {
            $data .= $this->readBytes($this->addressOfCluster($chain[$c]), 0, CLUSTER_SIZE);
        }
        $data .= $this->readBytes(
            $this->addressOfCluster($chain[$c]),
            0,
            $bytesInLastCluster
        );
        return $data;
    }

    private function addressOfCluster($cluster) {
        return $this->rootClusterAddress + ($cluster - 2) * 8;
    }

    private function getFiles($firstCluster) {
        $files = array();
        foreach ($this->getClusterChain($firstCluster) as $cluster) {
            foreach ($this->readFilesInCluster($cluster) as $name => $fileInfo) {
                $files[$name] = $fileInfo;
            }
        }
        return $files;
    }

    private function getClusterChain($cluster) {
        $chain = array();
        $loop = false;
        while (!$loop && $cluster < 0x0ffffff8) {
            if (in_array($cluster, $chain)) {
                $loop = true;
            } else {
                $chain []= $cluster;
                $cluster = $this->getNextCluster($cluster);
            }
        }
        return $loop ? array() : $chain;
    }

    private function getNextCluster($numCluster) {
        $numSector = $numCluster >> 7;
        $numRecord = $numCluster & 0x0000007f;
        return StrHex::toDecimal(
            $this->readBytes(
                $this->fatAddress + $numSector,
                4 * $numRecord,
                4
            )
        );
    }

    private function readBytes($fromSector, $fromByteInSector, $count) {
        fseek($this->handle, 0);
        while ($fromSector > $this->maxComputableSector) {
            fseek($this->handle, $this->maxComputableSector * SECTOR_SIZE, SEEK_CUR);
            $fromSector -= $this->maxComputableSector;
        }
        fseek($this->handle, $fromSector * SECTOR_SIZE + $fromByteInSector, SEEK_CUR);
        return fread($this->handle, $count);
    }

    private function readFilesInCluster($cluster) {
        $files = array();
        $data = $this->readBytes($this->addressOfCluster($cluster), 0, CLUSTER_SIZE);
        for ($record = 0; $record * DIR_RECORD_SIZE < CLUSTER_SIZE; $record++) {
            $name = trim(substr($data, DIR_RECORD_SIZE * $record, 8));
            $ext = trim(substr($data, DIR_RECORD_SIZE * $record + 8, 3));
            $wholeName = ($ext == '') ? $name : "$name.$ext";
            $clusterH = substr($data, DIR_RECORD_SIZE * $record + 20, 2);
            $clusterL = substr($data, DIR_RECORD_SIZE * $record + 26, 2);
            $files[$wholeName] = array(
                'cluster' => StrHex::toDecimal($clusterL.$clusterH),
                'size' => StrHex::toDecimal(substr($data, DIR_RECORD_SIZE * $record + 28, 4))
            );
        }
        return $files;
    }

    public function close() {
        fclose($this->handle);
    }

}

$disk = new Disk(dirname(__FILE__).'/TUENTIDISK.BIN');

$stdin = fopen('php://stdin', 'r');
$numCases =  trim(fgets($stdin));
for ($case = 0; $case < $numCases; $case++) {
    $file = $disk->getFile(trim(fgets($stdin)));
    echo ($file->isCorrupt()) ? 'CORRUPT' : $file->md5();
    echo "\n";
}

$disk->close();
