<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\FueroRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FueroRepository::class)]
#[ORM\Table(schema: EntitySchema::MAIN)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
    ]
)]
class Fuero
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $descripcion = null;

    /**
     * @var Collection<int, Vocal>
     */
    #[ORM\OneToMany(mappedBy: 'fuero', targetEntity: Vocal::class)]
    private Collection $vocals;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $codFuero = null;

    #[ORM\Column(length: 1)]
    private ?string $estado = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastUserAppId = null;

    //############ PARA AUDITAR ############
    private ?int $storeId = null;

    public function __construct()
    {
        $this->vocals = new ArrayCollection();
        $this->estado = 'A';
    }

    public function setStoreId(int $storeId): static
    {
        $this->storeId = $storeId;
        return $this;
    }

    public function getStoreId(): ?string
    {
        return $this->storeId !== null ? (string) $this->storeId : null;
    }

    public function getLastUserAppId(): ?int
    {
        return $this->lastUserAppId;
    }

    public function setLastUserAppId(?int $lastUserAppId): static
    {
        $this->lastUserAppId = $lastUserAppId;

        return $this;
    }
    //########################### 

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(string $descripcion): static
    {
        $this->descripcion = $descripcion;

        return $this;
    }

    public function getEstado(): ?string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): static
    {
        $this->estado = $estado;

        return $this;
    }

    /**
     * @return Collection<int, Vocal>
     */
    public function getVocals(): Collection
    {
        return $this->vocals;
    }

    public function addVocal(Vocal $vocal): static
    {
        if (!$this->vocals->contains($vocal)) {
            $this->vocals[] = $vocal;
            $vocal->setFuero($this);
        }

        return $this;
    }

    public function removeVocal(Vocal $vocal): static
    {
        if ($this->vocals->removeElement($vocal)) {
            // set the owning side to null (unless already changed)
            if ($vocal->getFuero() === $this) {
                $vocal->setFuero(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->descripcion;
    }

    public function getCodFuero(): ?string
    {
        return $this->codFuero;
    }

    public function setCodFuero(?string $codFuero): static
    {
        $this->codFuero = $codFuero;

        return $this;
    }
}
