<?php

/**
 * Almacén key-value de configuración dinámica del sistema, editable sin redeploy.
 */

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Filter\CustomOrderFilter;
use App\Filter\GlobalSearchFilter;
use App\Repository\ConfiguracionSistemaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ConfiguracionSistemaRepository::class)]
#[ORM\Table(schema: EntitySchema::MAIN, options: ['comment' => 'Informacion de variables de configuración del sistema.'])]
#[ApiResource(
    security: "is_granted('ROLE_SUPER_ADMIN')",
    operations: [
        new GetCollection(
            parameters: [
                // Ordenamiento
                'order' => new QueryParameter(
                    filter: CustomOrderFilter::class
                ),
                // Búsqueda Global 'q'
                'q' => new QueryParameter(
                    filter: GlobalSearchFilter::class,
                    properties: [
                        'id',
                        'clave',
                        'valor',
                        'observaciones',
                    ]
                ),
            ]),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
    ]
)]
class ConfiguracionSistema
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'ID registro de configuracion'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, options: ['comment' => 'Clave del registro'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $clave = null;

    #[ORM\Column(length: 255, options: ['comment' => 'Valor del Registro'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $valor = null;

    #[ORM\Column(
        type: Types::TEXT,
        nullable: true,
        options: ['comment' => 'Observación del Registro']
    )]
    private ?string $observaciones = null;

    #[ORM\Column(
        nullable: true,
        options: [
            'comment' => 'ID del usuario que generó el alta o última modific. en el Registro',
        ]
    )]
    private ?int $lastUserAppId = null;

    /**
     * Variable no mapeada. Usada para auditar al momento de borrar la entidad.
     */
    private $storeId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClave(): ?string
    {
        return $this->clave;
    }

    public function setClave(string $clave): static
    {
        $this->clave = $clave;

        return $this;
    }

    public function getValor(): ?string
    {
        return $this->valor;
    }

    public function setValor(string $valor): static
    {
        $this->valor = $valor;

        return $this;
    }

    public function getObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function setObservaciones(?string $observaciones): static
    {
        $this->observaciones = $observaciones;

        return $this;
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

    public function setStoreId(int $storeId): self
    {
        $this->storeId = $storeId;

        return $this;
    }

    public function getStoreId(): ?string
    {
        return $this->storeId;
    }
}
