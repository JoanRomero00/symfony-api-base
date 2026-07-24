<?php

/**
 * Entidad de usuario del sistema: autenticación JWT, roles JSONB, baja lógica. Base para cualquier proyecto.
 */

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Dto\ChangePasswordDto;
use App\Dto\CreateUsuarioDto;
use App\Dto\UpdateUsuarioDto;
use App\Filter\EstadoUsuarioFilter;
use App\Filter\GlobalSearchFilter;
use App\Filter\UsuarioSortFilter;
use App\Repository\UsuarioRepository;
use App\State\Processor\BajaLogicaProcessor;
use App\State\Processor\ChangePasswordProcessor;
use App\State\Processor\ForceTwoFactorAllProcessor;
use App\State\Processor\ForceTwoFactorProcessor;
use App\State\Processor\ReactivarProcessor;
use App\State\Processor\ResetPasswordProcessor;
use App\State\Processor\UsuarioWriteProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UsuarioRepository::class)]
#[ORM\Table(schema: EntitySchema::MAIN)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[UniqueEntity(
    fields: ['username'],
    message: 'Este nombre de usuario ya está en uso.'
)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['usuario:read']],
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'search' => new QueryParameter(
                    filter: GlobalSearchFilter::class,
                    properties: [
                        'username',
                        'nombre',
                        'apellido',
                    ],
                    description: 'Busca por usuario, apellido o nombre.',
                ),
                'q' => new QueryParameter(
                    filter: GlobalSearchFilter::class,
                    properties: ['username', 'nombre', 'apellido'],
                    description: 'Alias compatible del parámetro search.',
                ),
                'estado' => new QueryParameter(
                    filter: EstadoUsuarioFilter::class,
                    schema: ['type' => 'string', 'enum' => ['activos', 'baja', 'todos']],
                ),
                'sort' => new QueryParameter(
                    filter: UsuarioSortFilter::class,
                    schema: [
                        'type' => 'string',
                        'enum' => [
                            'id',
                            'username',
                            'apellido',
                            'nombre',
                            'email',
                            'presidencia',
                            'fechaAlta',
                            'fechaBaja',
                            'ultimoAcceso',
                            'cantidadAccesos',
                        ],
                    ],
                ),
                'direction' => new QueryParameter(
                    filter: UsuarioSortFilter::class,
                    schema: ['type' => 'string', 'enum' => ['asc', 'desc']],
                ),
                'limit' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 9999],
                    description: 'Cantidad de registros por página.',
                ),
            ],
        ),
        new Get(
            normalizationContext: ['groups' => ['usuario:read']],
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Post(
            input: CreateUsuarioDto::class,
            normalizationContext: ['groups' => ['usuario:read', 'usuario:mail'], 'skip_null_values' => true],
            denormalizationContext: ['allow_extra_attributes' => false],
            security: "is_granted('ROLE_ADMIN')",
            processor: UsuarioWriteProcessor::class,
        ),
        new Patch(
            input: UpdateUsuarioDto::class,
            normalizationContext: ['groups' => ['usuario:read']],
            denormalizationContext: ['allow_extra_attributes' => false],
            security: "is_granted('ROLE_ADMIN')",
            processor: UsuarioWriteProcessor::class,
        ),
        new Delete(
            deserialize: false,
            input: false,
            output: false,
            processor: BajaLogicaProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/usuarios/{id}/reactivar',
            status: Response::HTTP_OK,
            deserialize: false,
            input: false,
            normalizationContext: ['groups' => ['usuario:read']],
            processor: ReactivarProcessor::class,
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Post(
            uriTemplate: '/usuarios/{id}/reset-password',
            status: Response::HTTP_OK,
            deserialize: false,
            input: false,
            normalizationContext: ['groups' => ['usuario:read', 'usuario:mail'], 'skip_null_values' => true],
            processor: ResetPasswordProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/usuarios/{id}/force-2fa',
            status: Response::HTTP_OK,
            deserialize: false,
            input: false,
            normalizationContext: ['groups' => ['usuario:read']],
            processor: ForceTwoFactorProcessor::class,
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Post(
            uriTemplate: '/usuarios/force-2fa-all',
            status: Response::HTTP_NO_CONTENT,
            read: false,
            deserialize: false,
            input: false,
            output: false,
            processor: ForceTwoFactorAllProcessor::class,
            security: "is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Post(
            uriTemplate: '/usuarios/cambiar-clave',
            input: ChangePasswordDto::class,
            output: false,
            processor: ChangePasswordProcessor::class,
            security: "is_granted('ROLE_USER')",
        ),
    ],
    order: ['username' => 'ASC'],
)]
class Usuario implements \Stringable, UserInterface, PasswordAuthenticatedUserInterface
{
    public const ALLOWED_ROLES = [
        'ROLE_CONSULTA_PRESIDENCIA',
        'ROLE_EMPLEADO_PRESIDENCIA',
        'ROLE_RESPONSABLE_PRESIDENCIA',
        'ROLE_ADMIN',
        'ROLE_SUPER_ADMIN',
    ];
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'ID Usuario.'])]
    #[Groups(['usuario:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, options: ['comment' => 'Nombre del Usuario.'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 180)]
    #[Groups(['usuario:read'])]
    private ?string $username = null;

    #[ORM\Column(type: 'json', options: ['jsonb' => true, 'comment' => 'Roles del Usuario.'])]
    #[Assert\NotBlank]
    #[Assert\Type('array')]
    #[Assert\Count(min: 1)]
    #[Assert\All([
        new Assert\Type('string'),
        new Assert\NotBlank(),
    ])]
    #[Groups(['usuario:read'])]
    private array $roles = [];

    /**
     * @var string Password hasheada
     */
    #[ORM\Column(options: ['comment' => 'Password del Usuario.'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1)]
    private ?string $password = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'DNI del Usuario.'])]
    #[Assert\Range(min: 1, max: 999999999)]
    #[Groups(['usuario:read'])]
    private ?int $dni = null;

    #[ORM\Column(length: 50, options: ['comment' => 'Utilizado para mostrar el Usuario Logueado.'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 50)]
    #[Groups(['usuario:read'])]
    private ?string $apellido = null;

    #[ORM\Column(length: 50, nullable: true, options: ['comment' => 'Utilizado para mostrar el usuario Logueado.'])]
    #[Assert\Length(max: 50)]
    #[Groups(['usuario:read'])]
    private ?string $nombre = null;

    #[ORM\Column(length: 50, options: ['comment' => 'Dirección del Correo Electrónico.'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 50)]
    #[Assert\Email]
    #[Groups(['usuario:read'])]
    private ?string $email = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, options: ['comment' => 'Fecha de Alta.'])]
    #[ApiProperty(required: true)]
    #[Groups(['usuario:read'])]
    private ?\DateTimeInterface $fechaAlta = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true, options: ['comment' => 'Fecha de Baja.'])]
    #[Groups(['usuario:read'])]
    private ?\DateTimeInterface $fechaBaja = null;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE, nullable: true, options: ['comment' => 'Fecha del último acceso.'])]
    #[Groups(['usuario:read'])]
    private ?\DateTimeInterface $ultimoAcceso = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, options: ['comment' => 'Cantidad de accesos efectuados por el usuario.'])]
    #[Groups(['usuario:read'])]
    private ?int $cantidadAccesos = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, options: ['comment' => 'ID del usuario que generó el alta o última modific. en el Registro.'])]
    #[Groups(['usuario:read'])]
    private ?int $lastUserAppId = null;

    #[ORM\Column(length: 30, nullable: true, options: ['comment' => 'Código de autenticación 2FA.'])]
    private ?string $authCode = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Versionado de código 2FA.'])]
    #[Groups(['usuario:read'])]
    private ?int $trustedVersion = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Profile del usuario. Usado para guardar configuraciones propias del usuario.'])]
    private ?array $profile = null;

    #[ORM\ManyToOne(targetEntity: Presidencia::class, inversedBy: 'usuarios')]
    #[ORM\JoinColumn(name: 'presidencia_id', referencedColumnName: 'id', nullable: true)]
    #[ApiProperty(readableLink: true, writableLink: false)]
    #[Groups(['usuario:read'])]
    private ?Presidencia $presidencia = null;

    #[Groups(['usuario:mail'])]
    private ?bool $mailSent = null;

    #[Groups(['usuario:mail'])]
    private ?string $warning = null;

    /**
     * Variable no mapeada. Usada para auditar al momento de borrar la entidad.
     */
    #[ApiProperty(readable: false, writable: false)]
    private $storeId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getDni(): ?int
    {
        return $this->dni;
    }

    public function setDni(?int $dni): static
    {
        $this->dni = $dni;

        return $this;
    }

    public function getApellido(): ?string
    {
        return $this->apellido;
    }

    public function setApellido(?string $apellido): static
    {
        $this->apellido = $apellido;

        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): static
    {
        $this->nombre = $nombre;

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

    public function getFechaAlta(): ?\DateTimeInterface
    {
        return $this->fechaAlta;
    }

    /**
     * Evento lanzado antes de almacenamiento en BD por primera vez.
     */
    #[ORM\PrePersist]
    public function setFechaAlta(): static
    {
        $this->fechaAlta = new \DateTime('now', new \DateTimeZone('America/Argentina/Cordoba'));

        return $this;
    }

    public function getFechaBaja(): ?\DateTimeInterface
    {
        return $this->fechaBaja;
    }

    public function setFechaBaja(?\DateTimeInterface $fechaBaja): static
    {
        $this->fechaBaja = $fechaBaja;

        return $this;
    }

    public function getUltimoAcceso(): ?\DateTimeInterface
    {
        return $this->ultimoAcceso;
    }

    public function setUltimoAcceso(?\DateTimeInterface $ultimoAcceso): static
    {
        $this->ultimoAcceso = $ultimoAcceso;

        return $this;
    }

    public function getCantidadAccesos(): ?int
    {
        return $this->cantidadAccesos;
    }

    public function setCantidadAccesos(?int $cantidadAccesos): static
    {
        $this->cantidadAccesos = $cantidadAccesos;

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

    public function __toString()
    {
        return $this->getUsername();
    }

    public function getAuthCode(): ?string
    {
        return $this->authCode;
    }

    public function setAuthCode(?string $authCode): static
    {
        $this->authCode = $authCode;

        return $this;
    }

    public function getTrustedVersion(): ?int
    {
        return $this->trustedVersion;
    }

    public function setTrustedVersion(int $trustedVersion): static
    {
        $this->trustedVersion = $trustedVersion;

        return $this;
    }

    public function getProfile(): ?array
    {
        return $this->profile;
    }

    public function setProfile(?array $profile): static
    {
        $this->profile = $profile;

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

    public function getMailSent(): ?bool
    {
        return $this->mailSent;
    }

    public function getWarning(): ?string
    {
        return $this->warning;
    }

    public function setMailDeliveryResult(bool $mailSent, ?string $warning): static
    {
        $this->mailSent = $mailSent;
        $this->warning = $warning;

        return $this;
    }
}
