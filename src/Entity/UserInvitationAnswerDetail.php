<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'USER_INVITATION_ANSWER_DETAIL')]
#[ORM\Entity]
class UserInvitationAnswerDetail
{

    #[ORM\Column(
        name: 'ID', type: 'integer', nullable: false, options: ['unsigned' => true, 'comment' => 'Invitation ID']
    )]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;

    #[ORM\Column(name:'NAME', type: 'string', length: 30, nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private $name;

    #[ORM\Column(name: 'VALUE', type: 'string', length: 500, nullable: true)]
    private $value;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;

        return $this;
    }


}
