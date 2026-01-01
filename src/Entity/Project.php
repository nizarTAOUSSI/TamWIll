<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $goalAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $collectedAmount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creator = null;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Contribution::class, orphanRemoval: true)]
    private Collection $contributions;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Comment::class, orphanRemoval: true)]
    private Collection $comments;

    #[ORM\OneToOne(mappedBy: 'project', cascade: ['persist', 'remove'])]
    private ?Scoreboard $scoreboard = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $payoutStatus = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rib = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $payoutRequestedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $payoutCompletedAt = null;

    public function __construct()
    {
        $this->contributions = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->collectedAmount = '0.00';
        $this->status = 'draft';
        $this->payoutStatus = 'pending';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getGoalAmount(): ?string
    {
        return $this->goalAmount;
    }

    public function setGoalAmount(string $goalAmount): static
    {
        $this->goalAmount = $goalAmount;
        return $this;
    }

    public function getCollectedAmount(): float
    {
        return (float) $this->collectedAmount;
    }

    public function setCollectedAmount(float $amount): self
    {
        $this->collectedAmount = (string) $amount;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): static
    {
        $this->creator = $creator;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @return Collection<int, Contribution>
     */
    public function getContributions(): Collection
    {
        return $this->contributions;
    }

    public function addContribution(Contribution $contribution): static
    {
        if (!$this->contributions->contains($contribution)) {
            $this->contributions->add($contribution);
            $contribution->setProject($this);
        }
        return $this;
    }

    public function removeContribution(Contribution $contribution): static
    {
        if ($this->contributions->removeElement($contribution)) {
            if ($contribution->getProject() === $this) {
                $contribution->setProject(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setProject($this);
        }
        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getProject() === $this) {
                $comment->setProject(null);
            }
        }
        return $this;
    }

    public function getScoreboard(): ?Scoreboard
    {
        return $this->scoreboard;
    }

    public function setScoreboard(Scoreboard $scoreboard): static
    {
        if ($scoreboard->getProject() !== $this) {
            $scoreboard->setProject($this);
        }
        $this->scoreboard = $scoreboard;
        return $this;
    }

    public function getPayoutStatus(): ?string
    {
        return $this->payoutStatus;
    }

    public function setPayoutStatus(?string $payoutStatus): static
    {
        $this->payoutStatus = $payoutStatus;
        return $this;
    }

    public function getRib(): ?string
    {
        return $this->rib;
    }

    public function setRib(?string $rib): static
    {
        $this->rib = $rib;
        return $this;
    }

    public function getPayoutRequestedAt(): ?\DateTimeInterface
    {
        return $this->payoutRequestedAt;
    }

    public function setPayoutRequestedAt(?\DateTimeInterface $payoutRequestedAt): static
    {
        $this->payoutRequestedAt = $payoutRequestedAt;
        return $this;
    }

    public function getPayoutCompletedAt(): ?\DateTimeInterface
    {
        return $this->payoutCompletedAt;
    }

    public function setPayoutCompletedAt(?\DateTimeInterface $payoutCompletedAt): static
    {
        $this->payoutCompletedAt = $payoutCompletedAt;
        return $this;
    }
}
