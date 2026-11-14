<?php

namespace App\Resource;

use Symfony\Component\Serializer\Attribute\Groups;

class Journal
{
    /**
     * The public domain under which each journal is served (https://{code}.{domain}).
     */
    public const string DOMAIN = 'episciences.org';

    /**
     * The sequential position within the journals collection. It is a duplicate of
     * `rvid` on a single journal, so it is only exposed by the collection (a 1-based
     * counter renumbered at each read), not by the item endpoint.
     */
    #[groups(['read:Reviews', 'read:Reviews:JournalCollection'])]
    private int $number;

    #[groups(['read:Reviews'])]
    private ?int $rvid;

    #[groups(['read:Reviews'])]
    private ?string $code;

    #[groups(['read:Reviews'])]
    private ?string $title;

    #[groups(['read:Reviews'])]
    private string $subtitle;

    #[groups(['read:Reviews'])]
    private string $issn;

    #[groups(['read:Reviews'])]
    private string $eissn;

    #[groups(['read:Reviews'])]
    private string $address;

    #[groups(['read:Reviews'])]
    private array $acceptedRepositories;

    public function getNumber(): int
    {
        return $this->number;
    }

    public function setNumber(int $number): self
    {
        $this->number = $number;
        return $this;
    }

    public function getRvid(): ?int
    {
        return $this->rvid;
    }

    public function setRvid(?int $rvid): self
    {
        $this->rvid = $rvid;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getSubtitle(): string
    {
        return $this->subtitle;
    }

    public function setSubtitle(string $subtitle): self
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    public function getIssn(): string
    {
        return $this->issn;
    }

    public function setIssn(string $issn): self
    {
        $this->issn = $issn;
        return $this;
    }

    public function getEissn(): string
    {
        return $this->eissn;
    }

    public function setEissn(string $eissn): self
    {
        $this->eissn = $eissn;
        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }

    /**
     * The labels of the repositories to which the journal's accepted papers are submitted,
     * indexed by repository id, excluding those disabled by the journal.
     *
     * @return array<int, string>
     */
    public function getAcceptedRepositories(): array
    {
        return $this->acceptedRepositories;
    }

    /**
     * @param array<int, string> $acceptedRepositories
     */
    public function setAcceptedRepositories(array $acceptedRepositories): self
    {
        $this->acceptedRepositories = $acceptedRepositories;
        return $this;
    }
}
