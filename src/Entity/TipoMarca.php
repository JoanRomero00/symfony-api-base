<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\TipoMarcaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TipoMarcaRepository::class)]
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
class TipoMarca
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $descripcion = null;

    #[ORM\Column(length: 1, nullable: true)]
    private ?string $letra = null;    

    /**
     * @var Collection<int, DetalleIntegracion>
     */
    #[ORM\OneToMany(mappedBy: 'tipoMarca', targetEntity: DetalleIntegracion::class)]
    private Collection $detallesIntegracion;

    #[ORM\Column(length: 1)]
    private ?string $estado = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastUserAppId = null;

    //############ PARA AUDITAR ############
    private ?int $storeId = null;

    public function __construct()
    {
        $this->detallesIntegracion = new ArrayCollection();
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

    public function getLetra(): ?string
    {
        return $this->letra;
    }

    public function setLetra(?string $letra): static
    {
        $this->letra = $letra;

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
     * @return Collection<int, DetalleIntegracion>
     */
    public function getDetallesIntegracion(): Collection
    {
        return $this->detallesIntegracion;
    }

    public function addDetalleIntegracion(DetalleIntegracion $detalleIntegracion): static
    {
        if (!$this->detallesIntegracion->contains($detalleIntegracion)) {
            $this->detallesIntegracion[] = $detalleIntegracion;
            $detalleIntegracion->setTipoMarca($this);
        }

        return $this;
    }

    public function removeDetalleIntegracion(DetalleIntegracion $detalleIntegracion): static
    {
        if ($this->detallesIntegracion->removeElement($detalleIntegracion)) {
            if ($detalleIntegracion->getTipoMarca() === $this) {
                $detalleIntegracion->setTipoMarca(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->descripcion;
    }
}
