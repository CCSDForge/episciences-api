<?php

namespace App\Controller;

use App\Entity\Volume;
use App\Resource\Range;
use Symfony\Component\HttpFoundation\Request;

class VolumesRangeController extends RangeController
{

    public function __invoke(Request $request): Range
    {
        $this->setResourceName(Volume::class);
        return $this->getResult($request);
    }

}