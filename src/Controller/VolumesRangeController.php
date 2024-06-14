<?php

namespace App\Controller;

use App\Entity\Volume;
use App\Resource\Rang;
use Symfony\Component\HttpFoundation\Request;

class VolumesRangeController extends RangeController
{

    public function __invoke(Request $request): Rang
    {
        $this->setResourceName(Volume::class);
        return $this->getResult($request);
    }

}