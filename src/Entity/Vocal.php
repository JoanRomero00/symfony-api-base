<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\VocalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VocalRepository::class)]
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
class Vocal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $sala = null;

    #[ORM\Column(length: 30)]
    private ?string $nombre = null;

    #[ORM\Column]
    private ?int $numero = null;

    #[ORM\Column(length: 1)]
    private ?string $estado = null;

    #[ORM\Column]
    private ?int $adHoc = null;

    #[ORM\Column]
    private ?int $orden = null;

    #[ORM\Column]
    private ?int $asigna = null;

    #[ORM\Column]
    private ?int $integra = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fecBaja = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $motBaja = null;

    #[ORM\Column]
    private ?int $vocPropio = null;

    /**
     * @var Collection<int, Licencia>
     */
    #[ORM\OneToMany(mappedBy: 'vocal', targetEntity: Licencia::class)]
    private Collection $licencias;

    #[ORM\ManyToOne(targetEntity: Fuero::class, inversedBy: 'vocals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Fuero $fuero = null;

    /**
     * @var Collection<int, DetalleIntegracion>
     */
    #[ORM\OneToMany(mappedBy: 'vocal', targetEntity: DetalleIntegracion::class)]
    private Collection $detallesIntegracion;

    #[ORM\ManyToOne(targetEntity: Presidencia::class, inversedBy: 'vocals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Presidencia $presidencia = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastUserAppId = null;

    //############ PARA AUDITAR ############
    private ?int $storeId = null;

    public function __construct()
    {
        $this->licencias = new ArrayCollection();
        $this->detallesIntegracion = new ArrayCollection();
        $this->adHoc = 0;
        $this->integra = 0;
        $this->orden = 0;
        $this->asigna = 0;
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

    public function getSala(): ?int
    {
        return $this->sala;
    }

    public function setSala(int $sala): static
    {
        $this->sala = $sala;

        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getNumero(): ?int
    {
        return $this->numero;
    }

    public function setNumero(int $numero): static
    {
        $this->numero = $numero;

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

    public function getAdHoc(): ?int
    {
        return $this->adHoc;
    }

    public function setAdHoc(int $adHoc): static
    {
        $this->adHoc = $adHoc;

        return $this;
    }

    public function getOrden(): ?int
    {
        return $this->orden;
    }

    public function setOrden(int $orden): static
    {
        $this->orden = $orden;

        return $this;
    }

    public function getAsigna(): ?int
    {
        return $this->asigna;
    }

    public function setAsigna(int $asigna): static
    {
        $this->asigna = $asigna;

        return $this;
    }

    public function getIntegra(): ?int
    {
        return $this->integra;
    }

    public function setIntegra(int $integra): static
    {
        $this->integra = $integra;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getFecBaja(): ?\DateTimeInterface
    {
        return $this->fecBaja;
    }

    public function setFecBaja(?\DateTimeInterface $fecBaja): static
    {
        $this->fecBaja = $fecBaja;

        return $this;
    }

    public function getMotBaja(): ?string
    {
        return $this->motBaja;
    }

    public function setMotBaja(?string $motBaja): static
    {
        $this->motBaja = $motBaja;

        return $this;
    }

    public function getVocPropio(): ?bool
    {
        return $this->vocPropio !== null ? (bool)$this->vocPropio : null;
    }

    public function setVocPropio(int $vocPropio): static
    {
        $this->vocPropio = $vocPropio;

        return $this;
    }

    /**
     * @return Collection<int, Licencia>
     */
    public function getLicencias(): Collection
    {
        return $this->licencias;
    }

    public function addLicencia(Licencia $licencia): static
    {
        if (!$this->licencias->contains($licencia)) {
            $this->licencias[] = $licencia;
            $licencia->setVocal($this);
        }

        return $this;
    }

    public function removeLicencia(Licencia $licencia): static
    {
        if ($this->licencias->removeElement($licencia)) {
            if ($licencia->getVocal() === $this) {
                $licencia->setVocal(null);
            }
        }

        return $this;
    }

    public function getFuero(): ?Fuero
    {
        return $this->fuero;
    }

    public function setFuero(?Fuero $fuero): static
    {
        $this->fuero = $fuero;

        return $this;
    }

    /**
     * @return Collection<int, DetalleIntegracion>
     */
    public function getDetallesIntegracion(): Collection
    {
        return $this->detallesIntegracion;
    }

    public function __toString(): string
    {
        return (string) $this->nombre;
    }

    public function addDetalleIntegracion(DetalleIntegracion $detalleIntegracion): static
    {
        if (!$this->detallesIntegracion->contains($detalleIntegracion)) {
            $this->detallesIntegracion[] = $detalleIntegracion;
            $detalleIntegracion->setVocal($this);
        }

        return $this;
    }

    public function removeDetalleIntegracion(DetalleIntegracion $detalleIntegracion): static
    {
        if ($this->detallesIntegracion->removeElement($detalleIntegracion)) {
            if ($detalleIntegracion->getVocal() === $this) {
                $detalleIntegracion->setVocal(null);
            }
        }

        return $this;
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
