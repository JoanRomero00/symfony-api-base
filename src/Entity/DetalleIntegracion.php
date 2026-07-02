<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\DetalleIntegracionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DetalleIntegracionRepository::class)]
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
class DetalleIntegracion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $canInt = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $motivo = null;

    #[ORM\ManyToOne(targetEntity: TipoMarca::class, inversedBy: 'detallesIntegracion')]
    #[ORM\JoinColumn(nullable: true)]
    private ?TipoMarca $tipoMarca = null;

    #[ORM\ManyToOne(targetEntity: Integracion::class, inversedBy: 'detallesIntegracion')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Integracion $integracion = null;

    #[ORM\ManyToOne(targetEntity: Vocal::class, inversedBy: 'detallesIntegracion')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vocal $vocal = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastUserAppId = null;

    //############ PARA AUDITAR ############
    private ?int $storeId = null;

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

    public function getCanInt(): ?int
    {
        return $this->canInt;
    }

    public function setCanInt(int $canInt): static
    {
        $this->canInt = $canInt;

        return $this;
    }

    public function getMotivo(): ?string
    {
        return $this->motivo;
    }

    public function setMotivo(?string $motivo): static
    {
        $this->motivo = $motivo;

        return $this;
    }

    public function getTipoMarca(): ?TipoMarca
    {
        return $this->tipoMarca;
    }

    public function setTipoMarca(?TipoMarca $tipoMarca): static
    {
        $this->tipoMarca = $tipoMarca;

        return $this;
    }

    public function getIntegracion(): ?Integracion
    {
        return $this->integracion;
    }

    public function setIntegracion(?Integracion $integracion): static
    {
        $this->integracion = $integracion;

        return $this;
    }

    public function getVocal(): ?Vocal
    {
        return $this->vocal;
    }

    public function setVocal(?Vocal $vocal): static
    {
        $this->vocal = $vocal;

        return $this;
    }
}
