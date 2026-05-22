<?php

/**
 * Registro de auditoría de accesos: logins, suplantaciones y acciones de usuarios.
 */

namespace App\Entity;

use App\Repository\UsuarioLogAccionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UsuarioLogAccionRepository::class)]
#[ORM\Table(schema: EntitySchema::MAIN, options: ['comment' => 'Auditoría de Accesos de Usuarios al Sistema'])]
class UsuarioLogAccion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIMETZ_MUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $fechaHora = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Usuario $Usuario = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accion = null;

    #[ORM\ManyToOne]
    private ?Usuario $impersonateTo = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFechaHora(): ?\DateTimeInterface
    {
        return $this->fechaHora;
    }

    public function setFechaHora(\DateTimeInterface $fechaHora): static
    {
        $this->fechaHora = $fechaHora;

        return $this;
    }

    public function getUsuario(): ?Usuario
    {
        return $this->Usuario;
    }

    public function setUsuario(?Usuario $Usuario): static
    {
        $this->Usuario = $Usuario;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }

    public function getAccion(): ?string
    {
        return $this->accion;
    }

    public function setAccion(?string $accion): static
    {
        $this->accion = $accion;

        return $this;
    }

    public function getImpersonateTo(): ?Usuario
    {
        return $this->impersonateTo;
    }

    public function setImpersonateTo(?Usuario $impersonateTo): static
    {
        $this->impersonateTo = $impersonateTo;

        return $this;
    }
}
