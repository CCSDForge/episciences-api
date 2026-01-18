<?php

namespace App\Resource;

use App\Entity\Review;
use Laminas\Feed\Writer\Feed as FeedWriter;

class Rss
{
    private ?Review $review = null;
    private string $feedType = 'rss';
    private ?string $baseUrl = null;



    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(Review $review = null): self
    {
        $this->review = $review;
        return $this;
    }

    public function setBaseUrl(?string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    public function getFeed(): FeedWriter
    {
        return $this->prepareFeedHead();
    }

    private function prepareFeedHead(): FeedWriter
    {
        $domain = 'episciences.org';

        $review = $this->getReview();
        $code = $review ? $review->getCode() : 'portal';
        $journalName = $review ? $review->getName() : 'Episciences';

        $applicationUrl = sprintf('https://%s.%s', $code, $domain);
        $journalLogo = sprintf('%s/logos/logo-%s-small.svg', $applicationUrl, $code);
        $baseUrl = $this->baseUrl ?? $applicationUrl;
        $feedLink = sprintf('%s/api/feed/%s/%s', $baseUrl, $this->getFeedType(), $code);

        $feed = new FeedWriter();

        $feed->setTitle($journalName);
        $feed->setLink($applicationUrl);
        $feed->setFeedLink($feedLink, $this->getFeedType());
        $feed->setDescription($journalName . ': latest publications');
        $feed->setImage(['uri' => $journalLogo, 'title' => $journalName, 'link' => $applicationUrl]);
        $feed->setGenerator('Episciences');

        $feed->addAuthor(['name' => $journalName]);
        $feed->setDateModified(time());
        $feed->addHub('https://pubsubhubbub.appspot.com/');
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