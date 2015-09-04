<?php
declare(ticks=10000); //required for ctrl+c capture

class image
{
    private $polygons = [];

    private $size = 128;

    public function mutate($rate)
    {
        if (rand(1,100) < $rate) {
            $this->mix();
        }
        if (rand(1,100) < $rate) {
            $this->removePolygon();
        }
        if (rand(1,100) < $rate) {
            $this->addPolygon();
        }
        foreach($this->polygons as $i => $polygon) {
            if (rand(1,100) < $rate) {
                $this->polygons[$i] = $this->mutatePolygon($rate, $polygon);
            }
        }
    }

    private function addPolygon()
    {
        $toInsert = [$this->generatePolygon()];
        array_splice($this->polygons, rand(0,count($this->polygons)), 0, $toInsert);
    }

    private function removePolygon()
    {
        if (count($this->polygons) == 0) return;
        unset($this->polygons[rand(0, count($this->polygons)-1)]);
        $this->polygons = array_values($this->polygons);
    }

    private function mutatePolygon($rate, $polygon)
    {
        foreach($polygon as $i => $point) {
            $polygon[$i] += rand(-$rate, $rate);
        }
        if ($polygon[0] < 0) {
            $polygon[0] += 256;
        } elseif($polygon[0] > 255) {
            $polygon[0] -= 256;
        }
        if ($polygon[1] < 0) {
            $polygon[1] += 128;
        } elseif($polygon[0] > 127) {
            $polygon[1] -= 128;
        }
        return $polygon;
    }

    private function generatePolygon()
    {
        $polygon = [];
        for($i =0; $i < rand(4,10)*2; $i++) {
            $max = ($i == 0 ? 255 : ($i == 1 ? 127 : $this->size));
            $polygon[] = rand(1,$max);
        }
        return $polygon;
    }

    private function draw()
    {
        $image = imagecreatetruecolor($this->size, $this->size);
        imagealphablending($image, true);
        imagefilledrectangle($image,0,0,$this->size, $this->size, imagecolorallocatealpha($image,0,0,0,0));
        foreach($this->polygons as $polygon) {
            $color = imagecolorallocatealpha($image, $polygon[0],$polygon[0],$polygon[0],$polygon[1]);
            imagefilledpolygon($image, array_slice($polygon,2), (count($polygon)/2)-1,$color);
        }
        return $image;
    }

    public function output($filename)
    {
        $image = $this->draw();
        imagepng($image, $filename);
        imagedestroy($image);
    }

    public function compare($fromImage)
    {
        $toImage = $this->draw();
        $totalDiff = 0;
        for($x=0;$x<$this->size;$x++)
        {
            for($y=0;$y<$this->size;$y++)
            {
                $from = imagecolorat($fromImage,$x,$y);
                $to = imagecolorat($toImage,$x,$y);
                $fromR = ($from >> 16) & 0xFF;
                $fromG = ($from >> 8) & 0xFF;
                $fromB = $from & 0xFF;
                $toR = ($to >> 16) & 0xFF;
                $toG = ($to >> 8) & 0xFF;
                $toB = $to & 0xFF;
                $diff = abs($fromR - $toR) + abs($fromB - $toB) + abs($fromG - $toG);
                $totalDiff += $diff;
            }
        }
        imagedestroy($toImage);
        return $totalDiff;
    }

    private function mix()
    {
        $index1 = rand(0,count($this->polygons)-1);
        $index2 = rand(0,count($this->polygons)-1);
        if ($index1 != $index2) {
            $temp = $this->polygons[$index1];
            $this->polygons[$index1] = $this->polygons[$index2];
            $this->polygons[$index2] = $temp;
        }
    }
}


$sourceImage = "lena.png";
$mutationRate = 2;
$populationSize = 100;


$stop = false;

$handler = function ($signal) {
    global $stop;
    $stop = true;
    echo "\n\nexiting\n\n";
};

pcntl_signal(SIGINT, $handler); //capture ctrl+c


$population = [];
if (!file_exists("save")) {
    for ($i = 0; $i < $populationSize; $i++) {
        $population[] = new image();
        $population[$i]->mutate(100); //bump it with an initial mutation
    }
} else {
    $savedImage = unserialize(file_get_contents("save"));
    for ($i = 0; $i < $populationSize; $i++) {
        $population[] = clone $savedImage;
    }
}
$source = imagecreatefrompng($sourceImage);

$bestImage = null;

$iteration = 1;
while(!$stop) {
    $bestDiff = 10000000000;
    foreach($population as $i => $image) {
        if ($i != 0) {  //don't mutate 0 - so it's the same as the best from the last iteration
            $image->mutate($mutationRate);
        }
        $diff = $image->compare($source);
        if ($diff < $bestDiff) {
            $bestDiff = $diff;
            $bestImage = $image;
            $bestMutationRate = $mutationRate;
        }
    }
    echo ".";
    $bestImage->output("best.png");
    echo "$iteration:$bestDiff:$bestMutationRate\n";

    for($i=0;$i<count($population);$i++) {
        $population[$i] = clone $bestImage;
    }
    $iteration++;
}


echo "Stopped";
$serializedBest = serialize($bestImage);
file_put_contents("save", $serializedBest);
