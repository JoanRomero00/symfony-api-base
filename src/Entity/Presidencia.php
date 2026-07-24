<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Contract\ActivatableInterface;
use App\Enum\EstadoRegistro;
use App\Filter\CustomOrderFilter;
use App\Filter\GlobalSearchFilter;
use App\Repository\PresidenciaRepository;
use App\State\Processor\ActivateProcessor;
use App\State\Processor\DeactivateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PresidenciaRepository::class)]
#[ORM\Table(schema: EntitySchema::MAIN)]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'order' => new QueryParameter(
                    filter: CustomOrderFilter::class,
                    properties: [
                        'id',
                        'tribu',
                        'codOrg',
                        'cantSalaPro',
                        'cantSala',
                        'vocSala',
                        'codFuero',
                        'email',
                        'estado',
                    ],
                ),
                'q' => new QueryParameter(
                    filter: GlobalSearchFilter::class,
                    properties: ['tribu', 'codFuero', 'email', 'codOrg'],
                ),
                'estado' => new QueryParameter(
                    filter: new ExactFilter(),
                    property: 'estado',
                    schema: ['type' => 'string', 'enum' => ['A', 'B']],
                ),
            ],
        ),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Patch(
            uriTemplate: '/presidencias/{id}/deactivate',
            input: false,
            deserialize: false,
            processor: DeactivateProcessor::class,
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/presidencias/{id}/activate',
            input: false,
            deserialize: false,
            processor: ActivateProcessor::class,
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
    ],
    order: ['id' => 'ASC'],
)]
class Presidencia implements ActivatableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['usuario:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['usuario:read'])]
    private ?string $tribu = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 99)]
    private ?int $cantSala = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 99)]
    private ?int $vocSala = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 99)]
    private ?int $cantSalaPro = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $fecInst = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 999999999)]
    private ?int $idInst = null;

    #[ORM\Column(length: 40)]
    #[Assert\Email]
    #[Assert\Length(max: 40)]
    private ?string $email = null;

    #[ORM\Column(length: 1)]
    #[ApiProperty(writable: false)]
    #[Assert\Choice(choices: ['A', 'B'])]
    private ?string $estado = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?int $codOrg = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Choice(choices: [0, 1])]
    private ?int $licencia = 0;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull]
    #[Assert\Choice(choices: [0, 1])]
    private ?int $sortComun = 0;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull]
    #[Assert\Choice(choices: [0, 1])]
    private ?int $sortAdHoc = 0;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull]
    #[Assert\Choice(choices: [0, 1])]
    private ?int $sortCinco = 0;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull]
    #[Assert\Choice(choices: [0, 1])]
    private ?int $sortComp = 0;

    /**
     * @var Collection<int, Usuario>
     */
    #[ORM\OneToMany(mappedBy: 'presidencia', targetEntity: Usuario::class)]
    #[ApiProperty(writable: false)]
    private Collection $usuarios;

    /**
     * @var Collection<int, Integracion>
     */
    #[ORM\OneToMany(mappedBy: 'presidencia', targetEntity: Integracion::class)]
    #[ApiProperty(writable: false)]
    private Collection $integracions;

    #[ORM\Column(length: 2, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 2)]
    private ?string $codFuero = null;

    /**
     * @var Collection<int, Vocal>
     */
    #[ORM\OneToMany(mappedBy: 'presidencia', targetEntity: Vocal::class)]
    #[ApiProperty(writable: false)]
    private Collection $vocals;

    #[ORM\Column(nullable: true)]
    #[ApiProperty(writable: false)]
    private ?int $lastUserAppId = null;

    // ############ PARA AUDITAR ############
    #[ApiProperty(readable: false, writable: false)]
    private ?int $storeId = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Choice(choices: [0, 1])]
    private ?int $vocOtroFuero = 0;

    #[ORM\Column(nullable: true)]
    #[Assert\Choice(choices: [0, 1])]
    private ?int $resta = 0;

    #[ORM\Column(length: 1, nullable: true)]
    #[Assert\Choice(choices: [0, 1])]
    private ?int $sorteoAleatorio = 0;

    /**
     * @var Collection<int, Expediente>
     */
    #[ORM\OneToMany(mappedBy: 'presidencia', targetEntity: Expediente::class)]
    #[ApiProperty(writable: false)]
    private Collection $expedientes;

    public function __construct()
    {
        $this->usuarios = new ArrayCollection();
        $this->integracions = new ArrayCollection();
        $this->vocals = new ArrayCollection();
        $this->expedientes = new ArrayCollection();
        $this->estado = EstadoRegistro::ACTIVO->value;
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

    public function getLicencia(): ?int
    {
        return $this->licencia;
    }

    public function setLicencia(?int $licencia): static
    {
        $this->licencia = $licencia;

        return $this;
    }

    public function getSortComun(): ?int
    {
        return $this->sortComun;
    }

    public function setSortComun(int $sortComun): static
    {
        $this->sortComun = $sortComun;

        return $this;
    }

    public function getSortAdHoc(): ?int
    {
        return $this->sortAdHoc;
    }

    public function setSortAdHoc(int $sortAdHoc): static
    {
        $this->sortAdHoc = $sortAdHoc;

        return $this;
    }

    public function getSortCinco(): ?int
    {
        return $this->sortCinco;
    }

    public function setSortCinco(int $sortCinco): static
    {
        $this->sortCinco = $sortCinco;

        return $this;
    }

    public function getSortComp(): ?int
    {
        return $this->sortComp;
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

    public function getResta(): ?int
    {
        return $this->resta;
    }

    public function setResta(?int $resta): static
    {
        $this->resta = $resta;

        return $this;
    }

    public function getVocOtroFuero(): ?int
    {
        return $this->vocOtroFuero;
    }

    public function setVocOtroFuero(?int $vocOtroFuero): static
    {
        $this->vocOtroFuero = $vocOtroFuero;

        return $this;
    }

    public function getSorteoAleatorio(): ?int
    {
        return $this->sorteoAleatorio;
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
