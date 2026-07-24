<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\CreateUsuarioDto;
use App\Dto\UpdateUsuarioDto;
use App\Entity\Usuario;
use App\Repository\PresidenciaRepository;
use App\Repository\UsuarioRepository;
use App\Service\UsuarioService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UsuarioWriteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly UsuarioRepository $usuarioRepository,
        private readonly PresidenciaRepository $presidenciaRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UsuarioService $usuarioService,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Usuario
    {
        if ($data instanceof CreateUsuarioDto) {
            return $this->create($data);
        }

        if ($data instanceof UpdateUsuarioDto) {
            return $this->update($data, (int) ($uriVariables['id'] ?? 0));
        }

        throw new \LogicException('El procesador de usuarios recibió datos incompatibles.');
    }

    private function create(CreateUsuarioDto $input): Usuario
    {
        $username = trim((string) $input->username);
        $this->assertUsernameAvailable($username);
        $this->assertAssignableRoles($input->roles);

        $usuario = new Usuario();
        $usuario
            ->setUsername($username)
            ->setApellido(trim((string) $input->apellido))
            ->setNombre($this->normalizeOptionalText($input->nombre))
            ->setEmail(trim((string) $input->email))
            ->setRoles(array_values(array_unique($input->roles)))
            ->setTrustedVersion(0)
            ->setPresidencia($this->resolvePresidencia($input->presidenciaId));

        $temporaryPassword = $this->usuarioService->generatePassword();
        $usuario->setPassword($this->passwordHasher->hashPassword($usuario, $temporaryPassword));

        $this->entityManager->persist($usuario);
        $this->entityManager->flush();

        $mailSent = $this->usuarioService->sendEmailPassword(
            (string) $usuario->getEmail(),
            $usuario->getUserIdentifier(),
            $temporaryPassword,
        );
        $usuario->setMailDeliveryResult(
            $mailSent,
            $mailSent ? null : 'El usuario fue creado, pero no se pudo enviar el correo con la contraseña temporal.',
        );

        return $usuario;
    }

    private function update(UpdateUsuarioDto $input, int $id): Usuario
    {
        $usuario = $this->usuarioRepository->find($id);
        if (!$usuario instanceof Usuario) {
            throw new NotFoundHttpException('El usuario solicitado no existe.');
        }

        if (!$this->security->isGranted('ROLE_SUPER_ADMIN') && in_array('ROLE_SUPER_ADMIN', $usuario->getRoles(), true)) {
            throw new AccessDeniedHttpException('Solo un superadministrador puede modificar otro superadministrador.');
        }

        $payload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        if (null !== $input->username) {
            $username = trim($input->username);
            $this->assertUsernameAvailable($username, $usuario);
            $usuario->setUsername($username);
        }
        if (null !== $input->apellido) {
            $usuario->setApellido(trim($input->apellido));
        }
        if (array_key_exists('nombre', $payload)) {
            $usuario->setNombre($this->normalizeOptionalText($input->nombre));
        }
        if (null !== $input->email) {
            $usuario->setEmail(trim($input->email));
        }
        if (array_key_exists('presidenciaId', $payload)) {
            $usuario->setPresidencia($this->resolvePresidencia($input->presidenciaId));
        }
        if (null !== $input->roles) {
            $this->assertAssignableRoles($input->roles);
            $usuario->setRoles(array_values(array_unique($input->roles)));
        }

        $this->entityManager->flush();

        return $usuario;
    }

    private function assertUsernameAvailable(string $username, ?Usuario $current = null): void
    {
        $existing = $this->usuarioRepository->findOneBy(['username' => $username]);
        if ($existing instanceof Usuario && $existing->getId() !== $current?->getId()) {
            throw new ConflictHttpException('Este nombre de usuario ya está registrado.');
        }
    }

    /**
     * @param list<string> $roles
     */
    private function assertAssignableRoles(array $roles): void
    {
        if (!$this->security->isGranted('ROLE_SUPER_ADMIN') && in_array('ROLE_SUPER_ADMIN', $roles, true)) {
            throw new AccessDeniedHttpException('Solo un superadministrador puede asignar el rol de superadministrador.');
        }
    }

    private function resolvePresidencia(?int $id): ?\App\Entity\Presidencia
    {
        if (null === $id) {
            return null;
        }

        $presidencia = $this->presidenciaRepository->find($id);
        if (null === $presidencia) {
            throw new NotFoundHttpException('La presidencia seleccionada no existe.');
        }

        return $presidencia;
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }
}
