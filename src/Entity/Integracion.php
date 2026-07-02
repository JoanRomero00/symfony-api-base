<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\IntegracionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntegracionRepository::class)]
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
class Integracion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $fecha = null;

    #[ORM\Column]
    private ?int $sala = null;

    #[ORM\Column]
    private ?string $tipo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observacion = null;

    #[ORM\Column(length: 1)]
    private ?string $estado = null;

    /**
     * @var Collection<int, DetalleIntegracion>
     */
    #[ORM\OneToMany(mappedBy: 'integracion', targetEntity: DetalleIntegracion::class)]
    private Collection $detallesIntegracion;

    #[ORM\ManyToOne(targetEntity: Presidencia::class, inversedBy: 'integracions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Presidencia $presidencia = null;

    #[ORM\ManyToOne(targetEntity: Expediente::class, inversedBy: 'integracions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Expediente $expediente = null;

    #[ORM\Column(nullable: true)]
    private ?int $nroIntegra = null;

    #[ORM\Column(nullable: true)]
    private ?int $anioIntegra = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaReintegro = null;

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

    public function getFecha(): ?\DateTimeInterface
    {
        return $this->fecha;
    }

    public function setFecha(\DateTimeInterface $fecha): static
    {
        $this->fecha = $fecha;
        return $this;
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

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;
        return $this;
    }

    public function getObservacion(): ?string
    {
        return $this->observacion;
    }

    public function setObservacion(?string $observacion): static
    {
        $this->observacion = $observacion;
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
            $detalleIntegracion->setIntegracion($this);
        }

        return $this;
    }

    public function removeDetalleIntegracion(DetalleIntegracion $detalleIntegracion): static
    {
        if ($this->detallesIntegracion->removeElement($detalleIntegracion)) {
            // set the owning side to null (unless already changed)
            if ($detalleIntegracion->getIntegracion() === $this) {
                $detalleIntegracion->setIntegracion(null);
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

    public function getExpediente(): ?Expediente
    {
        return $this->expediente;
    }

    public function setExpediente(?Expediente $expediente): static
    {
        $this->expediente = $expediente;

        return $this;
    }

    public function getNroIntegra(): ?int
    {
        return $this->nroIntegra;
    }

    public function setNroIntegra(?int $nroIntegra): static
    {
        $this->nroIntegra = $nroIntegra;
        return $this;
    }

    public function getAnioIntegra(): ?int
    {
        return $this->anioIntegra;
    }

    public function setAnioIntegra(?int $anioIntegra): static
    {
        $this->anioIntegra = $anioIntegra;
        return $this;
    }

    public function getFechaReintegro(): ?\DateTimeInterface
    {
        return $this->fechaReintegro;
    }

    public function setFechaReintegro(?\DateTimeInterface $fechaReintegro): static
    {
        $this->fechaReintegro = $fechaReintegro;

        return $this;
    }

    public function nroAnioIntegra(): ?string
    {
        if ($this->nroIntegra === null || $this->anioIntegra === null) {
            return null;
        }
        return str_pad((string)$this->nroIntegra, 4, '0', STR_PAD_LEFT) . '/' . $this->anioIntegra;
    }

    public function getMes(): ?string
    {
        if ($this->fecha === null) {
            return null;
        }
        $meses = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        $numeroMes = (int) $this->fecha->format('n');
        return $meses[$numeroMes] ?? null;
    }

    public function getVocalSorteado(): array
    {
        $detalles = $this->getDetallesIntegracion();
        $vocal = [];
        foreach ($detalles as $detalle) {
            if ($detalle->getTipoMarca() && $detalle->getTipoMarca()->getLetra() === 'S') {
                $vocal[] = $detalle->getVocal();
            }
        }
        return $vocal;
    }
}