<?php

namespace App\Controller;

use App\Entity\News;

use App\Resource\Range;

use Symfony\Component\HttpFoundation\Request;


class NewsRangeController extends RangeController
{

    public function __invoke(Request $request): Range
    {
        $this->setResourceName(News::class);
        return $this->getResult($request);
    }

}