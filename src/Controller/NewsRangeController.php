<?php

namespace App\Controller;

use App\Entity\News;

use App\Resource\Rang;

use Symfony\Component\HttpFoundation\Request;


class NewsRangeController extends RangeController
{

    public function __invoke(Request $request): Rang
    {
        $this->setResourceName(News::class);
        return $this->getResult($request);
    }

}