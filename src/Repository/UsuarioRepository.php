<?php

/**
 * Repositorio de Usuario: incluye método para actualizar datos de último acceso.
 */

namespace App\Repository;

use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Usuario>
 *
 * @method Usuario|null find($id, $lockMode = null, $lockVersion = null)
 * @method Usuario|null findOneBy(array $criteria, array $orderBy = null)
 * @method Usuario[]    findAll()
 * @method Usuario[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UsuarioRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Usuario::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Usuario) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Actualizar fecha de ultimo logueo y contabilizador de entradas al sistema.
     */
    public function actualizarLogueoUsuario(Usuario $usuario)
    {
        $usuario->setUltimoAcceso(new \DateTime('now', new \DateTimeZone('America/Argentina/Cordoba')));
        $cantidadAnterior = $usuario->getCantidadAccesos() != null ? $usuario->getCantidadAccesos() : 0;
        $usuario->setCantidadAccesos($cantidadAnterior + 1);

        $this->getEntityManager()->persist($usuario);
        $this->getEntityManager()->flush();
    }
}
