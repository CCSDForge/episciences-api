<?php

namespace App\Resource;


use App\Entity\Review;
use Laminas\Feed\Writer\Feed as FeedWriter;

class Rss
{
    private ?Review $review;
    private string $feedType = 'rss';



    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(Review $review = null): self
    {
        $this->review = $review;
        return $this;
    }

    public function getFeed(): FeedWriter
    {
        return $this->prepareFeedHead();
    }

    private function prepareFeedHead(): FeedWriter
    {
        $domain = 'episciences.org';

        $code  = $this->getReview() ? $this->getReview()->getCode() : 'portal';
        $applicationUrl = 'https://' . $code . '.' . $domain;


        $feed = new FeedWriter();
        $feedType = $this->getFeedType();

        $feed->setTitle('episciences.org - Latest papers');
        $feed->setLink($applicationUrl);
        $feed->setFeedLink($applicationUrl . '/feed/' . $feedType, $feedType);
        $feed->setDescription('Latest papers');
        $feed->setImage(['uri' => $applicationUrl . '/img/episciences_sign_50x50.png', 'title' => $domain, 'link' => $applicationUrl]);
        $feed->setGenerator($domain);

        $feed->addAuthor(['name' => $domain]);
        $feed->setDateModified(time());
        $feed->addHub('http://pubsubhubbub.appspot.com/');
        return $feed;
    }

    public function getFeedType(): string
    {
        return $this->feedType;
    }

    public function setFeedType(string $feedType): void
    {
        $this->feedType = $feedType;
    }

}