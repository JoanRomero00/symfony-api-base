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
use App\Repository\TipoMarcaRepository;
use App\State\Processor\ActivateProcessor;
use App\State\Processor\DeactivateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TipoMarcaRepository::class)]
#[ORM\Table(schema: EntitySchema::MAIN)]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'order' => new QueryParameter(
                    filter: CustomOrderFilter::class,
                    properties: ['id', 'letra', 'descripcion', 'estado'],
                ),
                'q' => new QueryParameter(
                    filter: GlobalSearchFilter::class,
                    properties: ['id', 'letra', 'descripcion'],
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
            uriTemplate: '/tipo_marcas/{id}/deactivate',
            input: false,
            deserialize: false,
            processor: DeactivateProcessor::class,
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/tipo_marcas/{id}/activate',
            input: false,
            deserialize: false,
            processor: ActivateProcessor::class,
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
    ],
    order: ['id' => 'ASC'],
)]
class TipoMarca implements ActivatableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private ?string $descripcion = null;

    #[ORM\Column(length: 1, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 1)]
    private ?string $letra = null;

    /**
     * @var Collection<int, DetalleIntegracion>
     */
    #[ORM\OneToMany(mappedBy: 'tipoMarca', targetEntity: DetalleIntegracion::class)]
    #[ApiProperty(writable: false)]
    private Collection $detallesIntegracion;

    #[ORM\Column(length: 1)]
    #[ApiProperty(writable: false)]
    #[Assert\Choice(choices: ['A', 'B'])]
    private ?string $estado = null;

    #[ORM\Column(nullable: true)]
    #[ApiProperty(writable: false)]
    private ?int $lastUserAppId = null;

    // ############ PARA AUDITAR ############
    #[ApiProperty(readable: false, writable: false)]
    private ?int $storeId = null;

    public function __construct()
    {
        $this->detallesIntegracion = new ArrayCollection();
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
