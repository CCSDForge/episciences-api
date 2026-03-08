<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Table(name:'USER_INVITATION_ANSWER')]
#[ORM\Entity]
class UserInvitationAnswer
{

    #[ORM\Column(
        name: 'ID', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: false, options: ['unsigned' => 'true', 'comment' => 'Invitation ID']
    )]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private int $id;
    #[ORM\Colmun(name:'ANSWER', type: 'string', length: 10, nullable: false)]
    private $answer;
    #[ORM\Column(name: 'ANSWER_DATE', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $answerDate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(string $answer): self
    {
        $this->answer = $answer;

        return $this;
    }

    public function getAnswerDate(): ?\DateTimeInterface
    {
        return $this->answerDate;
    }

    public function setAnswerDate(\DateTimeInterface $answerDate): self
    {
        $this->answerDate = $answerDate;

        return $this;
    }


}
