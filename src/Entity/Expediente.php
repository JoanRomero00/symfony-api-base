<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ExpedienteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExpedienteRepository::class)]
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
class Expediente
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, Integracion>
     */
    #[ORM\OneToMany(mappedBy: 'expediente', targetEntity: Integracion::class)]
    private Collection $integracions;

    #[ORM\ManyToOne(targetEntity: Presidencia::class, inversedBy: 'expedientes')]
    private ?Presidencia $presidencia = null;

    #[ORM\Column(nullable: true)]
    private ?int $NumExp = null;

    #[ORM\Column(nullable: true)]
    private ?int $anio = null;

    #[ORM\Column(nullable: true)]
    private ?string $cuij = null;

    #[ORM\Column(length: 255)]
    private ?string $caratula = null;

    #[ORM\Column(length: 1)]
    private ?string $estado = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastUserAppId = null;

    // ############ PARA AUDITAR ############
    private ?int $storeId = null;

    private ?string $nroExpCompleto = null;

    public function __construct()
    {
        $this->integracions = new ArrayCollection();
        $this->estado = 'A';
    }

    public function setnroExpCompleto(string $nroExpCompleto): static
    {
        $this->nroExpCompleto = $nroExpCompleto;

        return $this;
    }

    public function getnroExpCompleto(): ?string
    {
        return $this->NumExp ? ($this->NumExp.'/'.$this->anio) : null;
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
    // ###########################

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumExp(): ?int
    {
        return $this->NumExp;
    }

    public function setNumExp(?int $NumExp): static
    {
        $this->NumExp = $NumExp;

        return $this;
    }

    public function getCuij(): ?string
    {
        return $this->cuij;
    }

    public function setCuij(?string $cuij): static
    {
        $this->cuij = $cuij;

        return $this;
    }

    public function getCaratula(): ?string
    {
        return $this->caratula;
    }

    public function setCaratula(string $caratula): static
    {
        $this->caratula = $caratula;

        return $this;
    }

    public function getAnio(): ?int
    {
        return $this->anio;
    }

    public function setAnio(?int $anio): static
    {
        $this->anio = $anio;

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
     * @return Collection<int, Integracion>
     */
    public function getIntegracions(): Collection
    {
        return $this->integracions;
    }

    public function addIntegracion(Integracion $integracion): static
    {
        if (!$this->integracions->contains($integracion)) {
            $this->integracions[] = $integracion;
            $integracion->setExpediente($this);
        }

        return $this;
    }

    public function removeIntegracion(Integracion $integracion): static
    {
        if ($this->integracions->removeElement($integracion)) {
            // set the owning side to null (unless already changed)
            if ($integracion->getExpediente() === $this) {
                $integracion->setExpediente(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->caratula;
    }

    public function getPresidencia(): ?Presidencia
    {
        return $this->presidencia;
    }

    public function setPresidencia(?Presidencia $presidencia): static
    {
        $this->presidencia = $presidencia;

        return $this;
    }
}
