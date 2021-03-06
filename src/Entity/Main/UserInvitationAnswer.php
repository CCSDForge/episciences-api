<?php

namespace App\Entity\Main;

use Doctrine\ORM\Mapping as ORM;

/**
 * UserInvitationAnswer
 *
 * @ORM\Table(name="USER_INVITATION_ANSWER")
 * @ORM\Entity
 */
class UserInvitationAnswer
{
    /**
     * @var int
     *
     * @ORM\Column(name="ID", type="integer", nullable=false, options={"unsigned"=true,"comment"="Invitation ID"})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="ANSWER", type="string", length=10, nullable=false)
     */
    private $answer;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="ANSWER_DATE", type="datetime", nullable=false)
     */
    private $answerDate;

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
