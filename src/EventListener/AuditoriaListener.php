<?php

/**
 * Listener de Doctrine: captura eventos de persistencia y los registra en el schema de auditoría.
 */

namespace App\EventListener;

use App\Service\AuditoriaService;
use App\Service\JWTImpersonationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postRemove)]
class AuditoriaListener
{
    public function __construct(
        private AuditoriaService $auditoriaService,
        private JWTImpersonationService $jwtImpersonationService,
    ) {
    }

    /**
     * Verifica si la entidad es auditable.
     */
    private function isClaseAuditable(object $obj): bool
    {
        foreach ($this->auditoriaService->getEntitiesAuditables() as $class) {
            if ($obj instanceof $class) {
                return true;
            }
        }

        return false;
    }

    // ID reservado para operaciones realizadas desde la API pública (frontend de inscripción)
    private const PUBLIC_API_USER_ID = -1;

    /**
     * Obtiene el id del usuario real (no impersonado).
     * Retorna PUBLIC_API_USER_ID cuando la petición viene autenticada por API Key (sin usuario de BD).
     */
    private function getRealUserId(): int
    {
        return $this->jwtImpersonationService->getRealUserFromPayload()?->getId() ?? self::PUBLIC_API_USER_ID;
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $e = $event->getObject();
        if ($this->isClaseAuditable($e)) {
            $e->setLastUserAppId($this->getRealUserId());
        }
    }

    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $e = $event->getObject();
        if ($this->isClaseAuditable($e)) {
            $e->setLastUserAppId($this->getRealUserId());
        }
    }

    public function preRemove(PreRemoveEventArgs $event): void
    {
        $e = $event->getObject();
        if (method_exists($e, 'getId') && method_exists($e, 'setStoreId')) {
            $e->setStoreId($e->getId());
        }
    }

    public function postRemove(PostRemoveEventArgs $event): void
    {
        $e = $event->getObject();

        if (!$this->isClaseAuditable($e)) {
            return;
        }

        $storeId = method_exists($e, 'getStoreId') ? $e->getStoreId() : null;
        $entityName = (new \ReflectionClass($e))->getShortName();

        $this->auditoriaService->stampUserIdDelete(
            $entityName,
            $storeId,
            $this->getRealUserId()
        );
    }
}
