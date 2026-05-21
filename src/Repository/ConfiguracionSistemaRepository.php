<?php

/**
 * Repositorio de ConfiguracionSistema.
 */

namespace App\Repository;

use App\Entity\ConfiguracionSistema;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConfiguracionSistema>
 *
 * @method ConfiguracionSistema|null find($id, $lockMode = null, $lockVersion = null)
 * @method ConfiguracionSistema|null findOneBy(array $criteria, array $orderBy = null)
 * @method ConfiguracionSistema[]    findAll()
 * @method ConfiguracionSistema[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ConfiguracionSistemaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConfiguracionSistema::class);
    }
}
