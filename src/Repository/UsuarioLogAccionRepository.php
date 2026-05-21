<?php

/**
 * Repositorio de UsuarioLogAccion.
 */

namespace App\Repository;

use App\Entity\UsuarioLogAccion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UsuarioLogAccion>
 *
 * @method UsuarioLogAccion|null find($id, $lockMode = null, $lockVersion = null)
 * @method UsuarioLogAccion|null findOneBy(array $criteria, array $orderBy = null)
 * @method UsuarioLogAccion[]    findAll()
 * @method UsuarioLogAccion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UsuarioLogAccionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UsuarioLogAccion::class);
    }

    //    /**
    //     * @return UsuarioLogAccion[] Returns an array of UsuarioLogAccion objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?UsuarioLogAccion
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
