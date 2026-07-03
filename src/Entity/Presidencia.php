<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\PresidenciaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PresidenciaRepository::class)]
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
class Presidencia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $tribu = null;

    #[ORM\Column]
    private ?int $cantSala = null;

    #[ORM\Column]
    private ?int $vocSala = null;

    #[ORM\Column(nullable: true)]
    private ?int $cantSalaPro = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $fecInst = null;

    #[ORM\Column]
    private ?int $idInst = null;

    #[ORM\Column(length: 40)]
    private ?string $email = null;

    #[ORM\Column(length: 1)]
    private ?string $estado = null;

    #[ORM\Column]
    private ?int $codOrg = null;

    #[ORM\Column(nullable: true)]
    private ?int $licencia = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $sortComun = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $sortAdHoc = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $sortCinco = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $sortComp = null;

    /**
     * @var Collection<int, Usuario>
     */
    #[ORM\OneToMany(mappedBy: 'presidencia', targetEntity: Usuario::class)]
    private Collection $usuarios;

    /**
     * @var Collection<int, Integracion>
     */
    #[ORM\OneToMany(mappedBy: 'presidencia', targetEntity: Integracion::class)]
    private Collection $integracions;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $codFuero = null;

    /**
     * @var Collection<int, Vocal>
     */
    #[ORM\OneToMany(mappedBy: 'presidencia', targetEntity: Vocal::class)]
    private Collection $vocals;

    #[ORM\Column(nullable: true)]
    private ?int $lastUserAppId = null;

    // ############ PARA AUDITAR ############
    private ?int $storeId = null;

    #[ORM\Column(nullable: true)]
    private ?int $vocOtroFuero = null;

    #[ORM\Column(nullable: true)]
    private ?int $resta = null;

    #[ORM\Column(length: 1, nullable: true)]
    private ?int $sorteoAleatorio = null;

    /**
     * @var Collection<int, Expediente>
     */
    #[ORM\OneToMany(mappedBy: 'presidencia', targetEntity: Expediente::class)]
    private Collection $expedientes;

    public function __construct()
    {
        $this->usuarios = new ArrayCollection();
        $this->integracions = new ArrayCollection();
        $this->vocals = new ArrayCollection();
        $this->expedientes = new ArrayCollection();
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
    // ###########################

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTribu(): ?string
    {
        return $this->tribu;
    }

    public function setTribu(string $tribu): static
    {
        $this->tribu = $tribu;

        return $this;
    }

    public function getCantSala(): ?int
    {
        return $this->cantSala;
    }

    public function setCantSala(int $cantSala): static
    {
        $this->cantSala = $cantSala;

        return $this;
    }

    public function getVocSala(): ?int
    {
        return $this->vocSala;
    }

    public function setVocSala(int $vocSala): static
    {
        $this->vocSala = $vocSala;

        return $this;
    }

    public function getCantSalaPro(): ?int
    {
        return $this->cantSalaPro;
    }

    public function setCantSalaPro(?int $cantSalaPro): static
    {
        $this->cantSalaPro = $cantSalaPro;

        return $this;
    }

    public function getFecInst(): ?\DateTimeInterface
    {
        return $this->fecInst;
    }

    public function setFecInst(\DateTimeInterface $fecInst): static
    {
        $this->fecInst = $fecInst;

        return $this;
    }

    public function getIdInst(): ?int
    {
        return $this->idInst;
    }

    public function setIdInst(int $idInst): static
    {
        $this->idInst = $idInst;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

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

    public function getCodOrg(): ?int
    {
        return $this->codOrg;
    }

    public function setCodOrg(int $codOrg): static
    {
        $this->codOrg = $codOrg;

        return $this;
    }

    public function getLicencia(): ?bool
    {
        return $this->licencia !== null ? (bool) $this->licencia : null;
    }

    public function setLicencia(?int $licencia): static
    {
        $this->licencia = $licencia;

        return $this;
    }

    public function getSortComun(): ?bool
    {
        return $this->sortComun !== null ? (bool) $this->sortComun : null;
    }

    public function setSortComun(int $sortComun): static
    {
        $this->sortComun = $sortComun;

        return $this;
    }

    public function getSortAdHoc(): ?bool
    {
        return $this->sortAdHoc !== null ? (bool) $this->sortAdHoc : null;
    }

    public function setSortAdHoc(int $sortAdHoc): static
    {
        $this->sortAdHoc = $sortAdHoc;

        return $this;
    }

    public function getSortCinco(): ?bool
    {
        return $this->sortCinco !== null ? (bool) $this->sortCinco : null;
    }

    public function setSortCinco(int $sortCinco): static
    {
        $this->sortCinco = $sortCinco;

        return $this;
    }

    public function getSortComp(): ?bool
    {
        return $this->sortComp !== null ? (bool) $this->sortComp : null;
    }

    public function setSortComp(int $sortComp): static
    {
        $this->sortComp = $sortComp;

        return $this;
    }

    /**
     * @return Collection<int, Usuario>
     */
    public function getUsuarios(): Collection
    {
        return $this->usuarios;
    }

    public function addUsuario(Usuario $usuario): static
    {
        if (!$this->usuarios->contains($usuario)) {
            $this->usuarios[] = $usuario;
            $usuario->setPresidencia($this);
        }

        return $this;
    }

    public function removeUsuario(Usuario $usuario): static
    {
        if ($this->usuarios->removeElement($usuario)) {
            if ($usuario->getPresidencia() === $this) {
                $usuario->setPresidencia(null);
            }
        }

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
            $integracion->setPresidencia($this);
        }

        return $this;
    }

    public function removeIntegracion(Integracion $integracion): static
    {
        if ($this->integracions->removeElement($integracion)) {
            if ($integracion->getPresidencia() === $this) {
                $integracion->setPresidencia(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->tribu;
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
            $vocal->setPresidencia($this);
        }

        return $this;
    }

    public function removeVocal(Vocal $vocal): static
    {
        if ($this->vocals->removeElement($vocal)) {
            if ($vocal->getPresidencia() === $this) {
                $vocal->setPresidencia(null);
            }
        }

        return $this;
    }

    public function getResta(): ?bool
    {
        return $this->resta !== null ? (bool) $this->resta : null;
    }

    public function setResta(int $resta): static
    {
        $this->resta = $resta;

        return $this;
    }

    public function getVocOtroFuero(): ?bool
    {
        return $this->vocOtroFuero !== null ? (bool) $this->vocOtroFuero : null;
    }

    public function setVocOtroFuero(int $vocOtroFuero): static
    {
        $this->vocOtroFuero = $vocOtroFuero;

        return $this;
    }

    public function getSorteoAleatorio(): ?bool
    {
        return $this->sorteoAleatorio !== null ? (bool) $this->sorteoAleatorio : null;
    }

    public function setSorteoAleatorio(?int $sorteoAleatorio): static
    {
        $this->sorteoAleatorio = $sorteoAleatorio;

        return $this;
    }

    /**
     * @return Collection<int, Expediente>
     */
    public function getExpedientes(): Collection
    {
        return $this->expedientes;
    }

    public function addExpediente(Expediente $expediente): static
    {
        if (!$this->expedientes->contains($expediente)) {
            $this->expedientes->add($expediente);
            $expediente->setPresidencia($this);
        }

        return $this;
    }

    public function removeExpediente(Expediente $expediente): static
    {
        if ($this->expedientes->removeElement($expediente)) {
            if ($expediente->getPresidencia() === $this) {
                $expediente->setPresidencia(null);
            }
        }

        return $this;
    }
}
